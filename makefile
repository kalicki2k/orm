build:
	APP_ENV=development docker compose -f docker/compose.yml build

dev:
	APP_ENV=development docker compose -f docker/compose.yml up --build --watch

test:
	APP_ENV=development docker compose -f docker/compose.yml run --rm php vendor/bin/phpunit

composer:
	APP_ENV=development docker compose -f docker/compose.yml run --rm php composer

bash:
	APP_ENV=development docker compose -f docker/compose.yml run --rm php bash