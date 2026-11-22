#!/bin/sh
set -eu
# A failed export must leave the previous snapshot stale, never fake healthy zeros.
while :; do
  php /var/www/html/artisan ops:metrics:export --output=/metrics/procurepilot.prom --minutes="${OPS_EXPORT_WINDOW_MINUTES:-5}" || true
  sleep "${OPS_EXPORT_INTERVAL_SECONDS:-15}"
done
