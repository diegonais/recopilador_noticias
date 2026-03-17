FROM php:8.2-cli

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

RUN chmod +x /var/www/html/docker/entrypoint.sh \
    && mkdir -p /var/www/html/storage/cache /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/update.log \
    && printf "[]\n" > /var/www/html/storage/news.json \
    && crontab /var/www/html/docker/cron/portal-noticias-abi

EXPOSE 3003

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
