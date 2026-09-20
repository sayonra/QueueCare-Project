# QueueCare

QueueCare lets customers reserve a queue number remotely, follow their position, and know when to approach a counter. This repository contains the customer mobile app, the responsive staff/admin portal, and the Laravel API in one folder.

## Repository layout

| Path | Purpose |
| --- | --- |
| `apps/mobile` | React Native app built with Expo and TypeScript |
| `apps/web` | Next.js App Router, TSX, and Tailwind CSS portal |
| `apps/api` | Laravel REST API; MySQL is the production target |
| `docs` | Product scope, design system, architecture, API contract, and sprint plan |

## Run locally

Requirements: Node.js 22 LTS (see `.nvmrc`), npm, PHP 8.3+, Composer, and Docker Desktop. Use separate VS Code terminals.

Copy `.env.example` to `.env` at the repository root and `apps/api/.env.example` to `apps/api/.env`. Set both database passwords in the root file, and set `DB_PASSWORD` in the API file to the same `QUEUECARE_DB_PASSWORD` value. Then start MySQL and migrate:

```bash
docker compose up -d --wait mysql
cd apps/api && composer install && php artisan key:generate && php artisan migrate --seed
```

The database is bound to `127.0.0.1:3307`. The first start also creates `queuecare_test`. Docker keeps the database in a named volume. Restart with `docker compose up -d mysql` and stop with `docker compose down`.

Install the web and mobile dependencies with `npm ci --prefix apps/web` and `npm ci --prefix apps/mobile`. Start each app in a separate terminal:

```bash
npm run dev:web
npm run dev:mobile
cd apps/api && php artisan serve
```

The web portal runs at `http://localhost:3000`; Laravel uses `http://localhost:8000`. `GET http://localhost:8000/api/v1/health` confirms the API can reach MySQL. Expo prints a QR code for a device or simulator. A phone must reach the API through the Mac's LAN address rather than `localhost`.

For push delivery, configure an EAS project ID and use an Expo development build on a physical device. Process the notification outbox from the API with `php artisan notifications:retry`; production should schedule that command every minute. Branch check-in QR signs encode `queuecare://branch/{branch_id}`.

Do not commit `.env` files. If credentials change after the first MySQL start, update the existing database user or deliberately recreate the named volume; init scripts run only for a new volume.

## Current stage

Sprint 5 is complete. Managers can inspect date-ranged wait and service metrics, compare branches and staff, and download real CSV/PDF reports. The web portal adds an explicit dark theme, English/Khmer controls, visible keyboard focus, and accessible report tables; mobile follows the device theme and provides bilingual navigation and core customer screens. Historical seed data supports a useful first-run demonstration, while CI validates the Laravel suite, formatting, web production build, and both TypeScript clients. See [the product scope](docs/PRODUCT.md), [Modular Bento design direction](docs/DESIGN.md), [architecture](docs/ARCHITECTURE.md), [sprint plan](docs/SPRINTS.md), [release runbook](docs/RELEASE.md), and [demo script](docs/DEMO_SCRIPT.md).

### Demonstration accounts

All seeded accounts use the password `password`.

| Role | Email |
| --- | --- |
| Super admin | `admin@queuecare.test` |
| Branch manager | `manager@queuecare.test` |
| Counter staff | `staff@queuecare.test` |
| Customer | `customer@queuecare.test` |

The [QueueCare Notion project hub](https://www.notion.so/QueueCare-Project-Hub-3de6608cae7080608f21ca56f983c5f9) tracks the sprint backlog with table and status board views.

## Decisions

- React Native with Expo replaces the Flutter recommendation in the concept brief, as requested.
- Web uses Next.js, TypeScript/TSX, and Tailwind CSS. shadcn/ui can be added when the first portal components are built.
- Concept 11, Modular Bento, is the approved visual direction across mobile, web, and public display surfaces.
- Laravel owns ticket state and permissions; clients never calculate authoritative queue order.
- MySQL 8.4 is the local and CI database; PHPUnit uses SQLite in memory for fast feature tests.
