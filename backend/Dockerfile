FROM dunglas/frankenphp:php8.4

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    bash \
    && docker-php-ext-install pdo pdo_mysql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --no-autoloader --no-scripts --no-progress --prefer-dist

RUN composer dump-autoload --optimize

RUN chmod -R 777 /app

EXPOSE 80

CMD ["bash", "-c", "frankenphp run"]
