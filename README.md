# CRS Holdings Employees Credit Cooperative

Loan management system for CRS Holdings Employees Credit Cooperative. The repository includes the admin/officer system, PHP API, MySQL schema, and the member-facing portal.

## Stack

- Database: MySQL 8+
- Backend: PHP 8.1+
- Admin frontend: Vue 3 + Vite
- Member portal: static HTML/CSS/JavaScript, served from the same frontend host or deployed separately

## Local Demo URLs

When running the current local setup:

```text
Admin system:   http://localhost:5174/
Member portal:  http://localhost:5174/crs-member-portal/index.html
Backend API:    http://localhost:8000
```

For another device on the same Wi-Fi, replace `localhost` with the Mac IP shown by Vite, for example:

```text
http://192.168.254.115:5174/
http://192.168.254.115:5174/crs-member-portal/index.html
```

## Setup

### 1. Database

Create/import the database:

```sql
source database/schema.sql
```

For an existing database that already has the core tables, apply only the member portal migration:

```sql
source database/member_portal_module.sql
```

### 2. Backend API

Edit:

```text
backend/config/database.php
```

Set the local MySQL credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crs_coop');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Development API server:

```bash
cd backend/api
php -S localhost:8000
```

### 3. Admin Frontend

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

### 4. Member Portal

The portal source is in:

```text
crs-member-portal/
```

For same-origin local demos, it is also copied into:

```text
frontend/public/crs-member-portal/
```

Open:

```text
http://localhost:5174/crs-member-portal/index.html
```

## Important API Endpoints

Core:

```text
GET/POST/PUT/DELETE  /members.php
GET/POST/PUT         /loans.php
GET                  /loan-types.php
GET/POST             /payments.php
GET/POST/PUT         /share-capital.php
GET/POST             /bills.php
GET/POST/PUT         /users.php
```

Member portal:

```text
GET/POST/PUT         /member-portal-accounts.php
POST                 /member-auth.php
GET                  /member-portal.php
```

Member portal flow:

1. Admin creates access in `Settings -> Member Portal Access`.
2. Backend saves the member login in `member_portal_accounts`.
3. Member logs in through `member-auth.php`.
4. API returns a bearer token and member profile.
5. Portal calls `member-portal.php` with `Authorization: Bearer <token>`.
6. Backend returns only that member's loans, payments, share capital, beneficiaries, and allowed modules.

## Database Additions For Member Portal

The member portal uses these tables:

```text
member_portal_accounts
member_portal_sessions
member_portal_audit_logs
```

Passwords are stored as PHP `password_hash()` values. Sessions store only a SHA-256 token hash.

## Project Structure

```text
backend/
  api/
    member-auth.php
    member-portal.php
    member-portal-accounts.php
    members.php
    loans.php
    payments.php
    share-capital.php
  config/database.php
  helpers/helpers.php

database/
  schema.sql
  member_portal_module.sql

frontend/
  src/
  public/crs-member-portal/

crs-member-portal/
  index.html
  dashboard.html
  css/styles.css
  js/api.js
  js/auth.js
  js/portal.js
```

## Current Notes

- The Vue admin system falls back to preview/local storage if the backend is unavailable.
- For real testing, run the frontend with `VITE_API_URL=http://localhost:8000`.
- The member portal can also fall back to demo data, but production should always use the PHP API and MySQL tables.
- Every member-facing endpoint must filter by the authenticated member account. Do not trust a member ID sent from the browser.

## Production Checklist

- Configure real database credentials on the server.
- Apply `database/schema.sql` or `database/member_portal_module.sql`.
- Point `VITE_API_URL` to the production API base.
- Serve the built Vue app from `frontend/dist`.
- Use HTTPS before enabling real member credentials.
- Add password reset/change-password screens for member accounts.
- Add file/object storage for IDs, signed packets, receipts, and other attachments.
