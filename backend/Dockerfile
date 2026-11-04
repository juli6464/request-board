FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-cli-alpine
RUN docker-php-ext-install pdo_mysql
WORKDIR /var/www
COPY --from=vendor /app /var/www
RUN cp -n .env.example .env \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && php artisan key:generate --force \
 && php artisan package:discover --ansi

# Servidor embebido de PHP con el router de Laravel y 4 workers (una llamada lenta
# al LLM no bloquea el listado). Se usa `php -S` en vez de `artisan serve` porque
# este último hace que el .env prevalezca sobre las variables del contenedor.
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000
# El router resuelve index.php relativo al directorio actual -> arrancar desde public/.
WORKDIR /var/www/public
CMD ["php", "-S", "0.0.0.0:8000", "../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
