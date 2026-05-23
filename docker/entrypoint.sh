#!/bin/sh
set -e

echo "🚀 Oriotel — Initializing service..."

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL..."
while ! php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
    sleep 2
done
echo "✅ MySQL is ready."

# Install dependencies if vendor/autoload.php is missing
if [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Dependencies missing (vendor/autoload.php not found)."
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader --no-dev || { echo "❌ Composer install failed"; exit 1; }
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Clear stale cache
echo "⚙️  Clearing stale cache..."
php artisan optimize:clear

echo "📦 Skipping configuration caching for local development..."
# php artisan config:cache
# php artisan route:cache

echo "✅ Service ready — starting PHP-FPM..."
exec php-fpm
