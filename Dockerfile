# ChapTarif — image de production (Render)
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql pgsql opcache \
 && a2enmod rewrite headers expires deflate \
 && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/chaptarif.ini
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p storage && chown -R www-data:www-data storage \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
 && echo "ServerTokens Prod\nServerSignature Off" >> /etc/apache2/conf-available/security.conf

ENV PORT=10000
EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
