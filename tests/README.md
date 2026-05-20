# Phase 1 Smoke Tests

Acceptance suite for AUTH-01..05, RBAC-01..06, AUDIT-01..05.

## Prerequisites

- MySQL running with schema applied: `mysql -u root crs_coop < database/schema.sql`
- Apply Phase 1 patches: `mysql -u root crs_coop < database/admin_auth_module.sql`
- PHP dev server: `php -S localhost:8000 -t backend/api`
- Tools: `jq`, `mysql` client, `bash`, `curl`

## Setup (seed test users)

```bash
mysql -u root crs_coop < tests/seed-rbac-users.sql
```

## Run

```bash
bash tests/smoke-auth.sh && bash tests/smoke-rbac.sh && bash tests/smoke-audit.sh
```

## Environment Overrides

| Variable | Default | Description |
|----------|---------|-------------|
| `BACKEND_URL` | `http://localhost:8000` | PHP dev server URL |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_NAME` | `crs_coop` | Database name |
| `DB_USER` | `root` | MySQL user |
| `DB_PASS` | (empty) | MySQL password |

**Warning — Production guard:** If `BACKEND_URL` contains `crsholdings.ph` or `.production.`, all scripts refuse to run (exit 2). Never set `BACKEND_URL` to a production host.

## Expected Output (clean run)

All lines show `OK` with green color. Exit code 0.

## Troubleshooting

| Failure | Root Cause |
|---------|-----------|
| `AUTH-01: token not 64-hex` | Seed bcrypt hash mismatch — regenerate: `php -r "echo password_hash('CRS-Admin-2026', PASSWORD_DEFAULT);"` |
| `AUDIT-03: time_zone` | `SET time_zone` missing from `getDB()` — check Plan 01-01 Task 1.2 |
| `RBAC-06: MANAGER blocked` | `require_cap(MANAGER)` missing in loans.php PUT — check Plan 01-02 Task 2.2 |
| `AUDIT-02: actor=999` | Browser-supplied user_id still in payments.php — check Plan 01-02 Task 2.3 |
| `AUTH-02: anonymous not 401` | `require_auth()` missing from that endpoint — check Plan 01-02 |

## TEARDOWN (run before production deploy)

```bash
mysql -u root crs_coop -e "DELETE FROM users WHERE email LIKE 'rbac-test-%@crsholdings.ph';"
```

This cascades through `admin_sessions` via FK `ON DELETE CASCADE`. Verify: `SELECT COUNT(*) FROM users WHERE email LIKE 'rbac-test-%'` returns 0.

## Requirement Coverage

| Requirement | Script | What it checks |
|-------------|--------|----------------|
| AUTH-01 | smoke-auth.sh | Login returns 64-hex token + SUPER_ADMIN role |
| AUTH-02 | smoke-auth.sh | Anonymous -> 401 on 5 endpoints; authed -> 200 |
| AUTH-03 | smoke-auth.sh | Logout deletes session; stale token -> 401 |
| AUTH-04 | smoke-auth.sh | Expired session -> 401 |
| AUTH-05 | smoke-auth.sh | Failed login audit row created |
| RBAC-01 | smoke-rbac.sh | 6-role ENUM in users.role |
| RBAC-02 | smoke-rbac.sh | AUDITOR POST payments -> 403 |
| RBAC-03 | smoke-rbac.sh | AUDITOR GET audit-logs -> 200 |
| RBAC-04 | smoke-rbac.sh | Login response role matches DB |
| RBAC-05 | smoke-rbac.sh | MANAGER POST users -> 403; SUPER_ADMIN -> 200 |
| RBAC-06 | smoke-rbac.sh | STAFF/LOAN_OFFICER PUT APPROVED -> 403; MANAGER not blocked |
| AUDIT-01 | smoke-audit.sh | Payment POST creates audit row |
| AUDIT-02 | smoke-audit.sh | Audit actor = session user ID, not body user_id=999 |
| AUDIT-03 | smoke-audit.sh | audit_logs.created_at in PHT (+08:00) |
| AUDIT-04 | smoke-audit.sh | DELETE audit-logs -> 405 |
| AUDIT-05 | smoke-audit.sh | LOGIN, LOGOUT, FAILED_LOGIN events in audit_logs |
