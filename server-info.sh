#!/usr/bin/env bash
# server-info.sh — گزارش کامل مشخصات سخت‌افزاری و نرم‌افزارهای نصب‌شده روی سرور
# اجرا: bash server-info.sh   (یا از راه دور: ssh user@server 'bash -s' < server-info.sh)

echo "===== OS ====="
cat /etc/os-release 2>/dev/null | grep -E '^(NAME|VERSION)='
uname -a

echo -e "\n===== CPU ====="
nproc
lscpu 2>/dev/null | grep -E 'Model name|Socket|Core\(s\)'

echo -e "\n===== RAM ====="
free -h

echo -e "\n===== DISK ====="
df -h --total 2>/dev/null | grep -E 'Filesystem|total'

echo -e "\n===== Virtualization ====="
systemd-detect-virt 2>/dev/null

echo -e "\n===== Docker ====="
if command -v docker &>/dev/null; then
  docker --version
  docker compose version 2>/dev/null || docker-compose --version 2>/dev/null
  echo "-- containers --"
  docker ps -a
  echo "-- images --"
  docker images
  echo "-- networks --"
  docker network ls
else
  echo "docker نصب نیست"
fi

echo -e "\n===== Web servers ====="
for svc in nginx apache2 httpd caddy traefik; do
  command -v "$svc" &>/dev/null && echo "$svc: $($svc -v 2>&1 | head -1)"
done

echo -e "\n===== Runtimes ====="
for cmd in php mysql psql node npm python3 certbot; do
  command -v "$cmd" &>/dev/null && echo "$cmd: $($cmd --version 2>&1 | head -1)"
done

echo -e "\n===== Running services (top 30) ====="
systemctl list-units --type=service --state=running --no-pager 2>/dev/null | head -30

echo -e "\n===== Open ports ====="
ss -tulpn 2>/dev/null || netstat -tulpn 2>/dev/null

echo -e "\n===== Firewall ====="
ufw status 2>/dev/null
firewall-cmd --list-all 2>/dev/null

echo -e "\n===== Existing site configs ====="
ls -la /etc/nginx/sites-enabled/ 2>/dev/null
ls -la /etc/apache2/sites-enabled/ 2>/dev/null
