# LimeSurvey — Self-Hosted Docker Setup

A production-ready self-hosted [LimeSurvey](https://www.limesurvey.org/) stack using Docker Compose.
All data stays on your own server — nothing is sent to external services.

## Stack

| Service    | Image                        | Purpose                          |
|------------|------------------------------|----------------------------------|
| LimeSurvey | `acspri/limesurvey:latest`   | Survey platform (PHP)            |
| MariaDB    | `mariadb:10.11`              | Database                         |
| Mailpit    | `axllent/mailpit:latest`     | Local email catcher (dev/test)   |

---

## Quick Start

### 1. Prerequisites

- Docker Desktop (Mac/Windows) or Docker Engine + Docker Compose v2 (Linux server)
- Git

### 2. Clone and configure

```bash
git clone <your-repo-url>
cd limesurvey
cp .env.example .env
```

Edit `.env` and set at minimum:

- `BASE_URL` — the full URL where LimeSurvey will be accessed, e.g. `https://forms.yourcompany.com`
- `ADMIN_PASSWORD` — a strong password for the admin account
- `ADMIN_EMAIL` — the admin email address
- `DB_PASSWORD` and `DB_ROOT_PASSWORD` — strong random strings

### 3. Start

```bash
bash limesurvey.sh
```

Or manually:

```bash
docker compose up -d
docker compose logs -f limesurvey
```

Wait for the log line: `AH00558: apache2: Could not reliably determine...` — that means it's ready.

### 4. Access

| Interface        | URL                                    |
|------------------|----------------------------------------|
| Survey list      | `http://localhost:9514`                |
| Admin panel      | `http://localhost:9514/index.php/admin`|
| Mailpit (dev)    | `http://localhost:8027`                |

Log in with the `ADMIN_USER` and `ADMIN_PASSWORD` from your `.env`.

---

## Production Deployment (Linux Server)

On a server, replace the Mailpit SMTP settings in `.env` with your real mail provider:

```env
BASE_URL=https://forms.yourcompany.com
SMTP_HOST=smtp.yourprovider.com
SMTP_PORT=587
SMTP_FROM=noreply@yourcompany.com
```

Mailpit is still started but unused — you can remove it from `docker-compose.yml` entirely if preferred.

Put Nginx or Caddy in front of LimeSurvey for HTTPS. A minimal Caddy example:

```
forms.yourcompany.com {
    reverse_proxy localhost:9514
}
```

---

## Setting File Permissions (first boot on Linux)

If LimeSurvey fails to start with permission errors on the upload directory:

```bash
chmod -R 777 ./data/limesurvey
```

---

## Custom Theme & JavaScript

The `theme/` directory contains a pre-built extension of the Fruity theme (`fruity_custom`)
that is mounted directly into the container. It includes a `custom.js` that adds a dynamic
**"Add another item"** button to Multiple Short Text questions.

The theme files are always present in the container — the only manual step is
activating it once after first boot.

### Activation (one-time, after first boot)

1. Log in to the admin panel
2. Go to **Configuration → Themes**
3. You will see **Fruity Custom** already listed — click **Install**
4. Go to **Configuration → Global Settings → General** and set the default theme to **Fruity Custom**

That's it. The JavaScript is live for all surveys.

### Using the dynamic list on a question

1. Create a **Multiple Short Text** question
2. Add sub-questions for the maximum number of items you expect (e.g. 10)
3. In the question editor, go to the **Display** tab
4. In the **CSS class** field, enter: `dynamic-add-list`
5. Save — the question will now show one field at a time with an **+ Add another item** button

### Updating the JavaScript

Edit `theme/js/custom.js` in this repository and restart the stack —
the change is reflected immediately since the directory is bind-mounted.

---

## Data & Backups

All persistent data lives in `./data/` (gitignored):

```
data/
  mariadb/       # Database files
  limesurvey/    # Uploaded files and assets
```

To back up, either:
- Stop the stack and copy the `data/` directory, or
- Use `mysqldump` for a portable database backup:

```bash
docker compose exec mariadb mysqldump -u limesurvey -p limesurvey > backup.sql
```

---

## Stopping and Restarting

```bash
# Stop without removing data
docker compose down

# Stop and remove all data (destructive — use with caution)
docker compose down -v
rm -rf ./data
```
