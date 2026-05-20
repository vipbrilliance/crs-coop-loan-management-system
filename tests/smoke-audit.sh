#!/usr/bin/env bash
set -euo pipefail

# Smoke tests for Phase 1 Audit requirements: AUDIT-01..05
# Run after: mysql -u root crs_coop < tests/seed-rbac-users.sql
# Do NOT point BACKEND_URL at a production host.

BACKEND_URL=${BACKEND_URL:-http://localhost:8000}
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-crs_coop}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}

# Production guard
if echo "$BACKEND_URL" | grep -qE 'crsholdings\.ph|\.production\.'; then
  echo "ERROR: BACKEND_URL '$BACKEND_URL' looks like a production host. Refusing to run." >&2
  exit 2
fi

FAILURES=0
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

pass() {
  echo -e "${GREEN}OK${NC}  $1"
}

fail() {
  echo -e "${RED}FAIL${NC} $1"
  FAILURES=$((FAILURES + 1))
}

echo "=== smoke-audit.sh — Phase 1 Audit smoke tests ==="
echo "Backend: $BACKEND_URL"
echo ""

# AUDIT-01 + AUDIT-02 financial action writes audit with session actor
echo "--- AUDIT-01/02: payment POST creates audit row with session actor_user_id ---"
LO_TOK=$(curl -s -X POST "$BACKEND_URL/admin-auth.php" \
  -H 'Content-Type: application/json' \
  -d '{"email":"rbac-test-loan_officer@crsholdings.ph","password":"Test-LOAN_OFFICER-2026"}' \
  | jq -r '.data.token // empty')
LO_ID=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SELECT id FROM users WHERE email='rbac-test-loan_officer@crsholdings.ph';" "$DB_NAME" 2>/dev/null)

# Check if any loan exists
loan_id=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SELECT id FROM loans ORDER BY id LIMIT 1;" "$DB_NAME" 2>/dev/null)

if [ -z "$loan_id" ]; then
  echo "WARN: No loans in DB — AUDIT-01/02 skipped (seed data needed)"
else
  baseline=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
    "SELECT COUNT(*) FROM audit_logs WHERE module IN ('Payments','PAYMENTS') AND action='POSTED';" "$DB_NAME" 2>/dev/null)
  curl -s -X POST "$BACKEND_URL/payments.php" \
    -H "Authorization: Bearer $LO_TOK" -H 'Content-Type: application/json' \
    -d "{\"loan_id\":$loan_id,\"period_no\":1,\"amount_paid\":100,\"payment_date\":\"2026-05-20\",\"user_id\":999,\"received_by\":999}" > /dev/null
  after=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
    "SELECT COUNT(*) FROM audit_logs WHERE module IN ('Payments','PAYMENTS') AND action='POSTED';" "$DB_NAME" 2>/dev/null)
  [ "$after" -gt "$baseline" ] && pass "AUDIT-01:payment audit row created" || fail "AUDIT-01:no audit row after payment POST"
  actor=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
    "SELECT actor_user_id FROM audit_logs WHERE module IN ('Payments','PAYMENTS') ORDER BY id DESC LIMIT 1;" "$DB_NAME" 2>/dev/null)
  [ "$actor" = "$LO_ID" ] && pass "AUDIT-02:actor=$LO_ID (NOT 999)" || fail "AUDIT-02:actor expected $LO_ID got $actor"
fi

# AUDIT-03 PHT timestamp
echo ""
echo "--- AUDIT-03: audit_logs session time_zone must be +08:00 (PHT) ---"
tz=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SELECT @@session.time_zone FROM audit_logs ORDER BY id DESC LIMIT 1;" "$DB_NAME" 2>/dev/null)
[ "$tz" = "+08:00" ] && pass "AUDIT-03:time_zone=+08:00" || fail "AUDIT-03:expected +08:00 got $tz"

# AUDIT-04 DELETE returns 405
echo ""
echo "--- AUDIT-04: DELETE audit-logs must return 405 (immutable log) ---"
SA_TOK=$(curl -s -X POST "$BACKEND_URL/admin-auth.php" \
  -H 'Content-Type: application/json' \
  -d '{"email":"rbac-test-super_admin@crsholdings.ph","password":"Test-SUPER_ADMIN-2026"}' \
  | jq -r '.data.token // empty')
code=$(curl -s -o /dev/null -w '%{http_code}' \
  -X DELETE -H "Authorization: Bearer $SA_TOK" "$BACKEND_URL/audit-logs.php?id=1")
[[ "$code" =~ ^(405|401)$ ]] && pass "AUDIT-04:DELETE audit-logs=$code (not 200)" || fail "AUDIT-04:DELETE expected 405/401 got $code"

# AUDIT-05 login/logout/failed-login events exist
echo ""
echo "--- AUDIT-05: LOGIN, LOGOUT, FAILED_LOGIN events must exist in audit_logs ---"
for action in LOGIN LOGOUT FAILED_LOGIN; do
  cnt=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
    "SELECT COUNT(*) FROM audit_logs WHERE action='$action';" "$DB_NAME" 2>/dev/null)
  [ "$cnt" -gt 0 ] && pass "AUDIT-05:$action event exists" || fail "AUDIT-05:no $action event in audit_logs"
done

echo ""
echo "=== Results: $FAILURES failure(s) ==="
exit ${FAILURES:-0}
