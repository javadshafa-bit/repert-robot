#!/bin/sh
# نصب در: /etc/letsencrypt/renewal-hooks/deploy/reload-traefik.sh  (chmod +x)
#
# Traefik فایل‌های کانفیگ dynamic را watch می‌کند، ولی تغییر محتوای خود
# فایل‌های گواهی را لزوماً تشخیص نمی‌دهد. بعد از هر تمدید موفق certbot،
# این هوک Traefik را ری‌استارت می‌کند تا گواهی جدید بارگذاری شود.

docker restart traefik >/dev/null 2>&1 || true
logger -t certbot-hook "traefik restarted after certificate renewal"
