# اضافه کردن یک سایت جدید به سرور

معماری فعلی: **Traefik** روی پورت ۸۰/۴۴۳، شبکه‌ی مشترک داکر به نام **`web`**،
و هر سایت یک استک کاملاً مستقل. سایت‌ها با **فایل yml** به Traefik معرفی می‌شوند،
نه با لیبل (چون docker provider به‌خاطر ناسازگاری نسخه‌ی API با Docker 29 خاموش است).

---

## خلاصه‌ی سه‌مرحله‌ای

1. رکورد A دامنه را به `94.232.175.215` بده (روی کلادفلر: **DNS only**، نه پروکسی)
2. استک سایت را در `~/stacks/<نام>/` بالا بیاور و کانتینر وب آن را به شبکه `web` وصل کن
3. یک فایل در `~/stacks/traefik/dynamic/<نام>.yml` بساز

گواهی SSL خودکار گرفته می‌شود. ری‌استارت Traefik لازم نیست.

---

## مرحله ۱ — DNS

در کلادفلر برای دامنه‌ی جدید:

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| A | `example.ir` | `94.232.175.215` | DNS only |
| A | `www` | `94.232.175.215` | DNS only |

⚠️ حتماً **DNS only**. اگر پروکسی کلادفلر روشن باشد، چالش HTTP-01 برای صدور
گواهی شکست می‌خورد.

⚠️ نیم‌سرورهای دامنه باید واقعاً کلادفلر باشند. با این دستور چک کن:

```bash
dig +short NS example.ir @1.1.1.1
```

---

## مرحله ۲ — استک سایت

```bash
mkdir -p ~/stacks/mysite && cd ~/stacks/mysite
```

در `docker-compose.yml` سایت، فقط کانتینری که ترافیک وب می‌گیرد را به `web` وصل کن.
دیتابیس و بقیه سرویس‌ها روی شبکه‌ی داخلی بمانند تا از بیرون دسترسی‌پذیر نباشند:

```yaml
services:
  web:
    image: ...
    container_name: mysite-web        # این نام در فایل Traefik استفاده می‌شود
    restart: always
    networks: [internal, web]

  db:
    image: postgres:16-alpine
    restart: always
    volumes:
      - mysite_db:/var/lib/postgresql/data
    networks: [internal]              # فقط داخلی

volumes:
  mysite_db:

networks:
  internal:
    driver: bridge
  web:
    external: true
```

```bash
docker compose up -d
```

---

## مرحله ۳ — معرفی به Traefik

```bash
cat > ~/stacks/traefik/dynamic/mysite.yml <<'EOF'
http:
  routers:
    mysite:
      rule: "Host(`example.ir`) || Host(`www.example.ir`)"
      entryPoints: [websecure]
      service: mysite
      tls:
        certResolver: le        # گواهی خودکار Let's Encrypt

  services:
    mysite:
      loadBalancer:
        servers:
          - url: "http://mysite-web:80"
        passHostHeader: true
EOF
```

Traefik ظرف چند ثانیه فایل را می‌خواند و گواهی را می‌گیرد.

```bash
docker logs traefik --tail 20
curl -sI https://example.ir/ | head -3
```

---

## نکات مهم

**اگر کانتینر پشت، خودش TLS دارد** (مثل nginx سایت simurgh که بلاک `listen 80`
آن ریدایرکت دائمی به https دارد)، به پورت ۴۴۳ آن وصل شو نه ۸۰، وگرنه حلقه‌ی
ریدایرکت می‌سازد:

```yaml
  services:
    mysite:
      loadBalancer:
        servers:
          - url: "https://mysite-web:443"
        passHostHeader: true
        serversTransport: skip-verify

  serversTransports:
    skip-verify:
      insecureSkipVerify: true
```

**اپلیکیشن باید به پروکسی اعتماد کند**، وگرنه آدرس‌ها را http می‌سازد:
- Laravel: `trustProxies(at: '*')` در `bootstrap/app.php`
- Django: `SECURE_PROXY_SSL_HEADER = ('HTTP_X_FORWARDED_PROTO', 'https')`
- Express: `app.set('trust proxy', true)`

**نام کانتینر باید یکتا باشد** — Traefik با همان نام روی شبکه `web` پیدایش می‌کند.

**پورت هاست بایند نکن.** هیچ سایتی نباید `ports: - "80:80"` داشته باشد؛
فقط Traefik صاحب پورت‌های هاست است.

---

## عیب‌یابی

```bash
# روترها و سرویس‌های شناخته‌شده
docker exec traefik traefik healthcheck 2>/dev/null
docker logs traefik --tail 40

# آیا کانتینر روی شبکه web هست؟
docker network inspect web --format '{{range .Containers}}{{.Name}} {{end}}'

# تست بدون DNS
curl -sI --resolve example.ir:443:127.0.0.1 https://example.ir/ | head -3

# وضعیت گواهی‌های خودکار
sudo docker exec traefik cat /letsencrypt/acme.json | python3 -m json.tool | grep -i domain
```

خطای رایج `404 page not found` یعنی هیچ روتری با آن Host مطابقت نکرده —
معمولاً غلط املایی در `rule` یا اینکه فایل yml خطای نحوی دارد.
