# Zuwendungsbestätigung

Eine Webanwendung für gemeinnützige Organisationen zur digitalen Erstellung von steuerlich anerkannten Zuwendungsbestätigungen (Spendenquittungen) nach deutschem Recht.

Entwickelt für die **Dietrich F. Liedelt Stiftung**, aber frei für andere Stiftungen, Vereine und gemeinnützige Organisationen nutzbar.

---

## Was kann die App?

- **Anmeldung per Namensauswahl** – Mitarbeiter klicken auf ihren Namen, ausgewählte Konten sind zusätzlich mit einer PIN geschützt
- **Spenderdaten verwalten** – Stammdaten mit automatischer Spendernummer, Anrede, Adresse, E-Mail
- **Spenden erfassen** – Betrag, Datum, Förderungszweck, Ankreuzfeld (Vermögensstock / unmittelbare Verwendung)
- **Farbiges PDF erzeugen** – A4-Zuwendungsbestätigung mit Logo, Unterschrift und individuellem Förderungstext, konform zum BMF-Muster
- **Excel-Import** – Spenderliste aus Excel importieren, bestehende Spender automatisch erkennen (Fuzzy-Matching)
- **Bescheinigungsnummern** – werden automatisch vergeben (Format `YYxxxx`, z.B. `264711`)
- **Betrag in Worten** – wird automatisch auf Deutsch ausgeschrieben
- **Mehrere Förderungszwecke** – konfigurierbar mit vollem juristischen Text je Zweck
- **E-Mail-Versand** – Bescheinigung als PDF-Anhang, mit passender Anrede (Sie oder Du) und frei konfigurierbarem Text
- **Versandprotokoll** – jeder Zugriff auf ein PDF und jeder E-Mail-Versand wird mit Benutzer, Zeitpunkt und Ergebnis protokolliert

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

Für die lokale Entwicklung genügt **PHP 8.3** (mit den Erweiterungen `intl`, `gd` und `zip`), **Composer**, **Node.js 22** und **SQLite**.

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

**Nach dem Seeding** existiert ein einziges Konto namens *Administrator*. Auf der Anmeldeseite genügt ein Klick darauf – ein Passwort gibt es nicht (siehe [Anmeldung](#anmeldung)).

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
| `TAILSCALE_ONLY` | `true` beschränkt den Zugriff auf das Tailscale-Netz |
| `MAIL_MAILER` | `smtp` für echten Versand; ohne Angabe landen E-Mails nur im Log |
| `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` | SMTP-Zugang |

4. Deployment starten

Beim ersten Start werden Migrationen und Seeder automatisch ausgeführt, sofern `AUTORUN_LARAVEL_MIGRATION_SEED=true` gesetzt ist (in den mitgelieferten Compose-Dateien ist das der Fall). Die Seeder legen die Benutzerkonten, die Förderungszwecke und die Grundeinstellungen an – **ohne sie gibt es kein Konto zum Anmelden**. Sie sind idempotent, ein Neustart überschreibt also nichts.

> Angelegt wird dabei ein einziges Konto namens *Administrator*, und nur solange überhaupt kein Benutzer existiert. Ein gelöschtes Konto kommt beim nächsten Start also nicht zurück.

---

## Neu deployen

Nach einem Push auf `main` baut die GitHub Action ein neues Image. Auf dem Server genügt dann ein Befehl:

```bash
deploy
```

Die Funktion legt [deploy/setup-vm.sh](deploy/setup-vm.sh) in `~/.bashrc` an. Auf einem bereits eingerichteten Server einmalig nachtragen:

```bash
cat >> ~/.bashrc <<'BASHRC'

# spendenquittung-deploy
deploy() (
    set -e
    cd /opt/spendenquittung
    docker compose --env-file .env pull
    docker compose --env-file .env up -d
    docker image prune -f >/dev/null
    docker compose ps --format 'table {{.Service}}\t{{.Status}}'
)

artisan() (
    cd /opt/spendenquittung
    docker compose exec app php artisan "$@"
)

logs() (
    cd /opt/spendenquittung
    docker compose logs -f --tail=100 "$@"
)
BASHRC
source ~/.bashrc
```

Derselbe Block legt zwei weitere Abkürzungen an, die aus **jedem** Verzeichnis funktionieren – `docker compose` selbst findet seine Konfiguration sonst nur im Projektverzeichnis:

```bash
artisan db:seed --force   # artisan im App-Container
artisan tinker
logs worker               # Logs verfolgen, z. B. beim E-Mail-Versand
```

Alle drei laufen in einer Subshell, wechseln das Arbeitsverzeichnis also nicht dauerhaft. `deploy` bricht zusätzlich beim ersten Fehler ab und räumt alte Images weg – sonst läuft die Platte einer kleinen VM mit der Zeit voll.

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

| Bild | Platz im PDF | Mindestens | Empfohlen |
|---|---|---|---|
| Unterschrift | 60 × 15 mm | 709 × 177 px | 1400 × 350 px |
| Logo | 40 × 20 mm | 472 × 236 px | 945 × 472 px |
| Herzfigur | 25 × 18 mm | 295 × 213 px | 591 × 425 px |

Jeweils **PNG mit transparentem Hintergrund**. JPG wird zwar angenommen, bringt aber einen sichtbaren hellen Kasten und Kompressionsartefakte um die feinen Linien einer Unterschrift mit.

Die Bilder werden proportional in den angegebenen Rahmen eingepasst – das Seitenverhältnis bestimmt also, welche der beiden Kanten am Ende begrenzt. Deshalb die Vorlage **eng um die Zeichnung beschneiden**: leerer Rand im Bild kostet Platz im Rahmen und lässt die Unterschrift kleiner wirken.

Die Werte ergeben sich aus der Vorlage ([resources/views/pdf/zuwendungsbestaetigung.blade.php](resources/views/pdf/zuwendungsbestaetigung.blade.php)); wer sie ändert, passt die Größen entsprechend an.

### Anmeldung

Die App ist für den Betrieb in einem privaten Netz gedacht und hat deshalb keine Passwörter. Die Anmeldeseite listet alle Benutzer namentlich auf:

- **Ohne PIN** – ein Klick auf den Namen meldet an
- **Mit PIN** – der Name trägt ein Schloss-Symbol und fragt eine PIN ab (fünf Fehlversuche pro Minute, danach gesperrt)

Benutzer werden unter **Einstellungen → Benutzer** angelegt und bearbeitet. Das PIN-Feld bleibt beim Bearbeiten leer; leer speichern behält die bestehende PIN.

> Diese Bequemlichkeit hat einen Preis: Wer Zugang zum Netz hat, kann sich als beliebiger Mitarbeiter ohne PIN anmelden. Damit ist die Zuordnung im Versandprotokoll („wer hat die Bescheinigung verschickt") nur so verlässlich wie der Netzzugang. Wer das nicht möchte, vergibt für jedes Konto eine PIN.

### Förderungszwecke

Unter **Förderungszwecke** können beliebig viele Zwecke mit dem vollständigen juristischen Text angelegt werden. Je Spende wird der passende Zweck ausgewählt und erscheint im PDF.

### Bescheinigungen erzeugen und ausgeben

Unter **Bescheinigungen** stehen je Eintrag drei Aktionen bereit:

- **PDF erzeugen** – rendert die Bescheinigung über Gotenberg und legt sie ab. Über die Mehrfachauswahl lassen sich auch ganze Stapel auf einmal erzeugen.
- **PDF öffnen** – liefert das fertige PDF im Browser aus.
- **Per E-Mail senden** – schickt die Bescheinigung als PDF-Anhang an den Spender. Nur verfügbar, wenn ein PDF existiert; ohne hinterlegte E-Mail-Adresse ist die Aktion deaktiviert. Auch als Massenaktion, die Bescheinigungen ohne PDF oder ohne Adresse überspringt.

Der Status einer Bescheinigung wandert dabei von *Erfasst* über *PDF erstellt* zu *Gedruckt*; ein erneutes Erzeugen setzt einen bereits erreichten Status nie zurück. Jeder Abruf eines PDFs landet im **Versandprotokoll** unterhalb des Bearbeiten-Formulars.

> Der E-Mail-Versand läuft über die Queue. Es muss also ein Worker laufen (`php artisan queue:work`; im Docker-Compose erledigt das der `worker`-Container). Ohne Worker bleiben die E-Mails liegen und es erscheint kein Protokolleintrag.

Den **Betreff und den Text** der E-Mail legst du unter *Einstellungen → E-Mail-Versand* fest, getrennt für die Sie- und die Du-Form (siehe Haken „Duzen“ beim Spender). Anrede und Grußformel ergänzt die App automatisch. Verfügbare Platzhalter: `:nummer`, `:betrag`, `:datum`, `:jahr`, `:zweck`.

Scheitert ein Versand, versucht es die App zweimal erneut (nach einer und nach fünf Minuten). Erst danach erscheint ein Fehlereintrag mit der Meldung im Versandprotokoll.

Die erzeugten PDFs enthalten personenbezogene Daten und liegen deshalb auf einer **privaten** Storage-Disk (`storage/app/private/bescheinigungen`). Sie sind ausschließlich über die angemeldete Route `/bescheinigungen/{id}/pdf` erreichbar, nie über einen öffentlichen Link. Die Disk lässt sich per `PDF_DISK` umstellen.

### PDF-Vorlage anpassen

Die Vorlage liegt unter [resources/views/pdf/zuwendungsbestaetigung.blade.php](resources/views/pdf/zuwendungsbestaetigung.blade.php). Sie ist ein normales HTML/CSS-Dokument und kann frei gestaltet werden. Gotenberg rendert es über Chromium zu einem druckfähigen A4-PDF.

---

## Sicherheitshinweise

- Die App ist für den **internen Betrieb** ausgelegt und sollte nicht öffentlich erreichbar sein.
- Empfohlen wird der Betrieb hinter **Tailscale** (VPN) oder einem anderen privaten Netzwerk.
- Die mitgelieferte `TailscaleOnly`-Middleware blockiert alle Anfragen von außerhalb des Tailscale-Netzwerks, wenn `TAILSCALE_ONLY=true` gesetzt ist. Sie ist sowohl im `web`-Stack als auch im Filament-Panel registriert.
- Jeder angelegte Benutzer hat vollen Zugriff auf das Panel – es gibt keine Selbstregistrierung, Konten legt ausschließlich ein Administrator an.
- Credentials gehören **niemals ins Repository** – ausschließlich über Umgebungsvariablen konfigurieren.
- Das gilt auch für **Anmelde-PINs, und auch als Testwert**. PINs werden gehasht in der Datenbank abgelegt und ausschließlich über die Oberfläche vergeben.
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
