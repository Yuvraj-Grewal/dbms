FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends mariadb-server \
 && rm -rf /var/lib/apt/lists/* && docker-php-ext-install pdo_mysql
COPY index.php /var/www/html/index.php
COPY schema.sql /app/schema.sql
COPY start.sh /app/start.sh
ENV DB_HOST=127.0.0.1 DB_USER=app DB_PASS=app
CMD ["bash","/app/start.sh"]
