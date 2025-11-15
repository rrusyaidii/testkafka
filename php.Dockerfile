FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    librdkafka-dev \
    build-essential \
    git \
    unzip \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
