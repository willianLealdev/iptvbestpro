#!/bin/sh
set -e

APP=/var/www/html
DATA=/data

mkdir -p "$DATA"

# Pastas de mídia: na primeira vez copia os arquivos padrão para o volume,
# depois troca a pasta do app por um link para o volume.
for dir in images uploads; do
    if [ ! -d "$DATA/$dir" ]; then
        cp -a "$APP/$dir" "$DATA/$dir"
    fi
    if [ ! -L "$APP/$dir" ]; then
        rm -rf "$APP/$dir"
        ln -s "$DATA/$dir" "$APP/$dir"
    fi
done

# Licença: o instalador grava includes/.lic; o link faz ela ficar no volume.
rm -f "$APP/includes/.lic"
ln -s "$DATA/.lic" "$APP/includes/.lic"

chown -R www-data:www-data "$DATA"

# Imagens: restaura do banco as que sumiram (deploy) logo ao iniciar e depois a cada 5 minutos
# guarda no banco as novas. Assim pôsteres, logos e fundos nunca ficam em branco.
(
    sleep 8
    while true; do
        su -p -s /bin/sh www-data -c "php $APP/cron/media_sync.php" || true
        sleep 300
    done
) &

# Monitor de DNS das playlists: a cada 5 minutos (primeiro ciclo 1 minuto após iniciar)
(
    sleep 60
    while true; do
        su -p -s /bin/sh www-data -c "php $APP/cron/dns_monitor.php" || true
        sleep 300
    done
) &

exec docker-php-entrypoint "$@"
