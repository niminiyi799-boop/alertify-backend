#!/bin/sh
set -e

PORT="${PORT:-8000}"

echo "Starting PHP server on 0.0.0.0:${PORT}"
exec php -S "0.0.0.0:${PORT}" -t public
