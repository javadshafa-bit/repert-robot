# گام ۵ — سوییچ پورت‌های ۸۰/۴۴۳ از nginx سایت simurgh به Traefik

⚠️ تنها مرحله‌ای که روی سایت در حال کار `pakhshemoon.ir` اثر می‌گذارد.
قطعی مورد انتظار: حدود ۳۰ ثانیه تا ۲ دقیقه.

## نکته کلیدی طراحی
Traefik به **پورت ۴۴۳** خود nginx سایت simurgh وصل می‌شود، نه ۸۰.
دلیل: بلاک `listen 80` در کانفیگ nginx یک `return 301 https://...` دارد؛
اگر Traefik به ۸۰ وصل شود حلقه‌ی بی‌نهایت ریدایرکت می‌سازد.
با این روش کانفیگ nginx سایت simurgh **اصلاً تغییر نمی‌کند** — فقط
بایند پورت روی هاست حذف و کانتینر به شبکه `web` اضافه می‌شود.

---

## ۱. بکاپ (قبل از هر تغییر)

```bash
cd ~/simorgh
cp docker-compose.prod.yml docker-compose.prod.yml.bak-$(date +%F-%H%M)
ls -la docker-compose.prod.yml*
```

## ۲. اضافه کردن فایل روتینگ به Traefik (بی‌خطر، هنوز فعال نمی‌شود)

```bash
cp ~/stacks/repert-robot/docker/traefik/dynamic/simurgh.yml ~/stacks/traefik/dynamic/
sleep 3
docker logs traefik --tail 10
```

## ۳. ویرایش compose سایت simurgh

حذف بایند پورت‌ها و اضافه کردن شبکه `web`:

```bash
cd ~/simorgh
python3 - <<'EOF'
import re
p = 'docker-compose.prod.yml'
s = open(p).read()

# حذف بایند "80:80" و "443:443" فقط در بلاک nginx
s = s.replace('''    ports:
      - "80:80"
      - "443:443"
''', '')

# اتصال nginx به شبکه مشترک
s = s.replace('    networks: [simurgh]\n\nvolumes:',
              '    networks: [simurgh, web]\n\nvolumes:')

# تعریف شبکه خارجی web
if 'web:\n    external: true' not in s:
    s = s.rstrip() + '\n  web:\n    external: true\n'

open(p,'w').write(s)
EOF

# بررسی نتیجه
grep -n -A 22 '^  nginx:' docker-compose.prod.yml
tail -12 docker-compose.prod.yml
```

## ۴. سوییچ (لحظه‌ی قطعی)

```bash
cd ~/simorgh
docker compose -f docker-compose.prod.yml --env-file .env.production up -d nginx

cd ~/stacks/traefik
sed -i 's/^HTTP_PORT=.*/HTTP_PORT=80/; s/^HTTPS_PORT=.*/HTTPS_PORT=443/' .env
cat .env
docker compose up -d --force-recreate
sleep 8
docker logs traefik --tail 20
```

## ۵. تست فوری

```bash
curl -sI https://pakhshemoon.ir/           | head -3
curl -sI http://pakhshemoon.ir/            | head -4   # باید 301 به https بدهد
curl -sI --resolve a1-studio.ir:443:127.0.0.1 https://a1-studio.ir/ | head -3
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
```

## ۶. هوک تمدید گواهی

```bash
sudo cp ~/stacks/repert-robot/docker/traefik/reload-traefik-hook.sh \
        /etc/letsencrypt/renewal-hooks/deploy/reload-traefik.sh
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-traefik.sh
sudo certbot renew --dry-run
```

---

## پلن بازگشت (اگر چیزی خراب شد)

```bash
cd ~/stacks/traefik
sed -i 's/^HTTP_PORT=.*/HTTP_PORT=8080/; s/^HTTPS_PORT=.*/HTTPS_PORT=8443/' .env
docker compose up -d --force-recreate

cd ~/simorgh
cp docker-compose.prod.yml.bak-* docker-compose.prod.yml
docker compose -f docker-compose.prod.yml --env-file .env.production up -d nginx

curl -sI https://pakhshemoon.ir/ | head -3
```

کل بازگشت زیر یک دقیقه طول می‌کشد.
