#!/bin/sh
set -e
# Render fournit le port d'écoute dans $PORT
sed -i "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf
# Variables d'environnement transmises à PHP
exec apache2-foreground
