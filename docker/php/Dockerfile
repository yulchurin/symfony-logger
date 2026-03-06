FROM unit:1.34.2-php8.4

RUN apt-get update -y && apt-get upgrade -y
RUN apt-get install -y curl gnupg2 ca-certificates lsb-release --no-install-recommends

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        librabbitmq-dev \
    && rm -rf /var/lib/apt/lists/*

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions amqp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY unit.json /docker-entrypoint.d/
