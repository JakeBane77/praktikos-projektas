# Kingdom Idle

Kingdom Idle is a Laravel and Vue idle game where players gather resources over time, upgrade buildings, play minigames, unlock achievements, prestige through road progress, and interact through alliances, chat, and leaderboards.

## Features

- Dashboard mode for resource management, buildings, prestige, achievements, alliances, minigames, weather, and leaderboards.
- Immersive mode with a large visual kingdom map, animated weather, time-of-day changes, and in-world action buttons.
- Passive hourly production and daily manual collection.
- Four resource minigames: wood, food, stone, and gold.
- Achievement bonuses that improve production.
- Alliance system with chat, applications, member management, and alliance goals.
- Weather snapshots powered by Open-Meteo with optional browser geolocation.
- Reverb-based real-time features for alliance chat and presence.

## Tech Stack

- Backend: Laravel 13, PHP 8.4, Fortify, Reverb
- Frontend: Vue 3, Inertia.js, TypeScript, Vite, Tailwind CSS
- Database: MySQL 8.4
- Tooling: Pest, Pint, Larastan
- Containerized local environment: Laravel Sail

## Requirements

For local non-Docker setup:

- PHP 8.4
- Composer
- Node.js and npm
- MySQL 8.4 or compatible MySQL server

For Docker setup:

- Docker Desktop or Docker Engine with Compose support

## Project Setup

Clone the repository:

```bash
git clone https://github.com/JakeBane77/praktikos-projektas.git
cd praktikos-projektas
```

## Local Setup

1. Install dependencies:

```bash
composer install
npm install
```

2. Create the environment file:

```bash
copy .env.example .env
```

If you are not on Windows, use:

```bash
cp .env.example .env
```

3. Generate the app key and create the storage symlink:

```bash
php artisan key:generate
php artisan storage:link
```

4. Configure the database in `.env`.

If you are running the app outside Docker, change the database host and credentials to match your local database server.

For local MySQL, the most important variables are:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

If you want to use SQLite instead:

```env
DB_CONNECTION=sqlite
#DB_HOST=mysql
#DB_PORT=3306
#DB_DATABASE=laravel
#DB_USERNAME=sail
#DB_PASSWORD=password
```

Also make sure `APP_URL` matches the local template:

```env
APP_URL=http://localhost:8000
```

5. Start the local development stack:

```bash
composer dev
```

This starts:

- Laravel web server
- queue listener
- scheduler worker
- Reverb websocket server
- Vite dev server

Open the app at:

```text
http://localhost:8000
```

6. Run migrations and seed base game data:

Run:

```bash
php artisan migrate --seed
```

## Docker Setup With Sail

1. Install dependencies on the host first so Sail runtime files exist:

```bash
composer install
npm install
```

2. Create a Docker-oriented environment file:

```bash
copy .env.docker.example .env
```

If you are not on Windows, use:

```bash
cp .env.docker.example .env
```

3. Generate an application key and place it into `.env`:

```bash
php artisan key:generate --show
```

4. Start Sail:

```bash
vendor/bin/sail up -d
```

5. Run initial app setup inside the container:

```bash
vendor/bin/sail artisan migrate --seed
vendor/bin/sail artisan storage:link
```

6. Start Vite if you want hot reload during development:

```bash
vendor/bin/sail npm run dev
```

Open the app at:

```text
http://localhost
```

The Docker stack includes these long-running services:

- `laravel.test`
- `mysql`
- `queue`
- `scheduler`
- `reverb`

## Environment Notes

### Local `.env`

Use `.env.example` as the base for local development.

Important values:

- `APP_URL=http://localhost:8000`
- local DB credentials
- `BROADCAST_CONNECTION=reverb`
- `REVERB_HOST=127.0.0.1`
- `REVERB_PORT=8080`
- `VITE_REVERB_HOST=127.0.0.1` or `localhost`

### Docker `.env`

Use `.env.docker.example` as the base for Docker.

Important difference:

- `APP_URL=http://localhost:80`
- `REVERB_HOST=reverb` is used inside containers
- `VITE_REVERB_HOST=localhost` is used by the browser

If those are mixed up, websocket features will fail even though the app page loads.

## Demo Data

Base seed data is included in:

- building types
- achievements

To seed the project with extra generated data for testing:

```bash
php artisan db:seed --class=FactoryDemoSeeder
```

This creates 200 factory-generated users and related demo game data.

## Useful Commands

Development:

```bash
composer dev
npm run dev
npm run build
```

Database:

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed
php artisan db:seed --class=FactoryDemoSeeder
```

Queues, scheduler, realtime:

```bash
php artisan queue:listen --tries=1
php artisan schedule:work
php artisan schedule:list
php artisan reverb:start
php artisan reverb:restart
```

Tests and quality checks:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=2G
npm run lint:check
npm run types:check
```

Sail:

```bash
vendor/bin/sail up -d
vendor/bin/sail down
vendor/bin/sail artisan migrate --seed
vendor/bin/sail artisan storage:link
vendor/bin/sail npm run dev
```

## Project Structure

```text
praktikos-projektas/
|-- app/
|   |-- Http/
|   |-- Models/
|   |-- Policies/
|   `-- Services/
|-- bootstrap/
|-- config/
|-- database/
|   |-- factories/
|   |-- migrations/
|   `-- seeders/
|-- public/
|-- resources/
|   |-- css/
|   `-- js/
|       |-- components/
|       |-- composables/
|       |-- lib/
|       `-- pages/
|-- routes/
|-- tests/
|-- composer.json
|-- docker-compose.yml
|-- package.json
`-- README.md
```

## Troubleshooting

- If frontend changes do not appear, make sure `npm run dev` is running or rebuild with `npm run build`.
- If websocket chat/presence does not work, verify Reverb is running and the `REVERB_*` and `VITE_REVERB_*` variables match the environment you are using.
- If Docker starts but database access fails, check whether `.env` contains valid `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values.
- If weather or scheduled progression appears stale, confirm `php artisan schedule:work` is running.

## Roadmap

- Add more buildings, resources, achievements, and minigames.
- Expand alliance systems and cooperative progression.
- Add research and progression unlock trees.
- Add frontend automated tests.
