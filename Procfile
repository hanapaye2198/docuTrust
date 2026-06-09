release: php artisan migrate --force
web: vendor/bin/heroku-php-apache2 public/
worker: php artisan queue:work --queue=documents,notifications,payments,einvoices,blockchain --sleep=3 --tries=3 --max-time=3600
