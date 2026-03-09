#!/bin/sh
set -eu

cd /var/www/html

# Seed a local config for fresh clones without introducing a Docker-only config file.
if [ ! -f config/local.neon ] && [ -f config/local.neon.example ]; then
	cp config/local.neon.example config/local.neon
fi

mkdir -p var/tmp var/log
chown -R www-data:www-data var 2>/dev/null || true
chmod -R 0777 var/tmp var/log 2>/dev/null || true

lock_hash_file="vendor/.composer.lock.hash"
current_lock_hash=""
cached_lock_hash=""

if [ -f composer.lock ]; then
	current_lock_hash="$(sha1sum composer.lock | awk '{print $1}')"

	if [ -f "$lock_hash_file" ]; then
		cached_lock_hash="$(cat "$lock_hash_file")"
	fi
fi

if [ ! -f vendor/autoload.php ] || { [ -n "$current_lock_hash" ] && [ "$cached_lock_hash" != "$current_lock_hash" ]; }; then
	composer install --no-interaction --prefer-dist
	mkdir -p vendor

	if [ -n "$current_lock_hash" ]; then
		printf '%s' "$current_lock_hash" > "$lock_hash_file"
	fi
fi

rm -rf var/tmp/cache/nette.configurator

exec php-fpm -F
