#!/bin/sh
set -eu

mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS queuecare_test;
GRANT ALL PRIVILEGES ON queuecare_test.* TO 'queuecare'@'%';
SQL
