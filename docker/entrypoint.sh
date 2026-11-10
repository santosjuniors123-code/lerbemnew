#!/bin/sh
# LER BEM - inicializa MariaDB, cria banco/usuario e instala o esquema na primeira execucao.
set -eu

DB_NAME="${DB_NAME:-ler_bem}"
DB_USER="${DB_USER:-lerbem}"
DB_HOST="${DB_HOST:-127.0.0.1}"
SOCKET="/run/mysqld/mysqld.sock"
PASS_FILE="/var/lib/mysql/.ler_bem_db_pass"

mkdir -p /run/mysqld /var/lib/mysql /var/www/html/uploads
chown -R mysql:mysql /run/mysqld /var/lib/mysql

# Senha do banco: usa a do ambiente ou gera/persiste uma aleatoria
if [ -n "${DB_PASSWORD:-}" ]; then
  DB_PASS="$DB_PASSWORD"
elif [ -f "$PASS_FILE" ]; then
  DB_PASS="$(cat "$PASS_FILE")"
else
  DB_PASS="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
fi
printf '%s' "$DB_PASS" > "$PASS_FILE"
chmod 600 "$PASS_FILE"

if [ ! -d /var/lib/mysql/mysql ]; then
  echo "[i] Inicializando o diretorio de dados do MariaDB..."
  mariadb-install-db --user=mysql --datadir=/var/lib/mysql \
    --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1 || {
      echo "[x] Falha ao inicializar o MariaDB." >&2; exit 1; }
fi

echo "[i] Iniciando o MariaDB..."
mariadbd --user=mysql --datadir=/var/lib/mysql \
  --socket="$SOCKET" --bind-address="$DB_HOST" --port=3306 --skip-networking=0 &
MYSQL_PID=$!

i=0
while [ "$i" -lt 90 ]; do
  if mariadb-admin --socket="$SOCKET" ping >/dev/null 2>&1; then break; fi
  if ! kill -0 "$MYSQL_PID" 2>/dev/null; then
    echo "[x] O MariaDB encerrou durante a inicializacao." >&2
    exit 1
  fi
  i=$((i + 1))
  sleep 1
done

if ! mariadb-admin --socket="$SOCKET" ping >/dev/null 2>&1; then
  echo "[x] MariaDB nao respondeu no tempo esperado." >&2
  exit 1
fi

echo "[i] Garantindo banco e usuario da aplicacao..."
mariadb --socket="$SOCKET" -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

export DB_HOST DB_NAME DB_USER DB_PORT="3306"
export DB_PASSWORD="$DB_PASS"

# Espera a conexao TCP ficar disponivel para o usuario da aplicacao
i=0
while [ "$i" -lt 40 ]; do
  if mariadb --protocol=TCP -h "$DB_HOST" -P 3306 -u "$DB_USER" -p"$DB_PASS" -e 'SELECT 1' >/dev/null 2>&1; then
    break
  fi
  i=$((i + 1))
  sleep 1
done

# Instalador idempotente: cria o esquema e, se ainda nao houver usuarios, os dados iniciais
if [ -z "${ADMIN_EMAIL:-}" ]; then export ADMIN_EMAIL="admin@lerbem.local"; fi
if [ -z "${ADMIN_PASSWORD:-}" ]; then
  export ADMIN_PASSWORD="$(head -c 9 /dev/urandom | base64 | tr -d '/+=')Aa1!"
  echo "=============================================================="
  echo "[i] Senha inicial do administrador (altere apos o primeiro acesso):"
  echo "    E-mail: ${ADMIN_EMAIL}"
  echo "    Senha : ${ADMIN_PASSWORD}"
  echo "=============================================================="
fi

php /var/www/html/database/install_cli.php \
  || { echo "[!] Nova tentativa de instalacao em 5s..."; sleep 5; \
       php /var/www/html/database/install_cli.php \
       || echo "[!] Instalacao automatica retornou erro; verifique as mensagens acima."; }

# O PHP roda como www-data e precisa ler a configuracao gerada
if [ -f /var/www/html/config/config.local.php ]; then
  chown www-data:www-data /var/www/html/config/config.local.php
  chmod 0640 /var/www/html/config/config.local.php
fi

chown -R www-data:www-data /var/www/html/uploads

echo "[i] Iniciando o PHP-FPM..."
php-fpm -D

echo "[i] LER BEM pronto nas portas 80/3000."

exec "$@"
