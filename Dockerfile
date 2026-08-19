# ============================================================
# repert-robot — ایمیج production (چندمرحله‌ای)
# ============================================================

# ---------- مرحله ۱: وابستگی‌های PHP ----------
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
# بدون اجرای اسکریپت‌ها، چون هنوز کل سورس کپی نشده
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction \
      --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative


# ---------- مرحله ۲: build کردن assets ----------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build


# ---------- مرحله ۳: runtime ----------
FROM php:8.3-fpm-alpine AS runtime

# افزونه‌های موردنیاز Laravel + maatwebsite/excel (PhpSpreadsheet)
RUN apk add --no-cache \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        oniguruma-dev libxml2-dev zlib-dev \
        mysql-client bash tzdata \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip intl exif pcntl opcache \
    && apk del .build-deps \
    && rm -rf /tmp/*

ENV TZ=Asia/Tehran
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

WORKDIR /var/www/html

# سورس + وابستگی‌ها + assets ساخته‌شده
COPY --chown=www-data:www-data . .
COPY --from=vendor  --chown=www-data:www-data /app/vendor       ./vendor
COPY --from=assets  --chown=www-data:www-data /app/public/build ./public/build

COPY docker/php/php.ini        /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf       /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php/entrypoint.sh  /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# پوشه‌هایی که باید قابل نوشتن باشند
RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]


# ---------- مرحله ۴: ایمیج nginx (شامل فایل‌های استاتیک) ----------
# با ساختن nginx از همین Dockerfile، فایل‌های public همیشه با کد همگام‌اند
# و نیازی به volume مشترک (که بین بیلدها کهنه می‌شود) نیست.
FROM nginx:1.27-alpine AS web

COPY public                        /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/nginx/default.conf     /etc/nginx/conf.d/default.conf
