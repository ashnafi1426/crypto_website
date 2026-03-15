@echo off
echo Starting Laravel Development Server...
echo.
echo Make sure you have:
echo 1. Composer dependencies installed (composer install)
echo 2. Environment file configured (.env)
echo 3. Database migrated (php artisan migrate)
echo.
echo Starting server on http://127.0.0.1:8000
echo Press Ctrl+C to stop the server
echo.
php artisan serve --host=127.0.0.1 --port=8000