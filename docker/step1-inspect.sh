#!/usr/bin/env bash
# گام ۱ — بررسی وضعیت فعلی سرور قبل از هر تغییر
# فقط می‌خواند، هیچ چیزی را تغییر نمی‌دهد.
# اجرا روی سرور:  bash step1-inspect.sh

echo "===== ۱. محل و محتوای پروژه simurgh ====="
ls -la ~/simorgh 2>/dev/null || ls -la ~/simurgh 2>/dev/null

echo -e "\n===== ۲. فایل compose سایت simurgh ====="
find ~ -maxdepth 3 -name "docker-compose*.y*ml" 2>/dev/null

echo -e "\n===== ۳. بخش nginx در compose (بایند پورت‌ها و volumeها) ====="
for f in $(find ~ -maxdepth 3 -name "docker-compose*.y*ml" 2>/dev/null); do
  echo "--- $f ---"
  grep -n -A 25 -E '^\s*nginx:' "$f"
done

echo -e "\n===== ۴. کانفیگ nginx داخل کانتینر (server_name ها) ====="
docker exec simurgh-prod-nginx sh -c 'ls -la /etc/nginx/conf.d/ 2>/dev/null; cat /etc/nginx/conf.d/*.conf 2>/dev/null' | head -80

echo -e "\n===== ۵. server_name های تنظیم‌شده ====="
docker exec simurgh-prod-nginx sh -c 'grep -rh server_name /etc/nginx/ 2>/dev/null' | sort -u

echo -e "\n===== ۶. محل گواهی‌های SSL فعلی ====="
docker inspect simurgh-prod-nginx --format '{{range .Mounts}}{{.Source}} -> {{.Destination}}{{println}}{{end}}'
ls -la /etc/letsencrypt/live/ 2>/dev/null
sudo ls -la /etc/letsencrypt/live/ 2>/dev/null

echo -e "\n===== ۷. گواهی‌های certbot موجود ====="
sudo certbot certificates 2>/dev/null || certbot certificates 2>/dev/null

echo -e "\n===== ۸. IP عمومی سرور ====="
curl -s -4 ifconfig.me 2>/dev/null; echo
ip -4 addr show scope global | grep inet

echo -e "\n===== ۹. رزولوشن DNS دامنه‌ها ====="
for d in pakhshemoon.ir a1-studio.ir; do
  echo -n "$d -> "; getent hosts "$d" | awk '{print $1}' | tr '\n' ' '; echo
done

echo -e "\n===== ۱۰. فضای دیسک و وضعیت داکر ====="
df -h /
docker system df
