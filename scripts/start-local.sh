#!/bin/bash
set -eu

cd "$(dirname "$0")/.."

# Optional first argument: the PHP executable you want to use.
php_bin="${1:-}"
if [ -z "$php_bin" ]; then
    for candidate in /Applications/MAMP/bin/php/php*/bin/php /Applications/XAMPP/xamppfiles/bin/php; do
        if [ -x "$candidate" ] && "$candidate" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);'; then
            php_bin="$candidate"
            break
        fi
    done
fi
if [ -z "$php_bin" ]; then
    php_bin="$(command -v php || true)"
fi
if [ -z "$php_bin" ] || [ ! -x "$php_bin" ]; then
    echo 'PHP not found. Pass its full path: bash scripts/start-local.sh /path/to/php' >&2
    exit 1
fi
"$php_bin" -r 'if (PHP_VERSION_ID < 80100 || !extension_loaded("pdo_mysql") || !extension_loaded("curl")) { fwrite(STDERR, "Use PHP 8.1+ with pdo_mysql and curl enabled.\n"); exit(1); }'

# Keep the development server single-process; do not fork worker processes.
unset PHP_CLI_SERVER_WORKERS
echo "Using $php_bin"
echo 'Open http://127.0.0.1:8000/ (keep MySQL running). Stop with Ctrl+C.'
exec "$php_bin" -S 127.0.0.1:8000 -t "$PWD" "$PWD/router.php"
