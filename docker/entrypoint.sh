#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/cache storage/logs
[ -f storage/news.json ] || printf "[]\n" > storage/news.json
[ -f storage/logs/update.log ] || touch storage/logs/update.log

php /var/www/html/scripts/update_news.php || true

cron

exec php -S 0.0.0.0:3003 -t /var/www/html/public /var/www/html/router.php