# Etapa 1: base de geolocalização por país (DB-IP Lite, CC BY 4.0) + leitor MMDB
FROM composer:2 AS geo
WORKDIR /geo
RUN composer require maxmind-db/reader:^1.11 --no-interaction --no-progress --no-plugins \
    && apk add --no-cache curl \
    && M_ATUAL=$(date +%Y-%m) \
    && M_ANTERIOR=$(date -d @$(( $(date +%s) - 2764800 )) +%Y-%m) \
    && ( curl -fsSL "https://download.db-ip.com/free/dbip-country-lite-${M_ATUAL}.mmdb.gz" -o country.mmdb.gz \
         || curl -fsSL "https://download.db-ip.com/free/dbip-country-lite-${M_ANTERIOR}.mmdb.gz" -o country.mmdb.gz ) \
    && gunzip country.mmdb.gz

# Etapa 2: PHPMailer atualizado (substitui o vendor/ antigo do pacote original)
FROM composer:2 AS mailer
WORKDIR /mail
RUN composer require phpmailer/phpmailer:^6.9 --no-interaction --no-progress --no-plugins --optimize-autoloader

FROM php:8.2-apache

# Extensões exigidas pelo painel (mysqli, pdo, curl, gd, exif)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" mysqli pdo pdo_mysql gd exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Limites de upload e fuso horário
RUN { \
      echo "upload_max_filesize=64M"; \
      echo "post_max_size=64M"; \
      echo "memory_limit=256M"; \
      echo "max_execution_time=300"; \
      echo "date.timezone=America/Sao_Paulo"; \
      echo "display_errors=Off"; \
      echo "log_errors=On"; \
      echo "error_reporting=E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING"; \
      echo "expose_php=Off"; \
      echo "session.cookie_httponly=1"; \
      echo "session.cookie_secure=1"; \
      echo "session.cookie_samesite=Lax"; \
      echo "session.use_strict_mode=1"; \
      echo "session.use_only_cookies=1"; \
      echo "session.sid_length=48"; \
      echo "session.sid_bits_per_character=6"; \
      echo "allow_url_include=Off"; \
    } > /usr/local/etc/php/conf.d/painel.ini

# Segurança do Apache: pastas internas bloqueadas, sem PHP em images/uploads, cabeçalhos, sem versão
COPY docker/apache-security.conf /etc/apache2/conf-available/painel-security.conf
RUN a2enconf painel-security

# URLs limpas (sem .php) e imagens restauradas do banco (media.php)
COPY docker/apache-rewrite.conf /etc/apache2/conf-available/painel-rewrite.conf
RUN a2enconf painel-rewrite

COPY --from=geo /geo /opt/geo
COPY . /var/www/html/
RUN rm -rf /var/www/html/vendor /var/www/html/docker
COPY --from=mailer /mail/vendor /var/www/html/vendor
COPY docker-entrypoint.sh /usr/local/bin/painel-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/painel-entrypoint \
    && chmod +x /usr/local/bin/painel-entrypoint \
    && rm -f /var/www/html/Dockerfile /var/www/html/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

# /data guarda o que precisa sobreviver aos deploys: licença (.lic), images/ e uploads/
VOLUME ["/data"]
EXPOSE 80

ENTRYPOINT ["painel-entrypoint"]
CMD ["apache2-foreground"]
