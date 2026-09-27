#!/bin/bash
# Setup-Script für Spendenquittung Hetzner VM
# Voraussetzungen: Ubuntu 24.04, Tailscale bereits installiert und eingeloggt
# Domain: spendenquittung.tail068ba8.ts.net

set -euo pipefail

TAILSCALE_DOMAIN="spendenquittung.tail068ba8.ts.net"
APP_DIR="/opt/spendenquittung"

echo "=== 1/6 System aktualisieren ==="
apt-get update -q
apt-get upgrade -y -q

echo "=== 2/6 Docker installieren ==="
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

echo "=== 3/6 Tailscale HTTPS-Zertifikat ausstellen ==="
# Tailscale muss bereits eingeloggt sein (tailscale up --authkey=...)
tailscale cert "$TAILSCALE_DOMAIN"
mkdir -p /etc/ssl/tailscale
cp "/var/lib/tailscale/certs/${TAILSCALE_DOMAIN}.crt" /etc/ssl/tailscale/cert.pem
cp "/var/lib/tailscale/certs/${TAILSCALE_DOMAIN}.key" /etc/ssl/tailscale/key.pem
chmod 640 /etc/ssl/tailscale/key.pem

echo "=== 4/6 App-Verzeichnis anlegen ==="
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
      APP_NAME: ${APP_NAME:-Spendenquittung}
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
      TAILSCALE_ONLY: ${TAILSCALE_ONLY:-true}
      MAIL_MAILER: ${MAIL_MAILER:-log}
      MAIL_HOST: ${MAIL_HOST:-}
      MAIL_PORT: ${MAIL_PORT:-587}
      MAIL_USERNAME: ${MAIL_USERNAME:-}
      MAIL_PASSWORD: ${MAIL_PASSWORD:-}
      SESSION_DRIVER: database
      CACHE_STORE: database
      QUEUE_CONNECTION: database
      AUTORUN_ENABLED: "true"
      AUTORUN_LARAVEL_MIGRATION: "true"
      # Die Seeder sind idempotent (firstOrCreate), legen aber beim ersten Start
      # Benutzer, Foerderungszwecke und Einstellungen an. Ohne das gibt es kein
      # Konto zum Anmelden.
      AUTORUN_LARAVEL_MIGRATION_SEED: "true"
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
      APP_NAME: ${APP_NAME:-Spendenquittung}
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
      TAILSCALE_ONLY: ${TAILSCALE_ONLY:-true}
      MAIL_MAILER: ${MAIL_MAILER:-log}
      MAIL_HOST: ${MAIL_HOST:-}
      MAIL_PORT: ${MAIL_PORT:-587}
      MAIL_USERNAME: ${MAIL_USERNAME:-}
      MAIL_PASSWORD: ${MAIL_PASSWORD:-}
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

echo "=== 5/6 Deploy-Befehl in die Shell legen ==="
# Idempotent: bei wiederholtem Lauf nicht doppelt anhaengen.
if ! grep -q "spendenquittung-deploy" /root/.bashrc 2>/dev/null; then
cat >> /root/.bashrc <<'BASHRC'

# spendenquittung-deploy
deploy() (
    set -e
    cd /opt/spendenquittung
    docker compose --env-file .env pull
    docker compose --env-file .env up -d
    docker image prune -f >/dev/null
    docker compose ps --format 'table {{.Service}}\t{{.Status}}'
)

# artisan im App-Container, aus jedem Verzeichnis heraus.
#   artisan db:seed --force
#   artisan tinker
artisan() (
    cd /opt/spendenquittung
    docker compose exec app php artisan "$@"
)

# Logs, z. B.: logs worker
logs() (
    cd /opt/spendenquittung
    docker compose logs -f --tail=100 "$@"
)
BASHRC
fi

echo "=== 6/6 Nginx als TLS-Terminator installieren ==="
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
echo "Optional in derselben .env:"
echo "  TAILSCALE_ONLY=false   # nur zum Debuggen, oeffnet die App fuer alle IPs"
echo "  MAIL_MAILER=smtp       # ohne das landen E-Mails nur im Container-Log"
echo "  MAIL_HOST=smtp.beispiel.de"
echo "  MAIL_USERNAME=..."
echo "  MAIL_PASSWORD=..."
echo ""
echo "Dann starten mit:"
echo "  cd /opt/spendenquittung && docker compose --env-file .env up -d"
echo ""
echo "Danach genuegt zum Neudeployen in jeder SSH-Sitzung:"
echo "  deploy"
echo ""
echo "Beim ersten Start werden Migrationen UND Seeder ausgefuehrt. Danach sofort"
echo "die Passwoerter der angelegten Konten aendern (admin@example.com/password):"
echo "  docker compose exec app php artisan tinker"
echo ""
echo "Zertifikat erneuern (monatlich per Cron empfohlen):"
echo "  tailscale cert ${TAILSCALE_DOMAIN}"
echo "===================================================="
