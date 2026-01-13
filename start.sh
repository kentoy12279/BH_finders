#!/usr/bin/env bash
set -e
# Start the built-in PHP server, using Railway's $PORT or default 8080
php -S 0.0.0.0:${PORT:-8080} -t .
