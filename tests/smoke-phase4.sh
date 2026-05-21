#!/usr/bin/env bash
set -euo pipefail

# Smoke tests for Phase 4 delinquency tracking:
#   DELINQ-01 — loans list returns days_past_due field
#   DELINQ-02 — loans list returns par_bucket field
#   DELINQ-04 — WITHDRAWAL blocked for member with ACTIVE loan (HTTP 422)
#
# Run after:
#   mysql -u root crs_coop < database/schema.sql
#   mysql -u root crs_coop < tests/seed-rbac-users.sql
#
# NOTE: Wave 0 — backend loan delinquency fields (days_past_due, par_bucket) do not exist yet.
#       DELINQ-01 and DELINQ-02 will FAIL at this stage (expected).
#       DELINQ-04 (share-capital withdrawal guard) will FAIL at this stage (expected).
#       Re-run after Wave 1–3 endpoints are deployed to see full green.
#
# Do NOT point BASE_URL at a production host.

BASE_URL=${BASE_URL:-http://localhost:8000}
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-crs_coop}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_PORT=${DB_PORT:-3306}

# Member ID with an ACTIVE loan — member 9901 is the Phase 3/4 test fixture member
# with loan LN-TEST-P3001 (status=ACTIVE). Override via env var if needed.
DELINQ_MEMBER_ID=${DELINQ_MEMBER_ID:-9901}

# Production guard — mirrors Phase 1/2/3 smoke scripts
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

echo "=== smoke-phase4.sh — Phase 4 Delinquency Tracking smoke tests ==="
echo "Base URL: $BASE_URL"
echo "DELINQ_MEMBER_ID: $DELINQ_MEMBER_ID"
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
# DELINQ-01: GET /loans.php returns days_past_due field
# -------------------------------------------------------
echo "--- DELINQ-01: loans list returns days_past_due field ---"

RESP_D01=$(curl -s -o /tmp/smoke-phase4-delinq01.json -w '%{http_code}' \
  -H "Authorization: Bearer $LO_TOK" \
  "${BASE_URL}/loans.php?status=ACTIVE")
BODY_D01=$(cat /tmp/smoke-phase4-delinq01.json)

# Check response is 200 and data array is present
if [ "$RESP_D01" = "200" ]; then
  # Check if any active loans exist to test the field
  loan_count=$(echo "$BODY_D01" | jq -r '.data | length' 2>/dev/null || echo "0")
  if [ "${loan_count:-0}" -gt 0 ]; then
    # Verify days_past_due key exists in first record (value may be null or integer — both valid)
    has_dpd=$(echo "$BODY_D01" | jq -r '.data[0] | has("days_past_due")' 2>/dev/null || echo "false")
    if [ "$has_dpd" = "true" ]; then
      dpd_val=$(echo "$BODY_D01" | jq -r '.data[0].days_past_due' 2>/dev/null)
      record_result "DELINQ-01" "loans list returns days_past_due field (value: $dpd_val)" "pass"
    else
      record_result "DELINQ-01" "loans list returns days_past_due field (key missing from response)" "fail"
      echo "          Response keys: $(echo "$BODY_D01" | jq -r '.data[0] | keys' 2>/dev/null)"
    fi
  else
    warn "DELINQ-01: no ACTIVE loans found — field presence cannot be verified"
    warn "  Set DELINQ_MEMBER_ID to a member with an ACTIVE loan and re-seed fixtures"
    record_result "DELINQ-01" "loans list returns days_past_due field (skipped — no ACTIVE loans)" "fail"
  fi
else
  record_result "DELINQ-01" "GET /loans.php?status=ACTIVE returns 200 (got HTTP $RESP_D01)" "fail"
  echo "          Response: $BODY_D01"
fi

echo ""

# -------------------------------------------------------
# DELINQ-02: GET /loans.php returns par_bucket field
# -------------------------------------------------------
echo "--- DELINQ-02: loans list returns par_bucket field ---"

# Reuse the same response captured for DELINQ-01
if [ "$RESP_D01" = "200" ]; then
  loan_count=$(echo "$BODY_D01" | jq -r '.data | length' 2>/dev/null || echo "0")
  if [ "${loan_count:-0}" -gt 0 ]; then
    has_pb=$(echo "$BODY_D01" | jq -r '.data[0] | has("par_bucket")' 2>/dev/null || echo "false")
    if [ "$has_pb" = "true" ]; then
      pb_val=$(echo "$BODY_D01" | jq -r '.data[0].par_bucket' 2>/dev/null)
      # Validate par_bucket value matches expected set: "Current", "PAR 30", "PAR 90", "PAR 91+"
      if echo "$pb_val" | grep -qE '^(Current|PAR 30|PAR 90|PAR 91\+)$'; then
        record_result "DELINQ-02" "loans list returns par_bucket field (value: '$pb_val')" "pass"
      else
        record_result "DELINQ-02" "loans list returns par_bucket field with valid value (got: '$pb_val')" "fail"
      fi
    else
      record_result "DELINQ-02" "loans list returns par_bucket field (key missing from response)" "fail"
      echo "          Response keys: $(echo "$BODY_D01" | jq -r '.data[0] | keys' 2>/dev/null)"
    fi
  else
    warn "DELINQ-02: no ACTIVE loans found — field presence cannot be verified"
    record_result "DELINQ-02" "loans list returns par_bucket field (skipped — no ACTIVE loans)" "fail"
  fi
else
  record_result "DELINQ-02" "GET /loans.php?status=ACTIVE returns 200 (got HTTP $RESP_D01 — see DELINQ-01)" "fail"
fi

echo ""

# -------------------------------------------------------
# DELINQ-03: loans response includes par_bucket (enables frontend PAR badges)
# -------------------------------------------------------
echo "--- DELINQ-03: par_bucket field present in loans.php response ---"

LOANS_BODY_D03=$(curl -s -H "Authorization: Bearer $LO_TOK" "$BASE_URL/loans.php")
echo "$LOANS_BODY_D03" | grep -q '"par_bucket"' \
  && record_result "DELINQ-03" "par_bucket field present in loans.php response (enables frontend PAR badges)" "pass" \
  || record_result "DELINQ-03" "par_bucket field missing from loans.php response" "fail"

echo ""

# -------------------------------------------------------
# DELINQ-04: WITHDRAWAL blocked for member with ACTIVE loan — HTTP 422
# -------------------------------------------------------
echo "--- DELINQ-04: WITHDRAWAL blocked for member with ACTIVE loan ---"

RESP_D04=$(curl -s -o /tmp/smoke-phase4-delinq04.json -w '%{http_code}' \
  -X POST "${BASE_URL}/share-capital.php" \
  -H "Authorization: Bearer $MGR_TOK" \
  -H 'Content-Type: application/json' \
  -d "{\"member_id\":${DELINQ_MEMBER_ID},\"type\":\"WITHDRAWAL\",\"amount\":100,\"date\":\"$(date '+%Y-%m-%d')\",\"reference\":\"SMOKE-D04-TEST\",\"source\":\"manual\"}")
BODY_D04=$(cat /tmp/smoke-phase4-delinq04.json)

if [ "$RESP_D04" = "422" ]; then
  # Verify .message contains "active loan" substring
  msg=$(echo "$BODY_D04" | jq -r '.message // empty' 2>/dev/null)
  if echo "$msg" | grep -qi "active loan"; then
    record_result "DELINQ-04" "WITHDRAWAL blocked for member with ACTIVE loan — HTTP 422 + message contains 'active loan'" "pass"
  else
    record_result "DELINQ-04" "WITHDRAWAL blocked — HTTP 422 but message does not contain 'active loan' (got: '$msg')" "fail"
  fi
else
  record_result "DELINQ-04" "WITHDRAWAL blocked for member $DELINQ_MEMBER_ID with ACTIVE loan — expected HTTP 422 (got $RESP_D04)" "fail"
  echo "          Response: $BODY_D04"
  echo "          NOTE: ensure DELINQ_MEMBER_ID=$DELINQ_MEMBER_ID has an ACTIVE loan in the DB"
fi

echo ""

# -------------------------------------------------------
# Summary
# -------------------------------------------------------
echo ""
echo "=== Results: $FAIL failure(s), $PASS pass(es) ==="
[ "$FAIL" -eq 0 ] && echo "ALL PASS" || echo "FAILURES DETECTED"
exit $FAIL
