#!/bin/sh
set -e
# Render fournit le port d'écoute dans $PORT
sed -i "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf
# Variables d'environnement transmises à PHP
# Disque persistant Render (monté sur /var/data) : dossier des photos et documents
if [ -d /var/data ]; then mkdir -p /var/data/ct/public /var/data/ct/private && chown -R www-data:www-data /var/data/ct && chmod 750 /var/data/ct; fi
exec apache2-foreground
