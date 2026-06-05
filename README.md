# Kingdom Idle

A web-based idle kingdom game where users collect resources, upgrade buildings, complete minigames, unlock achievements, prestige through road progress, and compete on leaderboards.

## Features

- User registration, login, logout, email verification, and account security settings.
- Dashboard mode with resources, production rates, building upgrades, achievements, prestige, weather, and leaderboards.
- Immersive mode with a visual kingdom map, weather effects, time-of-day support, and icon-based game actions.
- Passive hourly production, once-per-day manual collection, and prestige progression.
- Resource minigames for wood, food, stone, and gold.
- Achievement bonuses that improve building production.
- Open-Meteo based weather snapshots with optional browser-provided geolocation.

## Tech Stack

- Frontend: Vue 3, Inertia.js, TypeScript, Vite, Tailwind CSS
- Backend: PHP 8.4, Laravel 13, Fortify
- Database: MySQL 8.4
- Testing: Pest, Larastan, Laravel Pint
- Local Docker: Laravel Sail

## Installation

Clone the repository:

```bash
git clone https://github.com/JakeBane77/praktikos-projektas.git
cd praktikos-projektas
```

Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Run migrations and seed base game data:

```bash
php artisan migrate --seed
```

Optional demo data with 200 factory-generated users:

```bash
php artisan db:seed --class=FactoryDemoSeeder
```

## Usage

Run the Laravel server, queue worker, and Vite dev server together:

```bash
composer dev
```

Then open:

```web page
http://localhost:8000
```

With Laravel Sail:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev
./vendor/bin/sail php artisan schedule:work
```

Then open:

```web page
http://localhost
```

Useful commands:

```bash
#commands `composer dev` runs
php artisan serve
npm run dev
php artisan schedule:work

#database commands
php	artisan migrate # runs new database migrations
php	artisan migrate:fresh # delete current database and run new migrations
php artisan db:seed # run database seeders
php artisan db:seed --class=FactoryDemoSeeder # seed database with 200 randomised users, default password is 'password'

#scheduler commands
php artisan schedule:work # launch the service
php artisan schedule:list # show all active schedules
php artisan weather:update # manually launch the schedule

#docker (sail) commands

vendor/bin/sail up -d # start container

vendor/bin/sail artisan migrate --seed
vendor/bin/sail npm install
vendor/bin/sail npm run dev
vendor/bin/sail php artisan schedule:work

vendor/bin/sail down # close container

#docker commands
docker compose up -d

docker compose exec laravel.test npm install
docker compose exec laravel.test npm run build
docker compose exec laravel.test php artisan optimize:clear
docker compose exec laravel.test php artisan migrate --seed

docker compose down -d

#test commands
php artisan test # runs pest unit tests
vendor/bin/pint --test
./vendor/bin/pint # removes code style issues
./vendor/bin/phpstan analyse --memory-limit=2G # runs larastan static code analysis

#extra commands
npm run build

```

## Project Structure

```text
praktikos-projektas/
|-- app/
|   |-- Http/Controllers/
|   |-- Models/
|   |-- Policies/
|   |-- Services/
|   `-- Support/
|-- database/
|   |-- factories/
|   |-- migrations/
|   `-- seeders/
|-- public/
|-- resources/
|   |-- css/
|   `-- js/
|       |-- components/
|       |-- lib/
|       `-- pages/
|-- routes/
|-- tests/
|-- composer.json
|-- package.json
|-- docker-compose.yml
`-- README.md
```

## Environment Variables

Create a `.env` file in the project root:

```env
APP_NAME="Kingdom Idle"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

APP_PORT=80
VITE_PORT=5173
FORWARD_DB_PORT=3306

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

VITE_APP_NAME="${APP_NAME}"
```

For a local XAMPP setup without Sail, adjust `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to match your MySQL configuration.

To run without a database server use `DB_CONNECTION=SQLITE` and comment out (`#`) other DB variables.

## Roadmap
- Add more features:
    - Add more resources, buildings, minigames, achievements.
    - Add social features (alliances, alliance chat, alliance tasks for bonuses).
    - Add research feature (unlocks buildings, bonuses to productions, unlocks alliance feature).
- Add frontend tests.
