web: vendor/bin/heroku-php-apache2 public/
worker: public/php artisan horizon
scheduler: php -d memory_limit=512M artisan schedule:daemon
