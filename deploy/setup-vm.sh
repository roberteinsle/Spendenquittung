#!/bin/bash
# Setup-Script für Spendenquittung Hetzner VM
# Voraussetzungen: Ubuntu 24.04, Tailscale bereits installiert und eingeloggt
# Domain: spendenquittung.tail068ba8.ts.net

set -euo pipefail

TAILSCALE_DOMAIN="spendenquittung.tail068ba8.ts.net"
APP_DIR="/opt/spendenquittung"

echo "=== 1/5 System aktualisieren ==="
apt-get update -q
apt-get upgrade -y -q

echo "=== 2/5 Docker installieren ==="
apt-get install -y -q ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update -q
apt-get install -y -q docker-ce docker-ce-cli containerd.io docker-compose-plugin
systemctl enable --now docker

echo "=== 3/5 Tailscale HTTPS-Zertifikat ausstellen ==="
# Tailscale muss bereits eingeloggt sein (tailscale up --authkey=...)
tailscale cert "$TAILSCALE_DOMAIN"
mkdir -p /etc/ssl/tailscale
cp "/var/lib/tailscale/certs/${TAILSCALE_DOMAIN}.crt" /etc/ssl/tailscale/cert.pem
cp "/var/lib/tailscale/certs/${TAILSCALE_DOMAIN}.key" /etc/ssl/tailscale/key.pem
chmod 640 /etc/ssl/tailscale/key.pem

echo "=== 4/5 App-Verzeichnis anlegen ==="
mkdir -p "$APP_DIR"
cat > "$APP_DIR/docker-compose.yml" <<'COMPOSE'
services:
  app:
    image: ghcr.io/roberteinsle/spendenquittung:${APP_TAG:-latest}
    pull_policy: always
    ports:
      - "127.0.0.1:8080:8080"
    environment:
      APP_ENV: production
      APP_KEY: ${APP_KEY}
      APP_URL: ${APP_URL}
      APP_DEBUG: "false"
      DB_CONNECTION: pgsql
      DB_HOST: db
      DB_PORT: "5432"
      DB_DATABASE: ${DB_DATABASE}
      DB_USERNAME: ${DB_USERNAME}
      DB_PASSWORD: ${DB_PASSWORD}
      GOTENBERG_URL: http://gotenberg:3000
      TAILSCALE_ONLY: "false"
      SESSION_DRIVER: database
      CACHE_STORE: database
      QUEUE_CONNECTION: database
      AUTORUN_ENABLED: "true"
      AUTORUN_LARAVEL_MIGRATION: "true"
      LOG_CHANNEL: stderr
    volumes:
      - app-storage:/var/www/html/storage
    depends_on:
      - db
      - gotenberg
    restart: unless-stopped

  worker:
    image: ghcr.io/roberteinsle/spendenquittung:${APP_TAG:-latest}
    pull_policy: always
    command: ["php", "artisan", "queue:work", "--tries=3", "--timeout=120", "--sleep=3"]
    environment:
      APP_ENV: production
      APP_KEY: ${APP_KEY}
      APP_URL: ${APP_URL}
      APP_DEBUG: "false"
      DB_CONNECTION: pgsql
      DB_HOST: db
      DB_PORT: "5432"
      DB_DATABASE: ${DB_DATABASE}
      DB_USERNAME: ${DB_USERNAME}
      DB_PASSWORD: ${DB_PASSWORD}
      GOTENBERG_URL: http://gotenberg:3000
      TAILSCALE_ONLY: "false"
      SESSION_DRIVER: database
      CACHE_STORE: database
      QUEUE_CONNECTION: database
      AUTORUN_ENABLED: "false"
      LOG_CHANNEL: stderr
    volumes:
      - app-storage:/var/www/html/storage
    depends_on:
      - db
    restart: unless-stopped

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db-data:/var/lib/postgresql/data
    restart: unless-stopped

  gotenberg:
    image: gotenberg/gotenberg:8
    command:
      - "gotenberg"
      - "--api-timeout=60s"
      - "--chromium-disable-javascript=false"
      - "--chromium-allow-list=file:///.*"
    restart: unless-stopped

volumes:
  app-storage:
  db-data:
COMPOSE

echo "=== 5/5 Nginx als TLS-Terminator installieren ==="
apt-get install -y -q nginx

cat > /etc/nginx/sites-available/spendenquittung <<NGINX
server {
    listen 80;
    server_name ${TAILSCALE_DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name ${TAILSCALE_DOMAIN};

    ssl_certificate     /etc/ssl/tailscale/cert.pem;
    ssl_certificate_key /etc/ssl/tailscale/key.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    client_max_body_size 50M;

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_set_header   Host \$host;
        proxy_set_header   X-Real-IP \$remote_addr;
        proxy_set_header   X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/spendenquittung /etc/nginx/sites-enabled/spendenquittung
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx

echo ""
echo "===================================================="
echo "Setup abgeschlossen."
echo ""
echo "Jetzt noch /opt/spendenquittung/.env anlegen:"
echo ""
echo "  APP_KEY=<php artisan key:generate --show>"
echo "  APP_URL=https://${TAILSCALE_DOMAIN}"
echo "  DB_DATABASE=spendenquittung"
echo "  DB_USERNAME=app"
echo "  DB_PASSWORD=<sicheres-passwort>"
echo ""
echo "Dann starten mit:"
echo "  cd /opt/spendenquittung && docker compose --env-file .env up -d"
echo ""
echo "Zertifikat erneuern (monatlich per Cron empfohlen):"
echo "  tailscale cert ${TAILSCALE_DOMAIN}"
echo "===================================================="
