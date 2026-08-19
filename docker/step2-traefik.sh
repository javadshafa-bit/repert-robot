#!/usr/bin/env bash
# گام ۲ — بالا آوردن Traefik روی پورت‌های موقت (هیچ تغییری روی سایت فعلی نمی‌دهد)
set -e

STACK=~/stacks/traefik

echo "===== ۰. بررسی نحوه تمدید گواهی‌های certbot ====="
sudo grep -E '^(authenticator|installer)' /etc/letsencrypt/renewal/*.conf

echo -e "\n===== ۱. ساخت شبکه مشترک web ====="
docker network inspect web >/dev/null 2>&1 && echo "شبکه web از قبل وجود دارد" \
  || docker network create web

echo -e "\n===== ۲. ساخت پوشه استک ====="
mkdir -p "$STACK/dynamic" "$STACK/logs"
echo "پوشه آماده: $STACK"
echo "→ حالا فایل‌های traefik.yml، dynamic/tls.yml، docker-compose.yml و .env را در این پوشه قرار دهید"

echo -e "\n===== ۳. آزاد کردن فضای build cache (اختیاری، ~۱۶GB) ====="
echo "برای اجرا:  docker builder prune -f"

echo -e "\n(بعد از قرار دادن فایل‌ها، اجرا کنید:)"
echo "  cd $STACK && docker compose up -d && docker compose logs -f traefik"
