FROM php:8.1-fpm

# install system deps required to build librdkafka/rdkafka and intl
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    autoconf \
    pkg-config \
    libssl-dev \
    librdkafka-dev \
    zlib1g-dev \
    libicu-dev \
    ca-certificates \
    git \
    unzip && \
  pecl channel-update pecl.php.net && \
  pecl install rdkafka && \
  docker-php-ext-enable rdkafka && \
  docker-php-ext-install intl && \
  # cleanup to keep image small
  apt-get purge -y build-essential autoconf pkg-config git unzip && \
  apt-get autoremove -y && \
  rm -rf /var/lib/apt/lists/* /tmp/pear

# install composer binary from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# copy composer files and install dependencies (composer sees intl and rdkafka)
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

COPY . /var/www/html
