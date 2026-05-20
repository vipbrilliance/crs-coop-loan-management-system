# CRS Coop Loan Management System - Developer Handoff

Last updated: May 15, 2026

This document is for the development team taking over the CRS Holdings Employees Credit Cooperative Loan Management System. It summarizes what is already built, how to run it, the database/API pieces that need to be wired for production, and the known next steps.

## Repository

GitHub:

```text
https://github.com/vipbrilliance/crs-coop-loan-management-system
```

Primary local project folder used during prototype work:

```text
/Users/michaeljaya.villanueva/Downloads/CRS COOP System/crs-github-merge
```

## Current Stack

- Admin frontend: Vue 3 + Vite
- Member portal: static HTML/CSS/JavaScript
- Backend API: PHP 8.1+ procedural endpoints
- Database: MySQL 8+
- Current local API base: `http://localhost:8000`
- Current local frontend base: `http://localhost:5174`

## Main Local URLs

```text
Admin system:   http://localhost:5174/
Member portal:  http://localhost:5174/crs-member-portal/index.html
Backend API:    http://localhost:8000
```

For a demo on another device in the same Wi-Fi network, replace `localhost` with the Mac LAN IP shown by Vite, for example:

```text
http://192.168.254.115:5174/
http://192.168.254.115:5174/crs-member-portal/index.html
```

## Project Layout

```text
backend/
  api/
    audit-logs.php
    bills.php
    dashboard.php
    loan-types.php
    loans.php
    member-auth.php
    member-portal.php
    member-portal-accounts.php
    members.php
    notification-logs.php
    payments.php
    share-capital.php
    users.php
  config/
    database.php

database/
  schema.sql
  member_portal_module.sql
  share_capital_module.sql
  billing_module.sql
  phase6_7_module.sql

frontend/
  src/
    views/
    composables/
    router.js
  public/
    crs-member-portal/
  dist/

crs-member-portal/
  index.html
  dashboard.html
  css/styles.css
  js/api.js
  js/auth.js
  js/portal.js
```

## Built Admin Modules

The Vue admin system currently includes these routes/modules:

```text
/                         Dashboard
/members                  Members / 201 file
/eligibility              Eligibility Engine
/loans                    Loan Officer Desk / Applications
/pipeline                 Loan Pipeline
/monitoring               Loan Monitoring
/restructuring            Loan Restructuring
/billing                  Billing
/payments                 Payments / Collections
/reports/collection       Collection Summary
/reports/aging            Aging Report
/reports/outstanding      Outstanding Balance
/audit-logs               Audit Logs
/share-capital            Share Capital Ledger
/beneficiaries            Member Beneficiaries
/notifications            Notifications
/loan-packet              PDF Loan Packet
/settings                 System Settings
```

## Backend Setup

Configure database connection here:

```text
backend/config/database.php
```

Current expected local config:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crs_coop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

Run the API locally:

```bash
cd backend/api
php -S localhost:8000
```

Important: the PHP dev server must be started from `backend/api` so endpoint paths resolve like:

```text
http://localhost:8000/members.php
http://localhost:8000/member-auth.php
```

## Frontend Setup

Run the admin frontend:

```bash
cd frontend
npm install
cp .env.example .env.local
```

Set:

```text
VITE_API_URL=http://localhost:8000
```

Run:

```bash
npm run dev -- --host 0.0.0.0 --port 5174
```

Build:

```bash
npm run build
```

## Database Setup

Fresh database:

```sql
SOURCE database/schema.sql;
```

Existing database that already has the core CRS tables:

```sql
SOURCE database/member_portal_module.sql;
```

The member portal migration adds:

```text
member_portal_accounts
member_portal_sessions
member_portal_audit_logs
```

## Important API Endpoints

Core endpoints:

```text
GET/POST/PUT/DELETE  /members.php
GET/POST/PUT         /loans.php
GET                  /loan-types.php
GET/POST             /payments.php
GET/POST/PUT         /share-capital.php
GET/POST             /bills.php
GET/POST/PUT         /users.php
GET                  /dashboard.php
```

Member portal endpoints:

```text
GET/POST/PUT         /member-portal-accounts.php
POST                 /member-auth.php
GET                  /member-portal.php
```

## Member Portal Access Flow

1. Admin opens `Settings -> Member Portal Access`.
2. Admin creates a username/password for a member.
3. Account is saved in `member_portal_accounts`.
4. Member logs in at:

```text
http://localhost:5174/crs-member-portal/index.html
```

5. Portal posts credentials to:

```text
POST /member-auth.php
```

6. API returns a bearer token and member profile.
7. Portal calls:

```text
GET /member-portal.php
Authorization: Bearer <token>
```

8. Backend should return only that authenticated member's allowed records.

## Security Notes

- Member passwords are stored using PHP `password_hash()`.
- Portal sessions store SHA-256 token hashes, not raw tokens.
- Member-facing endpoints must always filter by authenticated member account.
- Do not trust a member ID sent from the browser.
- Production must use HTTPS before enabling real member credentials.
- Add password reset/change-password before production launch.
- Add rate limiting / login attempt throttling for `member-auth.php`.
- Review CORS once frontend/API are deployed to final domains.

## Data And Integration Notes

The admin frontend still has preview/local-storage fallback behavior for demos. For real testing, always run with:

```text
VITE_API_URL=http://localhost:8000
```

The member portal also has demo fallback code in `crs-member-portal/js/api.js`. This is useful for UI review but should be disabled or guarded in production.

Attachment-related workflows are represented in UI but still need production storage decisions:

```text
beneficiary ID attachments
share capital deposit slips / receipts
signed loan packet uploads
PDF packet documents
```

Recommended production approach:

- Store uploaded files outside the web root or in object storage.
- Store metadata in MySQL with owner/member references.
- Require authorization checks before every download/preview.
- Keep audit logs for uploads, downloads, voids, edits, and approvals.

## Billing And Payments Reconciliation Design

Recommended workflow:

1. Generate billing cycle from active loans and due schedules.
2. Send billing statement to company.
3. Billing items become expected receivables.
4. Company remittance is uploaded or encoded.
5. System matches remittance rows to expected billing items.
6. Matched items become pending payments.
7. Loan officer/accounting confirms payments.
8. Confirmed payments post to loan schedules and collections.
9. Differences stay as exceptions for review.

Suggested statuses:

```text
BILL_DRAFT
BILL_ISSUED
REMITTANCE_RECEIVED
PENDING_CONFIRMATION
POSTED
PARTIAL
EXCEPTION
CANCELLED
```

## Recommended Next Backend Work

- Normalize and finalize all MySQL tables before production data entry.
- Add migrations instead of editing only `schema.sql`.
- Add authentication for admin users.
- Add role/permission middleware for admin modules.
- Convert preview-only UI paths to real API calls.
- Add validation and consistent error responses to every endpoint.
- Add pagination/search server-side for large member and loan tables.
- Add audit logging to create/update/delete operations.
- Add automated tests for loan calculations and payment posting.

## Recommended Next Frontend Work

- Add loading/empty/error states consistently.
- Remove or guard demo fallback mode in production builds.
- Add role-based module visibility after backend permissions are ready.
- Verify mobile layouts for members, payments, monitoring, and settings.
- Finalize standard typography across all modules.
- Add production upload/preview flows for documents and IDs.

## Handoff Checklist For Dev Team

- Pull latest `main` from GitHub.
- Create local MySQL database `crs_coop`.
- Import `database/schema.sql`.
- Configure `backend/config/database.php`.
- Start PHP API at `localhost:8000`.
- Start Vue dev server at `localhost:5174`.
- Confirm `GET http://localhost:8000/members.php` returns JSON.
- Confirm admin app loads real member data.
- Create a member portal access account in Settings.
- Confirm member portal login works.
- Review API security before production deployment.

## Known Caveats

- The current system is a strong prototype/handoff build, not a hardened production release.
- Some modules still use preview state or generated sample data when API calls are unavailable.
- No production admin authentication guard is enforced yet.
- File uploads need final storage architecture.
- Reports/PDF generation need final server-side rendering or document generation strategy.
- The database should be reviewed by the dev team before importing real cooperative data.
