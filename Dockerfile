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
