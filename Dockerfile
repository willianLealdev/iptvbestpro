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
    } > /usr/local/etc/php/conf.d/painel.ini

# Imagem que sumiu da pasta (ex.: após deploy) é entregue pela cópia do banco (media.php)
RUN {       echo "<Directory /var/www/html>";       echo "    RewriteEngine On";       echo "    RewriteCond %{REQUEST_FILENAME} !-f";       echo "    RewriteRule ^(images|uploads)/(.+)\$ media.php?p=\$1/\$2 [L,QSA]";       echo "</Directory>";     } > /etc/apache2/conf-available/media-fallback.conf     && a2enconf media-fallback

COPY --from=geo /geo /opt/geo
COPY . /var/www/html/
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
