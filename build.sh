#!/bin/bash

# LaraKube Professional Builder (Serversideup Edition)
# Compiles the CLI into a standalone PHAR binary using the official CLI foundation

set -e

# Ensure we are in the CLI directory
cd "$(dirname "$0")"

USER_ID=$(id -u)
GROUP_ID=$(id -g)

echo "📦 Optimizing production dependencies..."
./composer install --no-dev --optimize-autoloader --no-interaction

echo "🏗 Compiling LaraKube binary..."
docker run --rm \
    -v "$PWD":/var/www/html \
    -w /var/www/html \
    -e USER_ID=$USER_ID \
    -e GROUP_ID=$GROUP_ID \
    serversideup/php:8.4-cli \
    php -d phar.readonly=0 larakube app:build larakube --build-version=local

echo ""
echo "✅ Build successful!"
echo "📍 Binary location: laravel-k8s-cli/builds/larakube"
echo ""
echo "👉 Next step: Update your Gemini settings.json to point to the new binary."
