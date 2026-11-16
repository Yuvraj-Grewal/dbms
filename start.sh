#!/bin/bash
PORT=${PORT:-10000}
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
mysqld_safe --innodb_buffer_pool_size=32M --performance_schema=OFF &
for i in $(seq 1 60); do mariadb-admin ping --silent && break; sleep 1; done
mariadb < /app/schema.sql
mariadb -e "CREATE USER IF NOT EXISTS 'app'@'localhost' IDENTIFIED BY 'app'; CREATE USER IF NOT EXISTS 'app'@'127.0.0.1' IDENTIFIED BY 'app'; GRANT ALL ON dbms_project.* TO 'app'@'localhost','app'@'127.0.0.1';"
exec apache2-foreground
