FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite

# PHP config: log errors cleanly, don't dump HTML into responses
RUN echo "display_errors = Off" >> /usr/local/etc/php/conf.d/errors.ini && \
    echo "display_startup_errors = Off" >> /usr/local/etc/php/conf.d/errors.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/errors.ini && \
    echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/errors.ini && \
    echo "html_errors = Off" >> /usr/local/etc/php/conf.d/errors.ini && \
    echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini

COPY ./src /var/www/html

RUN mkdir -p /var/www/html/uploads /var/www/html/storage/evidence && \
    chown -R www-data:www-data /var/www/html/uploads /var/www/html/storage