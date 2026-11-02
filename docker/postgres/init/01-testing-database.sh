#!/bin/sh
# Runs once, when the postgres volume is first created.
# Backend tests (phpunit.xml) use this database, never the dev one.
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<SQL
CREATE DATABASE ${POSTGRES_DB}_testing OWNER "$POSTGRES_USER";
SQL
