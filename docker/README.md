# Docker deployment

This setup builds a production image for Laravel/PHP-FPM, a separate Nginx web
image with compiled Vite assets, and services for MySQL, the database queue
worker, and the Laravel scheduler.

## First run

```bash
cp .env.docker.example .env.docker
docker compose --env-file .env.docker build
docker compose --env-file .env.docker run --rm --entrypoint php app artisan key:generate --show
```

Paste the printed value into `.env.docker`:

```text
DOCKER_APP_KEY=base64:...
```

Then start the stack:

```bash
docker compose --env-file .env.docker up -d --build
```

The app will be available at:

```text
http://localhost:8080
```

The `app` service runs migrations and reference-data seeders automatically by default.

## Stable app key

`DOCKER_APP_KEY` is required. Generate one with:

```bash
docker compose --env-file .env.docker run --rm --entrypoint php app artisan key:generate --show
```

Then paste the printed value into:

```text
DOCKER_APP_KEY=base64:...
```

## Useful commands

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f app
docker compose --env-file .env.docker exec app php artisan migrate --force
docker compose --env-file .env.docker exec app php artisan db:seed --force
docker compose --env-file .env.docker down
```

To remove persistent MySQL and storage volumes:

```bash
docker compose --env-file .env.docker down -v
```
