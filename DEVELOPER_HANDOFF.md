# CRS Coop Loan Management System - Developer Handoff

Prepared: May 12, 2026

## Current State

This folder contains a Vue 3 frontend, a PHP API backend, and MySQL schema files for the CRS Holdings Employees Credit Cooperative loan management system.

The UI currently runs successfully through Vite at:

```text
http://localhost:5173
```

Important: the current running UI is in preview mode unless the PHP backend is hosted and reachable at the configured API URL. Preview mode stores working data in browser `localStorage`.

## Stack

- Frontend: Vue 3, Vite, Vue Router, Pinia
- Backend: PHP 8.1+ API endpoints
- Database: MySQL 8+
- Default database name: `crs_coop`

## Main Folders

```text
backend/
  api/                 PHP API endpoints
  config/database.php  MySQL connection config
  helpers/             Shared PHP helpers

database/
  schema.sql           Main database schema and seed data
  *_module.sql         Incremental module SQL patches

frontend/
  src/                 Vue source files
  dist/                Production build output after npm run build
```

## Database

The primary schema is:

```text
database/schema.sql
```

It creates these core tables:

- `members`
- `loan_types`
- `loans`
- `amortization_schedule`
- `users`
- `notification_logs`
- `audit_logs`
- `share_capital_ledger`
- `companies`
- `bills`
- `bill_items`
- `bill_remittances`
- `payments`

Additional module scripts exist for audit logs, billing, loan pipeline, notifications, phase 6/7, and share capital.

Recommended setup:

```sql
SOURCE database/schema.sql;
```

Then review and apply module patches only if needed:

```text
database/audit_log_module.sql
database/billing_module.sql
database/loan_pipeline_module.sql
database/notification_log_module.sql
database/phase6_7_module.sql
database/share_capital_module.sql
```

## Backend Setup

Host the `backend/` folder under Apache/XAMPP/Laragon, for example:

```text
htdocs/crs-coop/backend/
```

Update:

```text
backend/config/database.php
```

Default values:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crs_coop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

Smoke test these endpoints after Apache/MySQL are running:

```text
http://localhost/crs-coop/backend/api/members.php
http://localhost/crs-coop/backend/api/loan-types.php
http://localhost/crs-coop/backend/api/loans.php
```

The API should return JSON with `success: true`.

## Frontend Setup

Install dependencies:

```bash
cd frontend
npm install
```

Configure API URL:

```text
frontend/.env.local
```

Current value:

```text
VITE_API_URL=http://localhost/crs-coop/backend/api
```

Run development server:

```bash
npm run dev
```

Build production assets:

```bash
npm run build
```

Production output goes to:

```text
frontend/dist/
```

## Preview Mode Behavior

The frontend API wrapper is:

```text
frontend/src/composables/useApi.js
```

It first tries to call the PHP backend. If the backend is unavailable, it falls back to browser `localStorage`.

Main preview storage keys include:

- `crs-coop-preview-data`
- `crs-coop-preview-settings`
- `crs-coop-preview-restructurings`
- beneficiary and notification preview keys inside their Vue views

For production, developers should persist all preview/localStorage-only modules through backend APIs and MySQL.

## Built Modules In The Current UI

- Dashboard with summaries and charts
- Members and 201 file
- Loan application/officer desk
- Eligibility engine
- Loan pipeline
- Loan monitoring
- Billing
- Payments focused on loan collections
- Share capital ledger under Membership
- Member beneficiaries
- Reports: collection summary, aging, outstanding balance
- Audit log
- Loan restructuring
- Notifications
- PDF loan packet
- Settings, users, roles, permissions, loan rules

## Recommended Production Work

1. Connect the PHP backend to MySQL and run the main schema.
2. Confirm every API route returns JSON successfully.
3. Replace remaining `localStorage` workflows with backend persistence:
   - settings roles and permissions
   - beneficiaries and ID attachments
   - restructuring records
   - monitoring status overrides
   - notification preview logs
4. Add real authentication/session handling.
5. Store file uploads outside the database or in a managed object store, and keep only metadata/paths in MySQL.
6. Add migrations for future schema changes instead of editing `schema.sql` directly after deployment.
7. Add backup and audit policies for financial records.

## Billing And Reconciliation Recommendation

The intended workflow should be:

1. Generate a bill for a company and period.
2. Bill status starts as `DRAFT`.
3. Issue/send the bill to the company.
4. Bill status becomes `ISSUED`.
5. Related amortization periods become `BILLED`.
6. When the company sends remittance, encode the remittance with OR/reference details and attachment.
7. System applies the remittance against bill items.
8. Partial remittance keeps the bill `PARTIAL`.
9. Full remittance marks the bill `SETTLED`.
10. Payment rows are created for each paid amortization item.
11. Loan schedules become `PAID` or `PARTIAL`.

So yes: issued billing should feed into payments as pending/billed obligations, then become confirmed payments only after remittance is posted.

## Notes For Developers

- Keep Share Capital under Membership only. It should not be duplicated under Payments.
- Payments should focus on loan collections and billing remittances.
- Use the visual/font standard currently applied in the frontend as the baseline for all modules.
- The app is currently a strong functional prototype, but the production milestone is backend persistence and authentication.
