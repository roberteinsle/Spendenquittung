# Zuwendungsbestätigung

Eine Webanwendung für gemeinnützige Organisationen zur digitalen Erstellung von steuerlich anerkannten Zuwendungsbestätigungen (Spendenquittungen) nach deutschem Recht.

Entwickelt für die **Dietrich F. Liedelt Stiftung**, aber frei für andere Stiftungen, Vereine und gemeinnützige Organisationen nutzbar.

---

## Was kann die App?

- **Spenderdaten verwalten** – Stammdaten mit automatischer Spendernummer, Anrede, Adresse, E-Mail
- **Spenden erfassen** – Betrag, Datum, Förderungszweck, Ankreuzfeld (Vermögensstock / unmittelbare Verwendung)
- **Farbiges PDF erzeugen** – A4-Zuwendungsbestätigung mit Logo, Unterschrift und individuellem Förderungstext, konform zum BMF-Muster
- **Excel-Import** – Spenderliste aus Excel importieren, bestehende Spender automatisch erkennen (Fuzzy-Matching)
- **Bescheinigungsnummern** – werden automatisch vergeben (Format `YYxxxx`, z.B. `264711`)
- **Betrag in Worten** – wird automatisch auf Deutsch ausgeschrieben
- **Mehrere Förderungszwecke** – konfigurierbar mit vollem juristischen Text je Zweck
- **Versandprotokoll** – Druck, E-Mail und Postversand werden protokolliert

---

## Tech-Stack

| Komponente | Technologie |
|---|---|
| Framework | Laravel 13 (PHP 8.3) |
| Admin-Oberfläche | Filament 4 |
| Datenbank | PostgreSQL |
| PDF-Erzeugung | Gotenberg 8 (Chromium-basiert) |
| Excel-Import | maatwebsite/excel |
| Containerisierung | Docker (serversideup/php) |
| CI/CD | GitHub Actions → GHCR |
| Betrieb | Coolify (selbst gehostet) |

---

## Voraussetzungen

- Docker und Docker Compose
- Ein GitHub-Account (für das Image-Registry)
- Eine Coolify-Instanz **oder** ein beliebiger Server mit Docker

Für die lokale Entwicklung genügt **PHP 8.3**, **Composer**, **Node.js 22** und **SQLite**.

---

## Lokale Entwicklung

```bash
# Repository klonen
git clone https://github.com/roberteinsle/Spendenquittung.git
cd Spendenquittung

# Abhängigkeiten installieren
composer install
npm install

# Umgebung einrichten
cp .env.example .env
php artisan key:generate

# Datenbank anlegen und befüllen (SQLite, keine Installation nötig)
php artisan migrate --seed

# Assets bauen
npm run build

# Entwicklungsserver starten
php artisan serve
```

Die App ist dann unter `http://localhost:8000` erreichbar.

**Standard-Login nach dem Seeding:**
- andrea@example.com / password
- jasmin@example.com / password
- admin@example.com / password

> Passwörter unbedingt vor dem ersten produktiven Einsatz ändern.

---

## Deployment mit Docker (Coolify)

### 1. Image bauen und pushen (GitHub Actions)

Das mitgelieferte Workflow-File [.github/workflows/build.yml](.github/workflows/build.yml) baut bei jedem Push auf `main` automatisch ein Docker-Image und legt es in der GitHub Container Registry (GHCR) ab.

Das Image wird unter `ghcr.io/DEIN-GITHUB-USERNAME/spendenquittung:latest` veröffentlicht.

Damit Coolify das Image ziehen kann, muss es **öffentlich** sein:
> GitHub → Packages → spendenquittung → Package settings → Change visibility → Public

Alternativ kann Coolify mit einem GitHub PAT (`read:packages`) als private Registry konfiguriert werden.

### 2. Coolify einrichten

1. In Coolify: **New Resource → Docker Compose (Empty)**
2. Inhalt von [deploy/docker-compose.coolify.yml](deploy/docker-compose.coolify.yml) einfügen
3. Folgende Umgebungsvariablen in Coolify setzen:

| Variable | Beschreibung |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` |
| `APP_URL` | URL der App, z.B. `https://quittungen.meinverein.de` |
| `DB_DATABASE` | Datenbankname |
| `DB_USERNAME` | Datenbankbenutzer |
| `DB_PASSWORD` | Sicheres Passwort |
| `APP_TAG` | Image-Tag, Standard: `latest` |

4. Deployment starten

Beim ersten Start werden Datenbankmigrationen und das Seeding automatisch ausgeführt.

---

## Anpassung an die eigene Organisation

### Stiftungs-/Vereinsdaten

Nach dem ersten Login unter **Einstellungen** hinterlegen:

- Name der Organisation
- Adresse
- Bankverbindung (IBAN, BIC, Geldinstitut)
- Freistellungsbescheid-Daten (Finanzamt, Steuernummer, Datum, Veranlagungszeitraum)
- Rechtsform (öffentlich-rechtlich / privatrechtlich)

### Logo und Unterschrift

Ebenfalls unter **Einstellungen**:

- **Logo** – PNG oder JPG, mind. 300 dpi bei Zielgröße
- **Unterschrift** – PNG mit transparentem Hintergrund empfohlen
- Optional: Herzfigur / Signet (je nach Gestaltung der Vorlage)

### Förderungszwecke

Unter **Förderungszwecke** können beliebig viele Zwecke mit dem vollständigen juristischen Text angelegt werden. Je Spende wird der passende Zweck ausgewählt und erscheint im PDF.

### PDF-Vorlage anpassen

Die Vorlage liegt unter [resources/views/pdf/zuwendungsbestaetigung.blade.php](resources/views/pdf/zuwendungsbestaetigung.blade.php). Sie ist ein normales HTML/CSS-Dokument und kann frei gestaltet werden. Gotenberg rendert es über Chromium zu einem druckfähigen A4-PDF.

---

## Sicherheitshinweise

- Die App ist für den **internen Betrieb** ausgelegt und sollte nicht öffentlich erreichbar sein.
- Empfohlen wird der Betrieb hinter **Tailscale** (VPN) oder einem anderen privaten Netzwerk.
- Die mitgelieferte `TailscaleOnly`-Middleware blockiert alle Anfragen von außerhalb des Tailscale-Netzwerks, wenn `TAILSCALE_ONLY=true` gesetzt ist.
- Credentials gehören **niemals ins Repository** – ausschließlich über Umgebungsvariablen konfigurieren.
- Regelmäßige Backups der Datenbank und des Storage-Volumes sind Pflicht.

---

## Datenschutz (DSGVO)

- Kein Tracking, keine externen Dienste außer dem konfigurierten SMTP-Server
- PDF-Erzeugung läuft vollständig lokal (Gotenberg)
- Alle Spenderdaten verbleiben auf dem eigenen Server
- Hosting sollte in Deutschland oder der EU erfolgen

---

## Lizenz

MIT – kostenlos nutzbar, auch für kommerzielle Organisationen.

---

## Mitwirken

Pull Requests und Issues sind willkommen. Bitte beachte beim Melden von Sicherheitsproblemen, keine sensiblen Daten in Issues zu veröffentlichen.
