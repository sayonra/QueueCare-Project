# Delivery plan

Two-week sprints are a planning assumption. Re-estimate after Sprint 0. Each item is done when the behavior works end to end, access is checked, and its relevant test or manual verification is recorded.

## Sprint 0 — foundation (complete)

- [x] One repository folder for Expo mobile, Next.js web, Laravel API.
- [x] VS Code workspace settings and setup documentation.
- [x] Product scope, architecture, and initial API contract.
- [x] Record remote join/check-in policy, branch timezone, ticket reset, and group-booking rule in `PRODUCT.md`.
- [x] Configure MySQL 8.4 for local development and CI; verify migrations.
- [x] Add `GET /api/v1/health` and a shared API error format, with feature tests.

## Sprint 1 — identity and branch setup

- [x] Establish Modular Bento tokens and shared mobile/web component foundations.
- [x] Laravel Sanctum authentication and role/branch authorization.
- [x] Branch, service, counter, operating hours, and staff assignment migrations/API.
- [x] Admin CRUD screens for branch configuration.
- [x] Seed one demonstration branch with services, counters, staff, and customer accounts.
- [x] Acceptance: manager can configure one branch; staff sees only assigned counters; unauthorized roles are denied.

## Sprint 2 — customer queue journey

- [x] Mobile branch discovery/list/detail and service selection.
- [x] Join queue with transactional ticket numbering and daily limits.
- [x] Active ticket with server estimate, cancellation, and status timeline.
- [x] Acceptance: customer joins an open service, sees a unique number, and can cancel; closed/full queues reject clearly.

## Sprint 3 — counter workflow and live status (complete)

- [x] Staff responsive counter screen: call, recall, serve, skip, restore, complete, pause.
- [x] Server-side transition rules and `ticket_status_history` for each change.
- [x] Five-second polling, basic admin metrics, and a privacy-safe public display.
- [x] Acceptance: atomic selection prevents two counters from calling the same ticket; customer, staff, manager, and display read one server state.

## Sprint 4 — advanced service flow (complete)

- [x] QR arrival check-in, scheduled appointments, group visits, transfers, and controlled priorities.
- [x] Expo push-token registration, notification outbox, delivery command, and exponential retry handling.
- [x] Acceptance: all priority/transfer changes record actor, reason, old/new values, and time; late arrivals and the two-minute absent grace period are enforced and documented.

## Sprint 5 — reporting and polish (complete)

- [x] Waiting/service duration reports, branch comparison, CSV/PDF export.
- [x] Khmer/English controls, system/mobile and explicit/web dark modes, accessibility, and visual refinement.
- [x] Automated end-to-end checks and multi-branch historical demo data.
- [x] Deployment runbook, CI release gate, and a repeatable portfolio demo video script.

## Decisions and risks to revisit with usage data

- Whether the two-minute called grace period fits each service and branch.
- Whether branches need to override the initial 30-minutes-before/15-minutes-after appointment check-in window.
- How group size changes the service-time model after Phase 2 launches.
- Whether production should continue with Expo Push Service or move to direct FCM/APNs after delivery data is available.
