#!/usr/bin/env bash
set -euo pipefail

# Smoke tests for Phase 1 RBAC requirements: RBAC-01..06
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

login_as() {
  local role=$(echo "$1" | tr '[:upper:]' '[:lower:]')  # lowercase
  local pw="Test-${1}-2026"
  curl -s -X POST "$BACKEND_URL/admin-auth.php" \
    -H 'Content-Type: application/json' \
    -d "{\"email\":\"rbac-test-${role}@crsholdings.ph\",\"password\":\"${pw}\"}" \
  | jq -r '.data.token // empty'
}

echo "=== smoke-rbac.sh — Phase 1 RBAC smoke tests ==="
echo "Backend: $BACKEND_URL"
echo ""

# RBAC-01 six-role ENUM
echo "--- RBAC-01: users.role ENUM must contain all 6 roles ---"
enum=$(mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SHOW COLUMNS FROM users LIKE 'role';" "$DB_NAME" 2>/dev/null)
for role in SUPER_ADMIN ADMIN MANAGER LOAN_OFFICER STAFF AUDITOR; do
  echo "$enum" | grep -q "$role" && pass "RBAC-01:enum $role" || fail "RBAC-01:enum missing $role"
done

# RBAC-04 role from server matches DB
echo ""
echo "--- RBAC-04: login response role must match DB role for all 6 ---"
for role in SUPER_ADMIN ADMIN MANAGER LOAN_OFFICER STAFF AUDITOR; do
  tok=$(login_as "$role")
  if [ -z "$tok" ]; then fail "RBAC-04:$role login failed"; continue; fi
  srv_role=$(curl -s -X POST "$BACKEND_URL/admin-auth.php" \
    -H 'Content-Type: application/json' \
    -d "{\"email\":\"rbac-test-$(echo "$role" | tr '[:upper:]' '[:lower:]')@crsholdings.ph\",\"password\":\"Test-${role}-2026\"}" \
    | jq -r '.data.user.role // empty')
  [ "$srv_role" = "$role" ] && pass "RBAC-04:$role role matches" || fail "RBAC-04:$role srv=$srv_role"
done

# RBAC-02 + RBAC-03 AUDITOR read-only
echo ""
echo "--- RBAC-02/03: AUDITOR can GET audit-logs (200) but cannot POST payments (403) ---"
AUD_TOK=$(login_as "AUDITOR")
code=$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $AUD_TOK" "$BACKEND_URL/audit-logs.php")
[ "$code" = "200" ] && pass "RBAC-03:AUDITOR GET audit-logs=200" || fail "RBAC-03:AUDITOR GET expected 200 got $code"
code=$(curl -s -o /dev/null -w '%{http_code}' \
  -X POST -H "Authorization: Bearer $AUD_TOK" -H 'Content-Type: application/json' \
  -d '{"loan_id":1,"period_no":1,"amount_paid":100,"payment_date":"2026-05-20"}' \
  "$BACKEND_URL/payments.php")
[ "$code" = "403" ] && pass "RBAC-02:AUDITOR POST payments=403" || fail "RBAC-02:AUDITOR POST expected 403 got $code"

# RBAC-05 MANAGER cannot create users; SUPER_ADMIN can
echo ""
echo "--- RBAC-05: MANAGER POST users=403; SUPER_ADMIN POST users=200/201 ---"
MGR_TOK=$(login_as "MANAGER")
code=$(curl -s -o /dev/null -w '%{http_code}' \
  -X POST -H "Authorization: Bearer $MGR_TOK" -H 'Content-Type: application/json' \
  -d '{"name":"x","email":"x-throwaway@crsholdings.ph","role":"STAFF","password":"Test-Throwaway-2026"}' \
  "$BACKEND_URL/users.php")
[ "$code" = "403" ] && pass "RBAC-05:MANAGER POST users=403" || fail "RBAC-05:MANAGER expected 403 got $code"

SA_TOK=$(login_as "SUPER_ADMIN")
code=$(curl -s -o /dev/null -w '%{http_code}' \
  -X POST -H "Authorization: Bearer $SA_TOK" -H 'Content-Type: application/json' \
  -d '{"name":"Throwaway","email":"x-throwaway@crsholdings.ph","role":"STAFF","password":"Test-Throwaway-2026","is_active":1}' \
  "$BACKEND_URL/users.php")
[[ "$code" =~ ^(200|201)$ ]] && pass "RBAC-05:SUPER_ADMIN POST users=200/201" || fail "RBAC-05:SUPER_ADMIN expected 200/201 got $code"
mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" -e \
  "DELETE FROM users WHERE email='x-throwaway@crsholdings.ph';" 2>/dev/null

# RBAC-06 loan approval requires MANAGER
echo ""
echo "--- RBAC-06: STAFF/LOAN_OFFICER PUT APPROVED=403; MANAGER not blocked ---"
STAFF_TOK=$(login_as "STAFF")
LO_TOK=$(login_as "LOAN_OFFICER")
for tok_var in STAFF_TOK LO_TOK; do
  eval tok=\$$tok_var
  code=$(curl -s -o /dev/null -w '%{http_code}' \
    -X PUT -H "Authorization: Bearer $tok" -H 'Content-Type: application/json' \
    -d '{"status":"APPROVED"}' "$BACKEND_URL/loans.php?id=1")
  [ "$code" = "403" ] && pass "RBAC-06:$tok_var PUT APPROVED=403" || fail "RBAC-06:$tok_var expected 403 got $code"
done
MGR_TOK=$(login_as "MANAGER")
code=$(curl -s -o /dev/null -w '%{http_code}' \
  -X PUT -H "Authorization: Bearer $MGR_TOK" -H 'Content-Type: application/json' \
  -d '{"status":"APPROVED"}' "$BACKEND_URL/loans.php?id=1")
# 403 = fail; 200/422 = pass (role check passed, downstream may be input error which is Phase 2)
[ "$code" = "403" ] && fail "RBAC-06:MANAGER expected NOT 403 got 403" || pass "RBAC-06:MANAGER not blocked ($code)"

echo ""
echo "=== Results: $FAILURES failure(s) ==="
exit ${FAILURES:-0}
