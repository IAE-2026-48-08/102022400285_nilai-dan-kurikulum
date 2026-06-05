#!/bin/sh
set -e

# Clear cached bootstrap files to avoid conflicts between host cache and container environment
echo "Clearing cached bootstrap files..."
rm -f bootstrap/cache/config.php \
      bootstrap/cache/routes.php \
      bootstrap/cache/services.php \
      bootstrap/cache/packages.php \
      bootstrap/cache/events.php

# Wait for database if configured
if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ]; then
    echo "Checking database connection to $DB_HOST..."
    php -r "
    \$start = time();
    while (true) {
        try {
            \$pdo = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [
                PDO::ATTR_TIMEOUT => 5
            ]);
            echo 'Database is ready!\n';
            break;
        } catch (PDOException \$e) {
            if (time() - \$start > 90) {
                echo 'Error: Database connection timed out after 90 seconds.\n';
                exit(1);
            }
            echo 'Waiting for database connection to be established...\n';
            sleep(2);
        }
    }
    "
fi

# Run migrations if app container and in production or if explicitly asked
if [ "$1" = "php-fpm" ]; then
    echo "Discovering package manifest..."
    php artisan package:discover --ansi

    echo "Running database migrations..."
    php artisan migrate --force

    # Cache configuration, routes, and views if environment is production/staging
    if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
        echo "Caching Laravel bootstrap files for production..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan event:cache
    else
        echo "Clearing Laravel caches for development..."
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear
    fi
fi

# Execute the main command
echo "Executing: $@"
exec "$@"
