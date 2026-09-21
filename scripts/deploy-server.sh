#!/usr/bin/env bash
set -euo pipefail
export PATH="/opt/plesk/php/8.3/bin:$PATH"
cd /var/www/vhosts/portada.info/prensa.portada.info
test -s .env || { echo 'Falta el archivo .env de producción'; exit 1; }
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/{private,public} bootstrap/cache
chmod -R u+rwX storage bootstrap/cache
rm -f bootstrap/cache/config.php
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
key_missing=$(php -r 'require "vendor/autoload.php"; $env = Dotenv\Dotenv::createImmutable(getcwd())->load(); echo empty($env["APP_KEY"]) ? "yes" : "no";')
if [ "$key_missing" = yes ]; then php artisan key:generate --force; fi
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan up
