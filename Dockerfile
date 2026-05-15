FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV PORT=10000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY . .

RUN mkdir -p uploads/products uploads/categories uploads/profile_images uploads/hero_slides uploads/homepage_feature logs \
    && chown -R www-data:www-data uploads logs \
    && sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf \
    && sed -i "/<Directory \\/var\\/www\\/>/,/<\\/Directory>/ s/AllowOverride None/AllowOverride All/" /etc/apache2/apache2.conf

EXPOSE 10000

CMD ["apache2-foreground"]
