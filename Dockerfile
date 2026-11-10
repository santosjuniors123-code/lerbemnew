# LER BEM - PHP 8.4 (Apache) + MariaDB em um unico container.
# Aplicacao PHP tradicional: sem Node.js no servidor de producao.
FROM php:8.4.27-apache

ENV DEBIAN_FRONTEND=noninteractive \
    APACHE_DOCUMENT_ROOT=/var/www/html

# Pacotes do sistema atualizados + servidor de banco + dependencias de extensoes PHP
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      mariadb-server mariadb-client \
      libonig-dev libcurl4-openssl-dev \
      autoconf g++ make pkg-config \
 && apt-get -y upgrade \
 && rm -rf /var/lib/apt/lists/*

# Extensoes necessarias: PDO MySQL (obrigatorio), mbstring e curl (IA)
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring \
 && a2enmod rewrite headers \
 && rm -f /etc/apache2/sites-enabled/000-default.conf

COPY docker/php-security.ini /usr/local/etc/php/conf.d/zz-ler-bem-security.ini
COPY docker/apache-ler-bem.conf /etc/apache2/sites-available/ler-bem.conf
COPY docker/mariadb-ler-bem.cnf /etc/mysql/mariadb.conf.d/99-ler-bem.cnf
COPY docker/entrypoint.sh /usr/local/bin/ler-bem-entrypoint
RUN ln -sf /etc/apache2/sites-available/ler-bem.conf /etc/apache2/sites-enabled/ler-bem.conf \
 && chmod +x /usr/local/bin/ler-bem-entrypoint \
 && mkdir -p /run/mysqld /var/lib/mysql /var/www/html/uploads \
 && chown -R mysql:mysql /run/mysqld /var/lib/mysql

COPY . /var/www/html

RUN mkdir -p /var/www/html/uploads /var/www/html/config \
 && chown -R www-data:www-data /var/www/html/uploads /var/www/html/config

ENV PORT=3000
EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=5 \
  CMD curl -fsS http://127.0.0.1:3000/login.php >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/ler-bem-entrypoint"]
CMD ["apache2-foreground"]
