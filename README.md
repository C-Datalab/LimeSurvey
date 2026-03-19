# LimeSurvey — Self-Hosted Docker Setup

A production-ready self-hosted [LimeSurvey](https://www.limesurvey.org/) stack using Docker Compose.
All data stays on your own server — nothing is sent to external services.

## Stack

| Service    | Image                        | Purpose          |
|------------|------------------------------|------------------|
| LimeSurvey | `acspri/limesurvey:latest`   | Survey platform  |
| MariaDB    | `mariadb:10.11`              | Database         |

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

Wait for the log line: `AH00558: apache2: Could not reliably determine...` — that means it's ready.

### 4. Access

| Interface   | URL                                     |
|-------------|-----------------------------------------|
| Survey list | `http://localhost:9514`                 |
| Admin panel | `http://localhost:9514/index.php/admin` |

Log in with the `ADMIN_USER` and `ADMIN_PASSWORD` from your `.env`.

---

## User Management

LimeSurvey does not have public self-signup. The admin creates all user accounts
manually via **Administration → User Management → Add user**, setting credentials
directly. No email infrastructure is required for internal use.

Survey respondents do not need accounts — surveys are accessed via URL only.

---

## Production Deployment (Linux Server)

Update `BASE_URL` in `.env` to your server's address:

```env
BASE_URL=https://forms.yourcompany.com
```

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

## Custom Theme

The `theme/` directory is a pre-built extension of the Fruity TwentyThree theme, bind-mounted
directly into the container. Custom JavaScript lives in `theme/scripts/custom.js`.

### Activation (one-time, after first boot)

1. Log in to the admin panel
2. Go to **Configuration → Themes**
3. Click **Install** on the custom theme
4. Go to **Configuration → Global Settings → General** and set it as the default theme

### Adding future customisations

Edit `theme/scripts/custom.js` in this repository and restart the stack —
changes are reflected immediately since the directory is bind-mounted.

---

## Dynamic List Questions

For questions where respondents need to add multiple items (e.g. a list of URLs),
use the native **Input on demand** question type — no custom JavaScript or CSS
class required. LimeSurvey handles this natively with no item cap.

---

## Email Validation

The custom theme includes client-side email validation with inline error styling.
It applies to any Multiple Short Text question configured as follows:

**One-time setup per question:**

1. In the question editor, open the **Display** tab and set **CSS class** to: `contact-info`
2. On the email subquestion row, set the **Code** field (not the label) to: `email`

The validator fires on blur. If the value is not a valid email address, the field gains a red
border and an error message appears below it. Both clear as soon as a valid address is entered.

> **Note:** The Code field is the short identifier in the subquestion row (e.g. `SQ001`, `SQ002`
> by default). Change it to `email` so the JavaScript can locate it reliably regardless of
> survey or question ID.

---

## Question Templates

Reusable question templates are stored in `question-templates/` as `.lsq` files.

**To import into a survey:** Add question → Import → select the `.lsq` file.

**To export a new template:** In the survey structure, click `...` on the question → Export,
then save the `.lsq` file into `question-templates/` and commit it to the repo.

| File                | Description                                      |
|---------------------|--------------------------------------------------|
| `contact-info.lsq`  | Name, Email (validated), Location (optional)     |

---

## Data & Backups

All persistent data lives in `./data/` (gitignored):

```
data/
  mariadb/       # Database files
  limesurvey/    # Uploaded files and assets
```

To back up, either stop the stack and copy the `data/` directory, or use `mysqldump`:

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
