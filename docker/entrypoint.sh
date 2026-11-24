#!/bin/sh
set -e
# Render fournit le port d'écoute dans $PORT
sed -i "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf
# Variables d'environnement transmises à PHP
# Disque persistant Render : dossier des photos et documents (UPLOAD_DIR, sinon /var/data/ct)
UD="${UPLOAD_DIR:-/var/data/ct}"
if [ -d "$(dirname "$UD")" ]; then mkdir -p "$UD/public" "$UD/private" && chown -R www-data:www-data "$UD" && chmod 750 "$UD"; fi
exec apache2-foreground
