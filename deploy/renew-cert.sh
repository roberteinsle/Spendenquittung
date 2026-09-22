#!/bin/bash
# Tailscale-Zertifikat erneuern und Nginx neu laden
# Cron: 0 3 1 * * /opt/spendenquittung/renew-cert.sh

TAILSCALE_DOMAIN="spendenquittung.tail068ba8.ts.net"

tailscale cert "$TAILSCALE_DOMAIN"
cp "/var/lib/tailscale/certs/${TAILSCALE_DOMAIN}.crt" /etc/ssl/tailscale/cert.pem
cp "/var/lib/tailscale/certs/${TAILSCALE_DOMAIN}.key" /etc/ssl/tailscale/key.pem
chmod 640 /etc/ssl/tailscale/key.pem
nginx -s reload
