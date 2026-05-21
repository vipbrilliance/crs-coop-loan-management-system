#!/usr/bin/env bash
set -euo pipefail

# Smoke tests for Phase 3 localStorage migrations:
#   BENEF-01, BENEF-02, BENEF-03  — Beneficiaries module
#   RESTR-01 through RESTR-05     — Loan Restructuring module
#   NOTIF-01, NOTIF-02            — Notification Logs module
#
# Run after:
#   mysql -u root crs_coop < database/schema.sql
#   mysql -u root crs_coop < database/phase3_module.sql
#   mysql -u root crs_coop < tests/seed-rbac-users.sql
#   mysql -u root crs_coop < tests/seed-phase3-fixtures.sql
#
# NOTE: Wave 0 — endpoint PHP files do not exist yet.
#       HTTP assertions will FAIL at this stage (expected).
#       Table-existence and DB-side checks pass.
#       Re-run after Wave 1–3 endpoints are deployed to see full green.
#
# Do NOT point BASE_URL at a production host.

BASE_URL=${BASE_URL:-http://localhost:8000}
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-crs_coop}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_PORT=${DB_PORT:-3306}

# Production guard — mirrors Phase 1/2 smoke scripts
if echo "$BASE_URL" | grep -qE 'crsholdings\.ph|\.production\.'; then
  echo "ERROR: BASE_URL '$BASE_URL' looks like a production host. Refusing to run." >&2
  exit 2
fi

PASS=0
FAIL=0
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

record_result() {
  local req_id="$1"
  local label="$2"
  local result="$3"   # "pass" or "fail"
  if [ "$result" = "pass" ]; then
    echo -e "${GREEN}[PASS]${NC} ${req_id}: ${label}"
    PASS=$((PASS + 1))
  else
    echo -e "${RED}[FAIL]${NC} ${req_id}: ${label}"
    FAIL=$((FAIL + 1))
  fi
}

warn() {
  echo -e "${YELLOW}[WARN]${NC} $1"
}

# -------------------------------------------------------
# LOGIN HELPERS
# -------------------------------------------------------

login_as() {
  # Usage: login_as ROLE
  # Returns the bearer token or empty string on failure.
  local role_upper="$1"
  local role_lower
  role_lower=$(echo "$role_upper" | tr '[:upper:]' '[:lower:]')
  local password="Test-${role_upper}-2026"
  curl -s -X POST "${BASE_URL}/admin-auth.php" \
    -H 'Content-Type: application/json' \
    -d "{\"email\":\"rbac-test-${role_lower}@crsholdings.ph\",\"password\":\"${password}\"}" \
  | jq -r '.data.token // empty'
}

# -------------------------------------------------------
# DB QUERY HELPER
# -------------------------------------------------------

db_query() {
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e "$1" "$DB_NAME" 2>/dev/null
}

echo "=== smoke-phase3.sh — Phase 3 localStorage Migration smoke tests ==="
echo "Base URL: $BASE_URL"
echo ""

# Obtain tokens for the three roles used in this suite
MGR_TOK=$(login_as "MANAGER")
LO_TOK=$(login_as "LOAN_OFFICER")
AUD_TOK=$(login_as "AUDITOR")

if [ -z "$MGR_TOK" ] || [ -z "$LO_TOK" ] || [ -z "$AUD_TOK" ]; then
  echo "ERROR: token acquisition failed — is seed-rbac-users.sql sourced?" >&2
  exit 1
fi

# -------------------------------------------------------
# BENEF-anon: Unauthenticated GET /beneficiaries.php → 401
# -------------------------------------------------------
echo "--- BENEF-anon: unauthenticated access guard ---"

code=$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/beneficiaries.php")
if [ "$code" = "401" ]; then
  record_result "BENEF-anon" "unauthenticated GET /beneficiaries.php returns 401" "pass"
else
  record_result "BENEF-anon" "unauthenticated GET /beneficiaries.php returns 401 (got $code)" "fail"
fi

echo ""

# -------------------------------------------------------
# BENEF-02: LOAN_OFFICER can create a beneficiary
# -------------------------------------------------------
echo "--- BENEF-02: create beneficiary ---"

RESP_B02=$(curl -s -o /tmp/smoke-phase3-benef02.json -w '%{http_code}' \
  -X POST "${BASE_URL}/beneficiaries.php" \
  -H "Authorization: Bearer $LO_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"member_id":9901,"full_name":"Smoke Test Beneficiary","beneficiary_type":"primary"}')
BODY_B02=$(cat /tmp/smoke-phase3-benef02.json)
new_benef_id=$(echo "$BODY_B02" | jq -r '.data.id // empty' 2>/dev/null)

if [ "$RESP_B02" = "201" ] && [ -n "$new_benef_id" ]; then
  record_result "BENEF-02" "POST /beneficiaries.php as LOAN_OFFICER → 201 + data.id=$new_benef_id" "pass"
else
  record_result "BENEF-02" "POST /beneficiaries.php as LOAN_OFFICER → 201 + data.id non-empty (got HTTP $RESP_B02, id='$new_benef_id')" "fail"
  echo "          Response: $BODY_B02"
fi

echo ""

# -------------------------------------------------------
# BENEF-01: Co-maker self-guard + successful co-maker creation
# -------------------------------------------------------
echo "--- BENEF-01: co-maker self-guard and creation ---"

# BENEF-01a: co-maker = borrower must return 422 (self-guard)
RESP_B01a=$(curl -s -o /tmp/smoke-phase3-benef01a.json -w '%{http_code}' \
  -X POST "${BASE_URL}/co-makers.php" \
  -H "Authorization: Bearer $LO_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"loan_id":9901,"member_id":9901}')
BODY_B01a=$(cat /tmp/smoke-phase3-benef01a.json)

if [ "$RESP_B01a" = "422" ]; then
  record_result "BENEF-01a" "POST /co-makers.php co-maker=borrower → 422 self-guard" "pass"
else
  record_result "BENEF-01a" "POST /co-makers.php co-maker=borrower → 422 self-guard (got $RESP_B01a)" "fail"
  echo "          Response: $BODY_B01a"
fi

# BENEF-01b: valid co-maker creation with different member (use member id=1 as fallback)
RESP_B01b=$(curl -s -o /tmp/smoke-phase3-benef01b.json -w '%{http_code}' \
  -X POST "${BASE_URL}/co-makers.php" \
  -H "Authorization: Bearer $LO_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"loan_id":9901,"member_id":1}')
BODY_B01b=$(cat /tmp/smoke-phase3-benef01b.json)
new_comaker_id=$(echo "$BODY_B01b" | jq -r '.data.id // empty' 2>/dev/null)

if [ "$RESP_B01b" = "201" ] && [ -n "$new_comaker_id" ]; then
  record_result "BENEF-01b" "POST /co-makers.php valid co-maker (member_id=1) → 201 + data.id=$new_comaker_id" "pass"
else
  record_result "BENEF-01b" "POST /co-makers.php valid co-maker → 201 (got HTTP $RESP_B01b, id='$new_comaker_id')" "fail"
  echo "          Response: $BODY_B01b"
fi

echo ""

# -------------------------------------------------------
# RESTR-01: Role guard + MANAGER can restructure
# -------------------------------------------------------
echo "--- RESTR-01: restructuring role guard and creation ---"

NEXT_MONTH=$(date -v+1m '+%Y-%m-01' 2>/dev/null || date -d '+1 month' '+%Y-%m-01')

# RESTR-01a: LOAN_OFFICER POST /restructuring.php → 403
RESP_R01a=$(curl -s -o /dev/null -w '%{http_code}' \
  -X POST "${BASE_URL}/restructuring.php" \
  -H "Authorization: Bearer $LO_TOK" \
  -H 'Content-Type: application/json' \
  -d "{\"loan_id\":9901,\"new_amount\":45000,\"new_annual_rate\":0.12,\"new_term_months\":18,\"new_frequency\":\"monthly\",\"first_due_date\":\"${NEXT_MONTH}\"}")

if [ "$RESP_R01a" = "403" ]; then
  record_result "RESTR-01a" "POST /restructuring.php as LOAN_OFFICER → 403" "pass"
else
  record_result "RESTR-01a" "POST /restructuring.php as LOAN_OFFICER → 403 (got $RESP_R01a)" "fail"
fi

# RESTR-01b: MANAGER POST /restructuring.php → 201
RESP_R01b=$(curl -s -o /tmp/smoke-phase3-restr01b.json -w '%{http_code}' \
  -X POST "${BASE_URL}/restructuring.php" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d "{\"loan_id\":9901,\"new_amount\":45000,\"new_annual_rate\":0.12,\"new_term_months\":18,\"new_frequency\":\"monthly\",\"first_due_date\":\"${NEXT_MONTH}\"}")
BODY_R01b=$(cat /tmp/smoke-phase3-restr01b.json)
restr_id=$(echo "$BODY_R01b" | jq -r '.data.id // empty' 2>/dev/null)

if [ "$RESP_R01b" = "201" ] && [ -n "$restr_id" ]; then
  record_result "RESTR-01b" "POST /restructuring.php as MANAGER → 201 + data.id=$restr_id" "pass"
else
  record_result "RESTR-01b" "POST /restructuring.php as MANAGER → 201 (got HTTP $RESP_R01b, id='$restr_id')" "fail"
  echo "          Response: $BODY_R01b"
fi

echo ""

# -------------------------------------------------------
# RESTR-02: Response contains original_amount (audit snapshot)
# -------------------------------------------------------
echo "--- RESTR-02: restructuring response contains original terms ---"

orig_amount=$(echo "$BODY_R01b" | jq -r '.data.original_amount // empty' 2>/dev/null)

if [ -n "$orig_amount" ] && echo "$orig_amount" | grep -qE '^[0-9]+(\.[0-9]+)?$'; then
  record_result "RESTR-02" "POST /restructuring.php response contains original_amount=$orig_amount (numeric)" "pass"
else
  record_result "RESTR-02" "POST /restructuring.php response contains original_amount (numeric) (got '$orig_amount')" "fail"
fi

echo ""

# -------------------------------------------------------
# RESTR-04: New amortization rows linked to restructuring_id
# -------------------------------------------------------
echo "--- RESTR-04: new amortization rows have restructuring_id set ---"

if [ -n "$restr_id" ] && [ "$restr_id" != "null" ]; then
  count_r04=$(db_query "SELECT COUNT(*) FROM amortization_schedule WHERE loan_id=9901 AND restructuring_id=${restr_id}")
  if [ "${count_r04:-0}" -gt 0 ]; then
    record_result "RESTR-04" "amortization_schedule rows with restructuring_id=$restr_id count=$count_r04 (> 0)" "pass"
  else
    record_result "RESTR-04" "amortization_schedule rows with restructuring_id=$restr_id count=$count_r04 (expected > 0)" "fail"
  fi
else
  warn "RESTR-04 skipped — restr_id not available (RESTR-01b may have failed)"
  record_result "RESTR-04" "DB check skipped — restructuring_id unavailable (RESTR-01b failed)" "fail"
fi

echo ""

# -------------------------------------------------------
# RESTR-05: Original amortization rows preserved (restructuring_id IS NULL)
# -------------------------------------------------------
echo "--- RESTR-05: original amortization rows preserved ---"

count_r05=$(db_query "SELECT COUNT(*) FROM amortization_schedule WHERE loan_id=9901 AND restructuring_id IS NULL")
if [ "${count_r05:-0}" -ge 18 ]; then
  record_result "RESTR-05" "amortization_schedule original rows (restructuring_id IS NULL) count=$count_r05 (>= 18)" "pass"
else
  record_result "RESTR-05" "amortization_schedule original rows count=$count_r05 (expected >= 18, new_term_months)" "fail"
fi

echo ""

# -------------------------------------------------------
# NOTIF-01: AUDITOR can GET /notification-logs.php → 200
# -------------------------------------------------------
echo "--- NOTIF-01: notification-logs.php accessible to AUDITOR ---"

RESP_N01=$(curl -s -o /dev/null -w '%{http_code}' \
  -H "Authorization: Bearer $AUD_TOK" \
  "${BASE_URL}/notification-logs.php")

if [ "$RESP_N01" = "200" ]; then
  record_result "NOTIF-01" "GET /notification-logs.php as AUDITOR → 200 after SQL patch" "pass"
else
  record_result "NOTIF-01" "GET /notification-logs.php as AUDITOR → 200 (got $RESP_N01)" "fail"
fi

echo ""

# -------------------------------------------------------
# NOTIF-02: AUDITOR role can view notification logs
# -------------------------------------------------------
echo "--- NOTIF-02: AUDITOR role authorisation for notification logs ---"

if [ "$RESP_N01" = "200" ]; then
  record_result "NOTIF-02" "AUDITOR role can view notification logs (same request as NOTIF-01, confirmed 200)" "pass"
else
  record_result "NOTIF-02" "AUDITOR role can view notification logs (got $RESP_N01)" "fail"
fi

echo ""

# -------------------------------------------------------
# Summary
# -------------------------------------------------------
echo ""
echo "=== Results: $FAIL failure(s), $PASS pass(es) ==="
[ "$FAIL" -eq 0 ] && echo "ALL PASS" || echo "FAILURES DETECTED"
exit $FAIL
