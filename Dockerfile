# LER BEM - PHP 8.4 (FPM) + nginx + MariaDB em um unico container.
# Aplicacao PHP tradicional: sem Node.js no servidor de producao.
FROM php:8.4.27-fpm-alpine

ENV PORT=3000 \
    TZ=America/Sao_Paulo

# Pacotes do sistema atualizados + nginx + servidor de banco + dependencias de extensoes
RUN apk upgrade --no-cache \
 && apk add --no-cache nginx mariadb mariadb-client tzdata oniguruma \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS oniguruma-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring \
 && apk del .build-deps \
 && rm -rf /var/cache/apk/* /tmp/*

COPY docker/php-security.ini /usr/local/etc/php/conf.d/zz-ler-bem-security.ini
COPY docker/nginx-ler-bem.conf /etc/nginx/http.d/default.conf
COPY docker/mariadb-ler-bem.cnf /etc/my.cnf.d/99-ler-bem.cnf
COPY docker/entrypoint.sh /usr/local/bin/ler-bem-entrypoint

RUN chmod +x /usr/local/bin/ler-bem-entrypoint \
 && mkdir -p /run/mysqld /var/lib/mysql /var/www/html/uploads \
 && chown -R mysql:mysql /run/mysqld /var/lib/mysql

COPY . /var/www/html

# Remove metadados de versionamento e garante permissoes de escrita
RUN rm -rf /var/www/html/.git /var/www/html/.github \
 && mkdir -p /var/www/html/uploads /var/www/html/config \
 && chown -R www-data:www-data /var/www/html/uploads /var/www/html/config

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=75s --retries=5 \
  CMD wget -q -O /dev/null http://127.0.0.1:3000/login.php || exit 1

ENTRYPOINT ["/usr/local/bin/ler-bem-entrypoint"]
CMD ["nginx", "-g", "daemon off;"]
