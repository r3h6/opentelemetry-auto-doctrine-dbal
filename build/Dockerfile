ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

# Install necessary packages
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    p7zip-full \
    && docker-php-ext-install zip \
    && docker-php-ext-enable zip

# Install OpenTelemetry extension
RUN pecl install opentelemetry && docker-php-ext-enable opentelemetry

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD ["composer"]