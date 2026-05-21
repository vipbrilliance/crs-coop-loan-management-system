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

---

## Phase 2: Server-Side Validation Smoke Tests

Acceptance suite for VALID-01 through VALID-05 (server-side validation and business-rule enforcement).

### Prerequisites

1. **Phase 1 seed already applied** — `tests/seed-rbac-users.sql` must be sourced first.
   The script reuses the MANAGER, STAFF, and LOAN_OFFICER users created by that seed.
2. **Plans 02-01, 02-02, 02-03 deployed** — `helpers.php` must have `json_validation_err()`,
   `loans.php` must have VALID-01/02/03/05 checks, and `payments.php` must have the VALID-04 check.
3. **`jq` installed** — used to parse JSON responses.
4. **`BASE_URL` env var** — optional; defaults to `http://localhost:8000`.

### Setup (seed test fixtures)

```bash
mysql -u <user> -p <db> < tests/seed-rbac-users.sql
mysql -u <user> -p <db> < tests/seed-valid-fixtures.sql
```

`seed-valid-fixtures.sql` inserts:
- Members 9001 (no active loan) and 9002 (has ACTIVE loan 9105)
- Loans 9101 (DRAFT), 9102 (PENDING), 9103 (APPROVED), 9104 (ACTIVE) for member 9001
- Loans 9105 (ACTIVE) and 9106 (APPROVED) for member 9002 (VALID-03 pair)
- One `amortization_schedule` row: loan 9104 period 1, `amount_due=12500.00`, `paid_amount=0.00`

The seed file is idempotent (`ON DUPLICATE KEY UPDATE` on primary keys); sourcing it twice produces the same result.

### Run

```bash
BASE_URL=http://localhost:8000 bash tests/smoke-valid.sh
```

### Expected Output (clean run)

```
[PASS] VALID-01a: POST loans empty body → 422 + required field errors
[PASS] VALID-01b: POST payments empty body → 422 + required field errors
[PASS] VALID-02a: POST loans amount=1 (below min) → 422 + 'Amount must be between' error
[PASS] VALID-02b: POST loans amount=99999999 (above max) → 422 + errors.amount exists
[PASS] VALID-03:  PUT loan 9106 to ACTIVE → 422 'Member already has an active' + status unchanged
[PASS] VALID-04a: POST payment 12500.01 (1c over ceiling) → 422 'Amount exceeds period balance of'
[PASS] VALID-04b: POST payment 12500.00 (exact ceiling) → 200 success=true (regression)
[PASS] VALID-05a: PUT loan 9101 DRAFT→ACTIVE → 422 'Cannot move from DRAFT to ACTIVE' + status unchanged
[PASS] VALID-05b: PUT loan 9101 DRAFT→PENDING → 200 (regression: valid transition works)
[PASS] VALID-05c: STAFF PUT loan 9102 PENDING→APPROVED → 403 (RBAC fires before transition check)

Total: 10 tests | Passed: 10 | Failed: 0
```

### Exit Codes

| Code | Meaning |
|------|---------|
| 0    | All tests passed |
| 1    | One or more tests failed |
| 2    | Production host detected — script refused to run |

### Re-running the Suite

`smoke-valid.sh` makes two real DB writes:

- **VALID-04b** posts a payment row to `payments` and updates `amortization_schedule.paid_amount` for loan 9104 period 1.
- **VALID-05b** transitions loan 9101 from `DRAFT` to `PENDING`.

To re-run cleanly, re-source the fixture seed first (it restores both rows to their original state):

```bash
mysql -u <user> -p <db> < tests/seed-valid-fixtures.sql
BASE_URL=http://localhost:8000 bash tests/smoke-valid.sh
```

### Environment Overrides

| Variable | Default | Description |
|----------|---------|-------------|
| `BASE_URL` | `http://localhost:8000` | PHP dev server URL |

**Warning — Production guard:** If `BASE_URL` contains `crsholdings.ph` or `.production.`, the script refuses to run (exit 2). Never set `BASE_URL` to a production host.
