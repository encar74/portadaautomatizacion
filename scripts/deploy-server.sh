#!/usr/bin/env bash
set -euo pipefail
export PATH="/opt/plesk/php/8.3/bin:$PATH"
cd /var/www/vhosts/portada.info/prensa.portada.info
test -s .env || { echo 'Falta el archivo .env de producción'; exit 1; }
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/{private,public} bootstrap/cache
# PHP puede crear archivos con otro propietario. Para reemplazar la caché
# necesitamos poder escribir en su directorio, no cambiar el modo del archivo.
for dir in bootstrap/cache storage/framework/{cache/data,sessions,views} storage/logs storage/app/{private,public}; do
    if [[ ! -w "$dir" || ! -x "$dir" ]]; then
        echo "El usuario de despliegue no puede escribir en $dir. Revisa los permisos de ese directorio en Plesk." >&2
        exit 1
    fi
done
rm -f bootstrap/cache/config.php || { echo 'No se pudo retirar la caché de configuración; revisa bootstrap/cache en Plesk.' >&2; exit 1; }
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
key_missing=$(php -r 'require "vendor/autoload.php"; $env = Dotenv\Dotenv::createImmutable(getcwd())->load(); echo empty($env["APP_KEY"]) ? "yes" : "no";')
if [ "$key_missing" = yes ]; then php artisan key:generate --force; fi
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan up
