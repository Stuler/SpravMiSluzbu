#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f config/local.neon ] && [ -f config/local.neon.example ]; then
	cp config/local.neon.example config/local.neon
fi

mkdir -p var/tmp var/log
chown -R www-data:www-data var 2>/dev/null || true
chmod -R 0777 var/tmp var/log 2>/dev/null || true

lock_hash_file="vendor/.composer.lock.hash"
current_lock_hash=""
cached_lock_hash=""
project_owner="$(stat -c '%u:%g' composer.json 2>/dev/null || printf 'www-data:www-data')"

if [ -f composer.lock ]; then
	current_lock_hash="$(sha1sum composer.lock | awk '{print $1}')"

	if [ -f "$lock_hash_file" ]; then
		cached_lock_hash="$(cat "$lock_hash_file")"
	fi
fi

if [ ! -f vendor/autoload.php ] || { [ -n "$current_lock_hash" ] && [ "$cached_lock_hash" != "$current_lock_hash" ]; }; then
	composer install --no-interaction --prefer-dist

	if [ -n "$current_lock_hash" ]; then
		mkdir -p vendor
		printf '%s' "$current_lock_hash" > "$lock_hash_file"
	fi

	chown -R "$project_owner" vendor 2>/dev/null || true
fi

rm -rf var/tmp/cache/nette.configurator

exec php-fpm -F
