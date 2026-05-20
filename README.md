# CRS Holdings Employees Credit Cooperative

Loan management system for CRS Holdings Employees Credit Cooperative — Mandaue City, Cebu. Covers the staff admin system, member-facing portal, PHP API, and public landing page.

## Stack

| Layer | Technology |
|-------|-----------|
| Database | MySQL 8+ |
| Backend API | PHP 8.1+ (procedural, no framework) |
| Admin frontend | Vue 3 + Vite + Pinia |
| Member portal | Static HTML / CSS / JS |
| Dev environment | Docker Compose (preferred) |

---

## Quick Start (Docker)

```bash
cd crs-github-merge
docker compose up -d
```

Then start the frontend:

```bash
cd frontend
npm install
npm run dev -- --host 0.0.0.0 --port 5174
```

| URL | Description |
|-----|-------------|
| `http://localhost:5174/` | Staff admin login |
| `http://localhost:5174/landing/` | Public landing page |
| `http://localhost:5174/crs-member-portal/index.html` | Member portal login |
| `http://localhost:8000/api/` | Backend API |

For another device on the same network, replace `localhost` with your Mac's IP (shown by Vite on startup).

---

## Login Credentials

### Staff Admin

| Email | Password | Role |
|-------|----------|------|
| `admin@crsholdings.ph` | *(set during setup)* | SUPER_ADMIN |

### Member Portal

| Member | Username | Temporary Password |
|--------|----------|--------------------|
| WAELSMITH BACLOHAN | `waelsmith.baclohan` | `CoralCrane509` |
| ROSALIO CABILING | `rosalio.cabiling` | `Star-Bloom-991` |

Members can also log in with their email address (if set) or member number.

---

## Manual Setup (without Docker)

### 1. Database

```bash
# Create the database and load the full schema + seed data
mysql -u root -p -e "CREATE DATABASE crs_coop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p crs_coop < database/schema.sql
```

Apply module patches in this order if needed:

```bash
mysql -u root -p crs_coop < database/billing_module.sql
mysql -u root -p crs_coop < database/audit_log_module.sql
mysql -u root -p crs_coop < database/loan_pipeline_module.sql
mysql -u root -p crs_coop < database/notification_log_module.sql
mysql -u root -p crs_coop < database/share_capital_module.sql
mysql -u root -p crs_coop < database/phase6_7_module.sql
mysql -u root -p crs_coop < database/member_portal_module.sql
```

### 2. Backend API

Edit `backend/config/database.php` with your MySQL credentials, then:

```bash
cd backend/api
php -S localhost:8000
```

### 3. Admin Frontend

```bash
cd frontend
npm install
cp .env.example .env.local
# Set VITE_API_URL=http://localhost:8000 in .env.local
npm run dev -- --host 0.0.0.0 --port 5174
```

---

## Project Structure

```
crs-github-merge/
├── backend/
│   ├── api/                        # PHP endpoints (one file per resource)
│   │   ├── member-auth.php         # Member portal login → bearer token
│   │   ├── member-portal.php       # Member data (loans, payments, share capital)
│   │   ├── member-portal-accounts.php
│   │   ├── members.php
│   │   ├── loans.php
│   │   ├── payments.php
│   │   ├── share-capital.php
│   │   └── ...
│   ├── config/database.php         # DB connection (reads env vars or hardcoded)
│   └── helpers/helpers.php         # cors(), json_ok(), json_err(), require_auth(), audit_log()
│
├── database/
│   ├── schema.sql                  # Full schema + seed data (run this first)
│   └── *.sql                       # Additive module patches
│
├── frontend/
│   ├── src/                        # Vue 3 admin app
│   └── public/
│       ├── landing/                # Public-facing landing page
│       └── crs-member-portal/      # Member portal (served statically)
│
└── crs-member-portal/              # Member portal source (sync to frontend/public/ after edits)
    ├── index.html                  # Login page
    ├── dashboard.html              # Member dashboard
    ├── css/styles.css
    └── js/
        ├── api.js                  # All backend calls (no demo fallback — real API only)
        ├── auth.js                 # Session management, login/logout
        └── portal.js              # Dashboard rendering
```

> **Member portal source rule:** Always edit files in `crs-member-portal/` and copy them to `frontend/public/crs-member-portal/`. The `public/` copy is what the browser serves.

---

## API Reference

### Auth
```
POST  /api/member-auth.php          Member portal login → { token, member, access }
```

### Admin resources
```
GET/POST/PUT/DELETE  /api/members.php
GET/POST/PUT         /api/loans.php
GET                  /api/loan-types.php
GET/POST             /api/payments.php
GET/POST/PUT         /api/share-capital.php
GET/POST             /api/bills.php
GET/POST/PUT         /api/users.php
```

### Member portal
```
POST  /api/member-auth.php          Login — returns bearer token (8-hour session)
GET   /api/member-portal.php        Member's own loans, payments, share capital, beneficiaries
GET/POST/PUT  /api/member-portal-accounts.php  Admin: manage portal access
```

### Member portal auth flow
1. Admin creates a portal account in **Settings → Member Portal Access**
2. Backend stores the account in `member_portal_accounts` with a bcrypt password hash
3. Member logs in via `member-auth.php` → receives a bearer token
4. Portal sends `Authorization: Bearer <token>` on every request
5. Backend validates the token against `member_portal_sessions`, re-derives the member identity from the session — never trusts a member ID from the browser

---

## Key Database Tables

| Table | Purpose |
|-------|---------|
| `members` | Member records |
| `loans` | Loan applications and disbursements |
| `amortization_schedule` | Per-period payment schedules |
| `payments` | Posted payment records |
| `share_capital_ledger` | Share capital transactions |
| `member_portal_accounts` | Member portal login credentials |
| `member_portal_sessions` | Active bearer token sessions (SHA-256 hashed) |
| `audit_logs` | Immutable audit trail for all financial and auth events |
| `users` | Staff accounts (SUPER_ADMIN, ADMIN, MANAGER, etc.) |

---

## Security Notes

- All staff API endpoints require a valid admin session (`require_auth()`)
- Role-based access enforced server-side via `require_cap()` — never trust the browser
- Member portal sessions expire after 8 hours (PHT)
- Session tokens stored as SHA-256 hashes only — raw token never persists in the DB
- `audit_log()` is called after every create/update/delete; records are append-only
- CORS is currently open (`*`) — lock to production domain before go-live

---

## Production Checklist

- [ ] Set real DB credentials via `.htaccess SetEnv` (not hardcoded in PHP)
- [ ] Lock CORS to production domain in `helpers.php`
- [ ] Build Vue app: `npm run build` → deploy `frontend/dist/` to `public_html`
- [ ] Add `.htaccess` SPA rewrite rules for Vue Router history mode
- [ ] Apply all SQL patches in order
- [ ] Disable PHP error display on the server
- [ ] Force HTTPS before issuing any member credentials
- [ ] Add member password change screen (accounts have `force_password_change` flag)
