FROM php:8.3-apache

# MySQL driver used by config.php (PDO)
RUN docker-php-ext-install pdo_mysql

# Timezone for the log date filters (override with TZ in docker-compose.yml)
ENV TZ=Asia/Manila
RUN echo 'date.timezone=${TZ}' > /usr/local/etc/php/conf.d/timezone.ini

# Settings saved from the dashboard live in /data (a Docker volume),
# outside the web root so the password can't be downloaded.
ENV CONFIG_PATH=/data/config.json
RUN mkdir -p /data && chown www-data:www-data /data
VOLUME /data

COPY --chown=www-data:www-data . /var/www/html/

EXPOSE 80
