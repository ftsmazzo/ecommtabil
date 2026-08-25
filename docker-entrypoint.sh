#!/bin/sh
set -e
php /var/www/html/scripts/php/migrate.php
exec apache2-foreground
