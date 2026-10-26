# ChapTarif — image de production (Render)
FROM php:8.3-apache

# Correctifs de sécurité d'octobre 2026 (PHP2Shell : PHP >= 8.3.36 ; certificats eMudhra) :
# la build échoue si l'image de base PHP n'est pas à jour, ce qui garde l'ancienne version en ligne.
RUN php -r 'exit(version_compare(PHP_VERSION, "8.3.36", ">=") ? 0 : 1);' \
 || (echo "PHP $(php -r 'echo PHP_VERSION;') trop ancien : image de base non à jour" && exit 1)

RUN apt-get update \
 && apt-get -y upgrade \
 && apt-get install -y --no-install-recommends libpq-dev ca-certificates \
 && update-ca-certificates \
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
