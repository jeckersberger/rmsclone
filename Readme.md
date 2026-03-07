# AdamRMS

![GitHub release (latest by date)](https://img.shields.io/github/v/release/adam-rms/adam-rms)
![GitHub repo size](https://img.shields.io/github/repo-size/adam-rms/adam-rms)
![GitHub issues](https://img.shields.io/github/issues/adam-rms/adam-rms)
![GitHub closed issues](https://img.shields.io/github/issues-closed/adam-rms/adam-rms)
![GitHub pull requests](https://img.shields.io/github/issues-pr/adam-rms/adam-rms)
![GitHub closed pull requests](https://img.shields.io/github/issues-pr-closed/adam-rms/adam-rms)
![GitHub](https://img.shields.io/github/license/adam-rms/adam-rms)
![GitHub stars](https://img.shields.io/github/stars/adam-rms/adam-rms)
![GitHub contributors](https://img.shields.io/github/contributors/adam-rms/adam-rms)

An advanced, open-source **Rental Management System** for Theatre, AV & Broadcast equipment. Built with PHP 8.3, Twig, MySQL, and AdminLTE. Deployed via Docker.

Available as a hosted solution or self-hosted via Docker.

## Features

### Equipment & Inventory
- Asset management with categories, types, groups, and custom fields
- Barcode/QR code generation and scanning
- Price management (daily, weekly, custom rates)
- Weight/value tracking and inventory calculations
- Maintenance scheduling and damage reporting

### Project & Job Management
- Full project lifecycle with configurable statuses
- Asset dispatch board (Kanban-style)
- Recurring/repeating projects with automation
- Crew/staff assignment and scheduling
- Calendar integration with ICS export
- File attachments (S3 or local storage)

### Financial Management
- Invoice, quote, and delivery note generation (PDF)
- Sequential document numbering (RE-2026-0001)
- Payment tracking with ledger views
- Dunning/collections with 3-level escalation and CSV export
- Profit dashboard and financial reporting
- EUeR (Einnahmen-Ueberschuss-Rechnung) tax reports
- DATEV export for accounting software
- Kleinunternehmerregelung (small business exemption) support
- Multi-currency support

### German Compliance
- GoBD-compliant invoicing
- DSGVO/GDPR tools (data export, anonymization, retention checks, audit log)
- Tax number and VAT ID support
- Net/tax/gross calculation

### Communication
- Email inbox (IMAP integration) with project assignment
- Email compose and reply with sent mail tracking
- AI-assisted email drafting

### AI Features
- Background automation with confirmation queue
- Email draft generation
- Project summaries and quote assistance
- Damage report description generation
- AI usage dashboard

### Additional
- Multi-instance / multi-tenancy
- Role-based permissions system
- CMS/Wiki for internal documentation
- Client management with history tracking
- Partner/sub-hire management
- Manufacturer directory
- Training modules and certifications
- PWA support for mobile access
- Multi-language (German/English)

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.3, Twig 3.x |
| Database | MySQL 8.0 / MariaDB 5.7+ |
| Frontend | AdminLTE (Bootstrap 4), jQuery |
| PDF | Dompdf (server-side), pdfmake (client-side) |
| Email | SendGrid, Mailgun, Postmark, or SMTP |
| File Storage | AWS S3 or local filesystem |
| Auth | JWT + HybridAuth (social login) |
| Migrations | Phinx |
| Deployment | Docker + Docker Compose |

## Quick Start

```bash
git clone <repo-url> && cd adam-rms
docker compose up -d
```

The app will be available at:

| Service | URL |
|---------|-----|
| Application | http://localhost:8080 |
| phpMyAdmin | http://localhost:8082 |
| Mailpit (email testing) | http://localhost:8083 |

Default database credentials are set in `docker-compose.yml`.

### Environment Variables

Configure via environment variables or `docker-compose.yml`:

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOSTNAME` | Database host | `db` |
| `DB_DATABASE` | Database name | `adamrms` |
| `DB_USERNAME` | Database user | `adamrms` |
| `DB_PASSWORD` | Database password | - |
| `ROOT_URL` | Public URL of the app | `http://localhost:8080` |
| `CONFIG_TIMEZONE` | Timezone | `Europe/Berlin` |
| `CONFIG_EMAILS_PROVIDER` | Email provider (SMTP/SendGrid/Mailgun/Postmark) | `SMTP` |
| `CONFIG_FILES_ENABLED` | Enable file uploads | `true` |
| `LOCAL_STORAGE_PATH` | Local file storage path | `/var/www/storage` |
| `DEV_MODE` | Enable development mode | `false` |

## Project Structure

```
src/
  api/           REST API endpoints
  assets/        Equipment management UI
  business/      Business modules (dunning, reports, DSGVO, etc.)
  clients.twig   Client management
  cms/           Content management system
  common/        Shared code (Config, Auth, Twig extensions)
  cron/          Scheduled tasks
  email/         Email inbox and sent views
  instances/     Multi-tenancy settings
  login/         Authentication
  project/       Project management
  services/      Business logic services (36+)
  static-assets/ JS, CSS, manifest.json, service worker
  training/      Training and certification modules
db/
  migrations/    Phinx database migrations
  seeds/         Seed data
```

## Docker Images

A maintained Docker image is hosted on GitHub Packages as [adam-rms/adam-rms](https://github.com/orgs/adam-rms/packages?repo_name=adam-rms).

Database migrations run automatically on container startup.

## Development

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?ref=main&repo=217888995)

This repo has a configured devcontainer for use with GitHub Codespaces or VSCode. Clone the repo and open in VSCode, then [open in a devcontainer](https://code.visualstudio.com/docs/devcontainers/tutorial).

See [DEVELOPMENT.md](DEVELOPMENT.md) for the full development setup guide.

## License

Licensed under **AGPL-3.0**. When self-hosting, changes to the source code must be kept open source. See [LICENSE](LICENSE) for details.
