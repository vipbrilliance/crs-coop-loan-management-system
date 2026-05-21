#!/usr/bin/env bash
set -euo pipefail

# Smoke tests for Phase 2 VALID requirements: VALID-01 through VALID-05
# Run after:
#   mysql -u root crs_coop < tests/seed-rbac-users.sql
#   mysql -u root crs_coop < tests/seed-valid-fixtures.sql
# Do NOT point BASE_URL at a production host.
#
# NOTE: VALID-05b transitions loan 9101 from DRAFT to PENDING (a real DB write).
#       VALID-04b posts a real payment row for loan 9104 period 1.
#       Re-source tests/seed-valid-fixtures.sql before re-running the full suite.

BASE_URL=${BASE_URL:-http://localhost:8000}

# Production guard — mirrors Phase 1 smoke scripts
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

echo "=== smoke-valid.sh — Phase 2 Server-Side Validation smoke tests ==="
echo "Base URL: $BASE_URL"
echo ""

# Obtain tokens for the three roles used in this suite
MGR_TOK=$(login_as "MANAGER")
LO_TOK=$(login_as "LOAN_OFFICER")
STAFF_TOK=$(login_as "STAFF")

if [ -z "$MGR_TOK" ]; then
  echo "ERROR: MANAGER login failed — is seed-rbac-users.sql sourced?" >&2; exit 1
fi
if [ -z "$LO_TOK" ]; then
  echo "ERROR: LOAN_OFFICER login failed — is seed-rbac-users.sql sourced?" >&2; exit 1
fi
if [ -z "$STAFF_TOK" ]; then
  echo "ERROR: STAFF login failed — is seed-rbac-users.sql sourced?" >&2; exit 1
fi

# Loan type 2 constants (Salary / Cash Loan) — keep in sync with seed-valid-fixtures.sql
LOAN_TYPE_ID=2
VALID_AMOUNT=25000
VALID_TERM=12
VALID_FREQ="monthly"

# -------------------------------------------------------
# VALID-01: Required-field validation
# -------------------------------------------------------
echo "--- VALID-01: Required-field validation ---"

# VALID-01a: POST /loans.php with empty body — expect 422 with field-level errors
BODY_01a=$(curl -s -o /tmp/smoke-valid-01a.json -w '%{http_code}' \
  -X POST "${BASE_URL}/loans.php" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d '{}')
RESP_01a=$(cat /tmp/smoke-valid-01a.json)

if [ "$BODY_01a" = "422" ] \
  && echo "$RESP_01a" | jq -e '.errors.member_id'   > /dev/null 2>&1 \
  && echo "$RESP_01a" | jq -e '.errors.loan_type_id' > /dev/null 2>&1 \
  && echo "$RESP_01a" | jq -e '.errors.amount'       > /dev/null 2>&1 \
  && echo "$RESP_01a" | jq -e '.errors.term_months'  > /dev/null 2>&1 \
  && echo "$RESP_01a" | jq -e '.errors.frequency'    > /dev/null 2>&1
then
  record_result "VALID-01a" "POST loans empty body → 422 + required field errors" "pass"
else
  record_result "VALID-01a" "POST loans empty body → 422 + required field errors (got HTTP $BODY_01a)" "fail"
  echo "          Response: $RESP_01a"
fi

# VALID-01b: POST /payments.php with empty body — expect 422 with field-level errors
BODY_01b=$(curl -s -o /tmp/smoke-valid-01b.json -w '%{http_code}' \
  -X POST "${BASE_URL}/payments.php" \
  -H "Authorization: Bearer $LO_TOK" \
  -H 'Content-Type: application/json' \
  -d '{}')
RESP_01b=$(cat /tmp/smoke-valid-01b.json)

if [ "$BODY_01b" = "422" ] \
  && echo "$RESP_01b" | jq -e '.errors.loan_id'      > /dev/null 2>&1 \
  && echo "$RESP_01b" | jq -e '.errors.period_no'    > /dev/null 2>&1 \
  && echo "$RESP_01b" | jq -e '.errors.amount_paid'  > /dev/null 2>&1 \
  && echo "$RESP_01b" | jq -e '.errors.payment_date' > /dev/null 2>&1
then
  record_result "VALID-01b" "POST payments empty body → 422 + required field errors" "pass"
else
  record_result "VALID-01b" "POST payments empty body → 422 + required field errors (got HTTP $BODY_01b)" "fail"
  echo "          Response: $RESP_01b"
fi

echo ""

# -------------------------------------------------------
# VALID-02: Loan amount range check against loan_type limits
# loan_type_id=2 (Salary/Cash Loan): min=5000, max=50000
# -------------------------------------------------------
echo "--- VALID-02: Loan amount range validation ---"

# VALID-02a: amount=1 (below min_amount=5000)
BODY_02a=$(curl -s -o /tmp/smoke-valid-02a.json -w '%{http_code}' \
  -X POST "${BASE_URL}/loans.php" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d "{\"member_id\":9001,\"loan_type_id\":$LOAN_TYPE_ID,\"amount\":1,\"term_months\":$VALID_TERM,\"frequency\":\"$VALID_FREQ\"}")
RESP_02a=$(cat /tmp/smoke-valid-02a.json)
AMT_ERR_02a=$(echo "$RESP_02a" | jq -r '.errors.amount // empty' 2>/dev/null)

if [ "$BODY_02a" = "422" ] && echo "$AMT_ERR_02a" | grep -q "Amount must be between"; then
  record_result "VALID-02a" "POST loans amount=1 (below min) → 422 + 'Amount must be between' error" "pass"
else
  record_result "VALID-02a" "POST loans amount=1 (below min) → 422 + 'Amount must be between' error (got HTTP $BODY_02a, error: '$AMT_ERR_02a')" "fail"
  echo "          Response: $RESP_02a"
fi

# VALID-02b: amount=99999999 (above max_amount=50000)
BODY_02b=$(curl -s -o /tmp/smoke-valid-02b.json -w '%{http_code}' \
  -X POST "${BASE_URL}/loans.php" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d "{\"member_id\":9001,\"loan_type_id\":$LOAN_TYPE_ID,\"amount\":99999999,\"term_months\":$VALID_TERM,\"frequency\":\"$VALID_FREQ\"}")
RESP_02b=$(cat /tmp/smoke-valid-02b.json)

if [ "$BODY_02b" = "422" ] && echo "$RESP_02b" | jq -e '.errors.amount' > /dev/null 2>&1; then
  record_result "VALID-02b" "POST loans amount=99999999 (above max) → 422 + errors.amount exists" "pass"
else
  record_result "VALID-02b" "POST loans amount=99999999 (above max) → 422 + errors.amount exists (got HTTP $BODY_02b)" "fail"
  echo "          Response: $RESP_02b"
fi

echo ""

# -------------------------------------------------------
# VALID-03: Second active loan block
# Loan 9106 (APPROVED, member 9002) transition to ACTIVE must fail
# because member 9002 already has loan 9105 ACTIVE on loan_type_id=2
# -------------------------------------------------------
echo "--- VALID-03: Second active loan block (same loan type) ---"

BODY_03=$(curl -s -o /tmp/smoke-valid-03.json -w '%{http_code}' \
  -X PUT "${BASE_URL}/loans.php?id=9106" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"status":"ACTIVE"}')
RESP_03=$(cat /tmp/smoke-valid-03.json)
MBR_ERR_03=$(echo "$RESP_03" | jq -r '.errors.member_id // empty' 2>/dev/null)

if [ "$BODY_03" = "422" ] && echo "$MBR_ERR_03" | grep -q "Member already has an active"; then
  # Verify no DB write: GET the loan and assert status is still APPROVED
  LOAN_STATUS_03=$(curl -s "${BASE_URL}/loans.php?id=9106" \
    -H "Authorization: Bearer $MGR_TOK" \
    | jq -r '.data.status // empty' 2>/dev/null)
  if [ "$LOAN_STATUS_03" = "APPROVED" ]; then
    record_result "VALID-03" "PUT loan 9106 to ACTIVE → 422 'Member already has an active' + status unchanged (APPROVED)" "pass"
  else
    record_result "VALID-03" "PUT loan 9106 to ACTIVE → 422 but status is '$LOAN_STATUS_03' (expected APPROVED — DB write may have occurred)" "fail"
  fi
else
  record_result "VALID-03" "PUT loan 9106 to ACTIVE → 422 + 'Member already has an active' error (got HTTP $BODY_03, error: '$MBR_ERR_03')" "fail"
  echo "          Response: $RESP_03"
fi

echo ""

# -------------------------------------------------------
# VALID-04: Payment overpayment block
# Loan 9104 period 1: amount_due=12500.00, paid_amount=0.00
# -------------------------------------------------------
echo "--- VALID-04: Payment overpayment block ---"

# VALID-04a: amount_paid=12500.01 (one cent above ceiling) → 422
BODY_04a=$(curl -s -o /tmp/smoke-valid-04a.json -w '%{http_code}' \
  -X POST "${BASE_URL}/payments.php" \
  -H "Authorization: Bearer $LO_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"loan_id":9104,"period_no":1,"amount_paid":12500.01,"payment_date":"2026-05-21"}')
RESP_04a=$(cat /tmp/smoke-valid-04a.json)
PAID_ERR_04a=$(echo "$RESP_04a" | jq -r '.errors.amount_paid // empty' 2>/dev/null)

if [ "$BODY_04a" = "422" ] && echo "$PAID_ERR_04a" | grep -q "Amount exceeds period balance of"; then
  # WARN: no direct DB check here — verify manually if needed
  warn "VALID-04a DB-side check skipped: verify manually that amortization_schedule.paid_amount remains 0.00 for loan 9104 period 1"
  record_result "VALID-04a" "POST payment 12500.01 (1c over ceiling) → 422 'Amount exceeds period balance of'" "pass"
else
  record_result "VALID-04a" "POST payment 12500.01 (1c over ceiling) → 422 'Amount exceeds period balance of' (got HTTP $BODY_04a, error: '$PAID_ERR_04a')" "fail"
  echo "          Response: $RESP_04a"
fi

# VALID-04b: amount_paid=12500.00 exactly (the ceiling) → 200
# NOTE: This test case writes a real payment row to the payments table and
#       updates amortization_schedule.paid_amount. Re-source seed-valid-fixtures.sql
#       before re-running the full suite.
BODY_04b=$(curl -s -o /tmp/smoke-valid-04b.json -w '%{http_code}' \
  -X POST "${BASE_URL}/payments.php" \
  -H "Authorization: Bearer $LO_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"loan_id":9104,"period_no":1,"amount_paid":12500.00,"payment_date":"2026-05-21"}')
RESP_04b=$(cat /tmp/smoke-valid-04b.json)
SUCCESS_04b=$(echo "$RESP_04b" | jq -r '.success // empty' 2>/dev/null)

if [ "$BODY_04b" = "200" ] && [ "$SUCCESS_04b" = "true" ]; then
  record_result "VALID-04b" "POST payment 12500.00 (exact ceiling) → 200 success=true (regression: valid payment accepted)" "pass"
else
  record_result "VALID-04b" "POST payment 12500.00 (exact ceiling) → 200 success=true (got HTTP $BODY_04b, success='$SUCCESS_04b')" "fail"
  echo "          Response: $RESP_04b"
fi

echo ""

# -------------------------------------------------------
# VALID-05: Loan status transition machine
# -------------------------------------------------------
echo "--- VALID-05: Loan status transition machine ---"

# VALID-05a: PUT loan 9101 (DRAFT) to ACTIVE — invalid transition → 422
BODY_05a=$(curl -s -o /tmp/smoke-valid-05a.json -w '%{http_code}' \
  -X PUT "${BASE_URL}/loans.php?id=9101" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"status":"ACTIVE"}')
RESP_05a=$(cat /tmp/smoke-valid-05a.json)
ST_ERR_05a=$(echo "$RESP_05a" | jq -r '.errors.status // empty' 2>/dev/null)

if [ "$BODY_05a" = "422" ] && echo "$ST_ERR_05a" | grep -q "Cannot move from DRAFT to ACTIVE"; then
  # Verify no DB write: GET loan 9101 and assert status is still DRAFT
  LOAN_STATUS_05a=$(curl -s "${BASE_URL}/loans.php?id=9101" \
    -H "Authorization: Bearer $MGR_TOK" \
    | jq -r '.data.status // empty' 2>/dev/null)
  if [ "$LOAN_STATUS_05a" = "DRAFT" ]; then
    record_result "VALID-05a" "PUT loan 9101 DRAFT→ACTIVE → 422 'Cannot move from DRAFT to ACTIVE' + status unchanged (DRAFT)" "pass"
  else
    record_result "VALID-05a" "PUT loan 9101 DRAFT→ACTIVE → 422 but status is '$LOAN_STATUS_05a' (expected DRAFT — DB write may have occurred)" "fail"
  fi
else
  record_result "VALID-05a" "PUT loan 9101 DRAFT→ACTIVE → 422 + 'Cannot move from DRAFT to ACTIVE' (got HTTP $BODY_05a, error: '$ST_ERR_05a')" "fail"
  echo "          Response: $RESP_05a"
fi

# VALID-05b: PUT loan 9101 (DRAFT) to PENDING — valid transition → 200
# NOTE: This writes a real DB update (loan 9101 status → PENDING).
#       Re-source seed-valid-fixtures.sql before re-running the suite.
BODY_05b=$(curl -s -o /tmp/smoke-valid-05b.json -w '%{http_code}' \
  -X PUT "${BASE_URL}/loans.php?id=9101" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"status":"PENDING"}')
RESP_05b=$(cat /tmp/smoke-valid-05b.json)

if [ "$BODY_05b" = "200" ]; then
  record_result "VALID-05b" "PUT loan 9101 DRAFT→PENDING → 200 (regression: valid transition still works)" "pass"
else
  record_result "VALID-05b" "PUT loan 9101 DRAFT→PENDING → 200 (got HTTP $BODY_05b)" "fail"
  echo "          Response: $RESP_05b"
fi

# VALID-05c: PUT loan 9102 (PENDING) to APPROVED as STAFF → 403
# RBAC gate must fire BEFORE the VALID-05 transition machine check
BODY_05c=$(curl -s -o /tmp/smoke-valid-05c.json -w '%{http_code}' \
  -X PUT "${BASE_URL}/loans.php?id=9102" \
  -H "Authorization: Bearer $STAFF_TOK" \
  -H 'Content-Type: application/json' \
  -d '{"status":"APPROVED"}')
RESP_05c=$(cat /tmp/smoke-valid-05c.json)

if [ "$BODY_05c" = "403" ]; then
  record_result "VALID-05c" "STAFF PUT loan 9102 PENDING→APPROVED → 403 (RBAC fires before VALID-05 transition check)" "pass"
else
  record_result "VALID-05c" "STAFF PUT loan 9102 PENDING→APPROVED → 403 (got HTTP $BODY_05c)" "fail"
  echo "          Response: $RESP_05c"
fi

echo ""

# -------------------------------------------------------
# Summary
# -------------------------------------------------------
TOTAL=$((PASS + FAIL))
echo "Total: $TOTAL tests | Passed: $PASS | Failed: $FAIL"

if [ "$FAIL" -eq 0 ]; then
  exit 0
else
  exit 1
fi
