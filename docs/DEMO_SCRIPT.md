# Portfolio demonstration script

Target length: 4–5 minutes. Use seeded accounts and reset the demo database with `php artisan migrate:fresh --seed` before recording.

## 1. Customer journey — 75 seconds

1. Open the mobile app in dark mode and switch between English and Khmer.
2. Sign in as `customer@queuecare.test` with `password`.
3. Find Central Clinic, show live waits, choose General Consultation, and join the queue.
4. Open the digital ticket and point out the queue position, estimated wait, live progress, and QR arrival action.

## 2. Counter workflow — 75 seconds

1. Sign in to the web portal as `staff@queuecare.test`.
2. Select Counter 01, call the next ticket, start service, and complete it.
3. Show skip/restore and transfer controls, explaining that the API validates every transition and writes audit history.

## 3. Manager controls — 75 seconds

1. Sign in as `manager@queuecare.test` and show the live dashboard.
2. Open branch setup and briefly show services, counters, and operating hours.
3. Open Reports, choose a date range, compare branches and staff, then download CSV and PDF.
4. Switch web theme and language, and navigate with the keyboard to demonstrate visible focus states.

## 4. Engineering proof — 45 seconds

1. Show the monorepo structure and API versioned routes.
2. Show `ticket_status_history`, the priority audit fields, and the report tests.
3. End on the passing GitHub Actions workflow and the public display route.
