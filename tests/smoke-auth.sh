#!/usr/bin/env bash
set -euo pipefail

# Smoke tests for Phase 1 AUTH requirements: AUTH-01..05, AUDIT-03, 11-file coverage gate
# Run after: mysql -u root crs_coop < tests/seed-rbac-users.sql
# Do NOT point BACKEND_URL at a production host.

BACKEND_URL=${BACKEND_URL:-http://localhost:8000}
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-crs_coop}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
TEST_EMAIL=${TEST_EMAIL:-rbac-test-super_admin@crsholdings.ph}
TEST_PASSWORD=${TEST_PASSWORD:-Test-SUPER_ADMIN-2026}

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

echo "=== smoke-auth.sh — Phase 1 Auth smoke tests ==="
echo "Backend: $BACKEND_URL"
echo ""

# AUTH-02 anonymous rejected
echo "--- AUTH-02: anonymous requests must return 401 ---"
for ep in members.php dashboard.php loans.php payments.php audit-logs.php; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BACKEND_URL/$ep")
  [ "$code" = "401" ] && pass "AUTH-02:$ep anon=401" || fail "AUTH-02:$ep expected 401 got $code"
done

# AUTH-01 login returns token
echo ""
echo "--- AUTH-01: login returns 64-hex token and correct role ---"
resp=$(curl -s -X POST "$BACKEND_URL/admin-auth.php" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"$TEST_PASSWORD\"}")
TOKEN=$(echo "$resp" | jq -r '.data.token // empty')
ROLE=$(echo "$resp" | jq -r '.data.user.role // empty')
[[ "$TOKEN" =~ ^[0-9a-f]{64}$ ]] && pass "AUTH-01:token format" || fail "AUTH-01:token not 64-hex (got: $TOKEN)"
[ "$ROLE" = "SUPER_ADMIN" ] && pass "AUTH-01:role=SUPER_ADMIN" || fail "AUTH-01:role expected SUPER_ADMIN got $ROLE"

# AUTH-02 authenticated succeeds
echo ""
echo "--- AUTH-02: authenticated request returns success ---"
ok=$(curl -s -H "Authorization: Bearer $TOKEN" "$BACKEND_URL/members.php" | jq -r '.success // false')
[ "$ok" = "true" ] && pass "AUTH-02:authed request ok" || fail "AUTH-02:authed request not ok"

# AUDIT-03 PHT timestamp
echo ""
echo "--- AUDIT-03: audit_logs session time_zone = +08:00 (PHT) ---"
tz=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SELECT @@session.time_zone FROM audit_logs WHERE action='LOGIN' ORDER BY id DESC LIMIT 1;" "$DB_NAME" 2>/dev/null)
[ "$tz" = "+08:00" ] && pass "AUDIT-03:session time_zone=+08:00" || fail "AUDIT-03:time_zone expected +08:00 got $tz"

# AUTH-05 failed login audited
echo ""
echo "--- AUTH-05: failed login creates FAILED_LOGIN audit row ---"
baseline=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SELECT COUNT(*) FROM audit_logs WHERE action='FAILED_LOGIN';" "$DB_NAME" 2>/dev/null)
curl -s -X POST "$BACKEND_URL/admin-auth.php" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"wrongpassword\"}" > /dev/null
after=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SELECT COUNT(*) FROM audit_logs WHERE action='FAILED_LOGIN';" "$DB_NAME" 2>/dev/null)
[ "$after" -gt "$baseline" ] && pass "AUTH-05:failed_login audited" || fail "AUTH-05:no FAILED_LOGIN audit row"

# AUTH-04 expired session rejected
echo ""
echo "--- AUTH-04: expired session must return 401 ---"
mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" -e \
  "UPDATE admin_sessions SET expires_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 MINUTE) WHERE token_hash = SHA2('$TOKEN', 256);" 2>/dev/null
code=$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $TOKEN" "$BACKEND_URL/members.php")
[ "$code" = "401" ] && pass "AUTH-04:expired session=401" || fail "AUTH-04:expected 401 got $code"
# Re-login for AUTH-03
resp2=$(curl -s -X POST "$BACKEND_URL/admin-auth.php" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"$TEST_PASSWORD\"}")
TOKEN2=$(echo "$resp2" | jq -r '.data.token // empty')

# AUTH-03 logout invalidates
echo ""
echo "--- AUTH-03: logout deletes session; stale token must return 401 ---"
curl -s -X POST "$BACKEND_URL/admin-auth.php?action=logout" \
  -H "Authorization: Bearer $TOKEN2" \
  -H 'Content-Type: application/json' -d '{}' > /dev/null
count=$(mysql -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e \
  "SELECT COUNT(*) FROM admin_sessions WHERE token_hash = SHA2('$TOKEN2', 256);" "$DB_NAME" 2>/dev/null)
[ "$count" = "0" ] && pass "AUTH-03:session deleted" || fail "AUTH-03:session still in DB"
code=$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $TOKEN2" "$BACKEND_URL/members.php")
[ "$code" = "401" ] && pass "AUTH-03:stale token=401" || fail "AUTH-03:stale token expected 401 got $code"

# Coverage gate: all 11 admin API files guarded
echo ""
echo "--- Coverage gate: all 11 admin API files must have require_auth ---"
for f in audit-logs bills dashboard loan-types loans member-portal-accounts members notification-logs payments share-capital users; do
  grep -q 'require_auth' "backend/api/$f.php" && pass "guard:$f" || fail "guard:$f MISSING require_auth"
done
# member-auth and member-portal must NOT be guarded
echo ""
echo "--- Coverage gate: member-auth and member-portal must NOT have require_auth ---"
for f in member-auth member-portal; do
  grep -q 'require_auth' "backend/api/$f.php" 2>/dev/null && fail "guard:$f SHOULD NOT have require_auth" || pass "guard:$f excluded OK"
done

echo ""
echo "=== Results: $FAILURES failure(s) ==="
exit ${FAILURES:-0}
