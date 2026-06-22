FROM php:8.1-apache

RUN a2enmod rewrite
RUN docker-php-ext-install curl zip

COPY . /var/www/html/

RUN chmod -R 755 /var/www/html/

EXPOSE 80
