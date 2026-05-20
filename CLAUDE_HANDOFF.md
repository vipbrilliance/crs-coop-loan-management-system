# Claude Handoff - CRS Coop Loan Management System

Last updated: May 18, 2026

This file is written specifically to help Claude or another development assistant understand the CRS Coop Loan Management System quickly. Use this together with `README.md`, `HANDOFF.md`, and the source folders listed below.

## One-Sentence Project Summary

CRS Coop Loan Management System is a cooperative loan administration prototype with a Vue admin/officer dashboard, PHP/MySQL API backend, and a separate member-facing portal for viewing personal loan, payment, share capital, and beneficiary information.

## GitHub Repository

```text
https://github.com/vipbrilliance/crs-coop-loan-management-system
```

Current local project folder used during handoff:

```text
/Users/michaeljaya.villanueva/Downloads/CRS COOP System/crs-github-merge
```

## Tech Stack

- Admin frontend: Vue 3, Vue Router, Pinia, Vite
- Member portal: static HTML, CSS, JavaScript
- Backend API: PHP 8.1+ procedural endpoints
- Database: MySQL 8+
- Local API server: PHP built-in server

## Important Existing Documentation

Give Claude these files first:

```text
README.md
HANDOFF.md
CLAUDE_HANDOFF.md
crs-member-portal/README.md
```

What each file is for:

- `README.md`: normal project setup and overview.
- `HANDOFF.md`: developer handoff, module list, setup, security notes, and known caveats.
- `CLAUDE_HANDOFF.md`: AI-assistant-oriented project context and next-task guide.
- `crs-member-portal/README.md`: standalone member portal setup and flow.

## Must-Include Source Folders For Claude

If moving this project to Claude, upload or provide access to these folders:

```text
backend/
database/
frontend/
crs-member-portal/
```

Also include these root files:

```text
README.md
HANDOFF.md
CLAUDE_HANDOFF.md
.gitignore
```

Do not include real `.env`, `.env.local`, database passwords, production credentials, or GitHub tokens.

## Current Local URLs

When running locally:

```text
Admin system:   http://localhost:5174/
Member portal:  http://localhost:5174/crs-member-portal/index.html
Backend API:    http://localhost:8000
```

For LAN/mobile demo, replace `localhost` with the Mac IP shown by Vite.

## Setup Commands

### Backend API

Configure database credentials:

```text
backend/config/database.php
```

Expected local database:

```text
DB_NAME=crs_coop
DB_USER=root
DB_PASS=
```

Run backend:

```bash
cd backend/api
php -S localhost:8000
```

### Frontend

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

## Database Files

Main schema:

```text
database/schema.sql
```

Module migrations / additions:

```text
database/audit_log_module.sql
database/billing_module.sql
database/loan_pipeline_module.sql
database/member_portal_module.sql
database/notification_log_module.sql
database/phase6_7_module.sql
database/share_capital_module.sql
```

For an existing CRS database, apply `database/member_portal_module.sql` to add member portal access tables:

```text
member_portal_accounts
member_portal_sessions
member_portal_audit_logs
```

## Backend API Endpoints

Files in `backend/api/`:

```text
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
```

Important endpoint groups:

```text
Members:              /members.php
Loans:                /loans.php
Loan types/settings:  /loan-types.php
Payments:             /payments.php
Billing:              /bills.php
Share capital:        /share-capital.php
Users:                /users.php
Dashboard:            /dashboard.php
Member portal admin:  /member-portal-accounts.php
Member portal login:  /member-auth.php
Member portal data:   /member-portal.php
```

## Admin Frontend Routes

Defined in:

```text
frontend/src/router.js
```

Current routes:

```text
/                         Dashboard
/members                  Members / 201 file
/eligibility              Eligibility Engine
/loans                    Loan Officer Desk / Loan Applications
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
/settings                 Settings
```

## Member Portal Flow

Source:

```text
crs-member-portal/
```

Served in Vite from:

```text
frontend/public/crs-member-portal/
```

Flow:

1. Admin goes to `Settings -> Member Portal Access`.
2. Admin creates a member portal account.
3. API stores username/password hash in `member_portal_accounts`.
4. Member logs in at `/crs-member-portal/index.html`.
5. Portal calls `POST /member-auth.php`.
6. Backend returns bearer token and member profile.
7. Portal calls `GET /member-portal.php` using `Authorization: Bearer <token>`.
8. Backend should return only data owned by that authenticated member.

Security requirements:

- Passwords must be hashed with `password_hash()`.
- Session table must store token hashes, not raw tokens.
- Member-facing API must never trust browser-sent member IDs.
- Production must use HTTPS.
- Add rate limiting and password reset/change-password before production.

## Current Build Status

The project is a working prototype/handoff build. It is not yet a hardened production release.

Already represented in the app:

- Members / 201 file
- Loan application workflow
- Loan pipeline
- Loan monitoring
- Eligibility rules
- Payments / collections
- Billing
- Share capital ledger
- Beneficiaries
- Loan restructuring
- Notifications
- PDF loan packet
- Settings and permissions
- Member portal account setup
- Member-facing portal files

Still needs developer production work:

- Full database normalization/review
- Admin authentication and authorization middleware
- Production-ready session management
- Server-side validation on all endpoints
- File upload storage and access control
- PDF/document generation strategy
- Test coverage for loan math and payment posting
- Removal or strict guarding of demo fallback data

## Known Prototype Behavior

The admin frontend has local preview fallback behavior when the backend is unavailable.

The member portal has demo fallback behavior in:

```text
crs-member-portal/js/api.js
```

Claude should treat fallback behavior as useful for demos only. Production should rely on the PHP API and MySQL database.

## Suggested Next Tasks For Claude

Ask Claude to work in this order:

1. Review `README.md`, `HANDOFF.md`, and this file.
2. Inspect `database/schema.sql` and module SQL files for consistency.
3. Inspect `backend/api/member-auth.php`, `member-portal.php`, and `member-portal-accounts.php`.
4. Verify admin Settings member portal account creation against the database.
5. Verify member portal login and bearer-token data loading.
6. Add or improve backend validation and consistent JSON error responses.
7. Add admin auth/roles/permissions server-side.
8. Replace preview fallback behavior with explicit development/demo mode.
9. Add tests for amortization, eligibility, billing, and payment posting.
10. Prepare production deployment notes.

## Suggested Prompt To Start Claude

Use this prompt:

```text
You are taking over the CRS Coop Loan Management System. Read README.md, HANDOFF.md, CLAUDE_HANDOFF.md, database/schema.sql, backend/api/*.php, frontend/src/router.js, frontend/src/composables/useApi.js, and crs-member-portal/README.md first.

Goal: understand the current prototype, verify the member portal access flow, identify production risks, and propose the next implementation steps. Do not rewrite the UI yet. Start with a concise architecture summary, current setup commands, database/API observations, and prioritized next tasks.
```

## Files Claude Should Inspect First

```text
README.md
HANDOFF.md
CLAUDE_HANDOFF.md
database/schema.sql
database/member_portal_module.sql
backend/config/database.php
backend/api/member-auth.php
backend/api/member-portal.php
backend/api/member-portal-accounts.php
frontend/src/router.js
frontend/src/composables/useApi.js
frontend/src/views/SettingsView.vue
crs-member-portal/README.md
crs-member-portal/js/api.js
crs-member-portal/js/auth.js
crs-member-portal/js/portal.js
```

## Deployment Notes To Confirm With Developers

- Decide whether admin and member portal are served from the same domain or separate subdomains.
- Decide final production API base URL.
- Configure HTTPS before using real credentials.
- Set real database credentials outside the repository.
- Store uploads in private storage with authorized download endpoints.
- Add database backups and migration/versioning workflow.
- Add monitoring and audit trail for sensitive transactions.

## Final Warning For Claude

Do not assume this is production-ready. Treat it as a substantial prototype with many module screens and some PHP/MySQL wiring already in place. The highest-risk areas are authentication, authorization, payment posting, document uploads, and data integrity.
