#!/bin/bash
set -e

PORT="${PORT:-10000}"

sed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/000-default.conf

exec apache2-foreground
