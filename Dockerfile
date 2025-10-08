FROM php:8.3-cli

RUN apt-get update && apt-get install -y git unzip librabbitmq-dev && rm -rf /var/lib/apt/lists/*
RUN pecl install amqp pcov && docker-php-ext-enable amqp pcov
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY --chown=1000:1000 . /var/www/project

WORKDIR /var/www/project
