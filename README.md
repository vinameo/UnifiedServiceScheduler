# Unified Service Scheduler

Service appointment scheduler for car dealerships: Laravel API (`api/`), React + Mantine web app (`web/`), PostgreSQL, Nginx, all in Docker.

## Run locally

Needs Docker only.

```bash
docker compose up -d --build
```

- Customer app: http://localhost:8088
- Staff web: http://localhost:8088/staff
- Health: http://localhost:8088/health/ready

The first start installs Composer packages, creates `api/.env`, runs migrations and seeders, and builds the web app. Change the port with `APP_PORT=9000 docker compose up -d`.

On Linux, build with your user id so files written by PHP stay editable: `UID=$(id -u) GID=$(id -g) docker compose build`.

### Hot reload for the web app

```bash
cd web
npm ci
npm run dev   # http://localhost:5173, API calls go to the nginx container
```

### Checks

```bash
docker compose exec php php artisan test
docker compose exec php ./vendor/bin/pint
docker compose exec php ./vendor/bin/phpstan analyse
cd web && npm run lint && npm run test
```

## Deploy

```bash
cp .env.production.example .env.production   # fill in every CHANGE_ME
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
```

- Put `fullchain.pem` and `privkey.pem` in `TLS_CERT_DIR` (default `docker/nginx/certs/`). HTTP redirects to HTTPS.
- Set `RUN_SEEDERS=true` for the first deploy to create the staff accounts.
- Generate `APP_KEY` with `docker compose -f docker-compose.prod.yml --env-file .env.production run --rm --no-deps php php artisan key:generate --show` (any value works for this one run).

See [CLAUDE.md](CLAUDE.md) for the architecture rules and coding conventions.
