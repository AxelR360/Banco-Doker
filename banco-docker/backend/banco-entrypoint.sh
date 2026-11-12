#!/bin/sh
set -eu
if [ -z "${BANCO_JWT_SECRET:-}" ]; then
    export BANCO_JWT_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
fi
php /var/www/html/migrate.php
exec docker-php-entrypoint "$@"
