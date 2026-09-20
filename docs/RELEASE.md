# Release runbook

QueueCare deploys as three independently configured applications. The API owns all authorization, queue order, status transitions, reporting, and audit history.

## Required production services

- MySQL 8.4 database in the same region as the Laravel API.
- HTTPS Laravel host with a persistent queue worker and a one-minute scheduler.
- Vercel project rooted at `apps/web`.
- Expo EAS projects for iOS and Android, connected to Firebase/APNs push credentials.

## Environment checklist

1. Copy each `.env.example` into the target platform's secret manager. Never upload local `.env` files.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, database credentials, allowed web origin, and the Expo push settings on the API.
3. Set `NEXT_PUBLIC_API_BASE_URL=https://api.example.com/api/v1` in Vercel.
4. Set `EXPO_PUBLIC_API_URL=https://api.example.com/api/v1` and the EAS project ID for mobile builds.
5. Run `php artisan migrate --force` and `php artisan config:cache` during the API release.
6. Run the Laravel scheduler every minute and keep `php artisan queue:work --tries=3` supervised.
7. Verify `/api/v1/health`, web sign-in, one customer ticket journey, one counter completion, one report export, and one physical-device push notification.

## Release commands

```bash
cd apps/api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize

cd ../web
npm ci
npm run build

cd ../mobile
npm ci
npm run typecheck
npx eas build --platform all
```

GitHub Actions repeats the API suite, Pint, web build, and both TypeScript checks on every pull request and push to `main`.

## Rollback

Redeploy the previous web and API artifacts. Roll back a migration only when its `down` method is confirmed safe against production data. Keep the new database schema when an application rollback remains compatible, because queue and audit history must not be discarded.
