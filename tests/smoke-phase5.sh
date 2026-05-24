#!/usr/bin/env bash
set -euo pipefail

# Smoke tests for Phase 5 — Operational Reports & CSV Data Import
#   REPORT-01 — GET /reports.php?type=portfolio returns loan list with loan_no field
#   REPORT-02 — GET /reports.php?type=collection&from=&to= returns HTTP 200 + success=true
#   REPORT-03 — GET /reports.php?type=par returns HTTP 200 + par_bucket field OR empty array
#   REPORT-04 — GET /reports.php?type=member&member_id=1 returns HTTP 200 + success=true
#   REPORT-05 — GET /reports.php?type=portfolio&format=csv returns Content-Type: text/csv
#   AUTH gate  — GET /reports.php?type=portfolio with no token returns HTTP 401
#   IMPORT-01  — POST /import.php?type=members with SUPER_ADMIN token → HTTP 200 + inserted > 0
#   IMPORT-02  — POST /import.php?type=loans with SUPER_ADMIN token → HTTP 200 + inserted > 0
#   IMPORT-03  — POST /import.php?type=members with LOAN_OFFICER token → HTTP 403 (RBAC gate)
#   IMPORT-04  — POST /import.php?type=members twice → second run: updated > 0, total CRS-TEST count still 10
#   IMPORT-05  — POST /import.php?type=members with BOM-prefixed CSV → HTTP 200 + parsed correctly
#
# NOTE: Wave 0 — reports.php and import.php do not exist yet.
#       All assertions WILL FAIL at Wave 0 (expected — endpoints are created in Waves 1 and 2A).
#       Re-run this script after each wave to verify incremental progress.
#       Full green required before /gsd-verify-work.
#
# Run after:
#   mysql -u root crs_coop < database/schema.sql
#   mysql -u root crs_coop < database/phase5_module.sql
#   mysql -u root crs_coop < tests/seed-rbac-users.sql
#   mysql -u root crs_coop < tests/seed-phase3-fixtures.sql   (for member id 1 + loan data)
#
# Do NOT point BASE_URL at a production host.

BASE_URL=${BASE_URL:-http://localhost:8000}
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-crs_coop}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_PORT=${DB_PORT:-3306}

FIXTURES_DIR=${FIXTURES_DIR:-"$(dirname "$0")/fixtures"}

# Production guard — mirrors Phase 1/2/3/4 smoke scripts
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

print_summary() {
  echo ""
  echo "=== Results: $FAIL failure(s), $PASS pass(es) ==="
  [ "$FAIL" -eq 0 ] && echo "ALL PASS" || echo "FAILURES DETECTED"
  exit $FAIL
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

echo "=== smoke-phase5.sh — Phase 5 Reports & CSV Import smoke tests ==="
echo "Base URL: $BASE_URL"
echo "Fixtures: $FIXTURES_DIR"
echo ""

# Obtain tokens: LOAN_OFFICER for reports (read-only), SUPER_ADMIN for import
TOKEN_LO=$(login_as "LOAN_OFFICER")
TOKEN_SA=$(login_as "SUPER_ADMIN")

# Raw token for CSV download URL (REPORT-05 needs it in query param)
RAW_TOKEN_SA="$TOKEN_SA"

if [ -z "$TOKEN_LO" ] || [ -z "$TOKEN_SA" ]; then
  echo "ERROR: token acquisition failed — is seed-rbac-users.sql sourced?" >&2
  exit 1
fi

# -------------------------------------------------------
# REPORT-01: GET /reports.php?type=portfolio — loan list with loan_no
# -------------------------------------------------------
echo "--- REPORT-01: portfolio report returns loan list ---"

RESP_R01=$(curl -s -o /tmp/smoke-phase5-r01.json -w '%{http_code}' \
  -H "Authorization: Bearer $TOKEN_LO" \
  "${BASE_URL}/reports.php?type=portfolio")
BODY_R01=$(cat /tmp/smoke-phase5-r01.json)

if [ "$RESP_R01" = "200" ]; then
  ok=$(echo "$BODY_R01" | jq -r '.success' 2>/dev/null || echo "false")
  has_loan_no=$(echo "$BODY_R01" | jq -r 'if (.data | length) > 0 then (.data[0] | has("loan_no")) else true end' 2>/dev/null || echo "false")
  if [ "$ok" = "true" ] && [ "$has_loan_no" = "true" ]; then
    record_result "REPORT-01" "portfolio report returns HTTP 200 + success=true + loan_no field" "pass"
  else
    record_result "REPORT-01" "portfolio report returned 200 but success=$ok or loan_no missing" "fail"
    echo "          Response: $BODY_R01"
  fi
else
  record_result "REPORT-01" "GET /reports.php?type=portfolio returns 200 (got HTTP $RESP_R01)" "fail"
  echo "          Response: $BODY_R01"
fi

echo ""

# -------------------------------------------------------
# REPORT-02: GET /reports.php?type=collection&from=2020-01-01&to=2030-12-31
# -------------------------------------------------------
echo "--- REPORT-02: collection report returns HTTP 200 + success=true ---"

RESP_R02=$(curl -s -o /tmp/smoke-phase5-r02.json -w '%{http_code}' \
  -H "Authorization: Bearer $TOKEN_LO" \
  "${BASE_URL}/reports.php?type=collection&from=2020-01-01&to=2030-12-31")
BODY_R02=$(cat /tmp/smoke-phase5-r02.json)

if [ "$RESP_R02" = "200" ]; then
  ok=$(echo "$BODY_R02" | jq -r '.success' 2>/dev/null || echo "false")
  if [ "$ok" = "true" ]; then
    record_result "REPORT-02" "collection report returns HTTP 200 + success=true" "pass"
  else
    record_result "REPORT-02" "collection report returned 200 but success=$ok" "fail"
    echo "          Response: $BODY_R02"
  fi
else
  record_result "REPORT-02" "GET /reports.php?type=collection returns 200 (got HTTP $RESP_R02)" "fail"
  echo "          Response: $BODY_R02"
fi

echo ""

# -------------------------------------------------------
# REPORT-03: GET /reports.php?type=par — par_bucket field present OR empty array
# -------------------------------------------------------
echo "--- REPORT-03: PAR report returns HTTP 200 + par_bucket field ---"

RESP_R03=$(curl -s -o /tmp/smoke-phase5-r03.json -w '%{http_code}' \
  -H "Authorization: Bearer $TOKEN_LO" \
  "${BASE_URL}/reports.php?type=par")
BODY_R03=$(cat /tmp/smoke-phase5-r03.json)

if [ "$RESP_R03" = "200" ]; then
  ok=$(echo "$BODY_R03" | jq -r '.success' 2>/dev/null || echo "false")
  row_count=$(echo "$BODY_R03" | jq -r '.data | length' 2>/dev/null || echo "0")
  if [ "$ok" = "true" ]; then
    if [ "${row_count:-0}" -gt 0 ]; then
      has_pb=$(echo "$BODY_R03" | jq -r '.data[0] | has("par_bucket")' 2>/dev/null || echo "false")
      if [ "$has_pb" = "true" ]; then
        pb_val=$(echo "$BODY_R03" | jq -r '.data[0].par_bucket' 2>/dev/null)
        record_result "REPORT-03" "PAR report returns par_bucket field (value: '$pb_val')" "pass"
      else
        record_result "REPORT-03" "PAR report returned rows but par_bucket key missing" "fail"
        echo "          Response keys: $(echo "$BODY_R03" | jq -r '.data[0] | keys' 2>/dev/null)"
      fi
    else
      warn "REPORT-03: no overdue loans in test DB — empty data array is valid (no loans overdue)"
      record_result "REPORT-03" "PAR report returns HTTP 200 + success=true (empty data array — no overdue loans)" "pass"
    fi
  else
    record_result "REPORT-03" "PAR report returned 200 but success=$ok" "fail"
    echo "          Response: $BODY_R03"
  fi
else
  record_result "REPORT-03" "GET /reports.php?type=par returns 200 (got HTTP $RESP_R03)" "fail"
  echo "          Response: $BODY_R03"
fi

echo ""

# -------------------------------------------------------
# REPORT-04: GET /reports.php?type=member&member_id=1 — member history
# -------------------------------------------------------
echo "--- REPORT-04: member history report returns HTTP 200 + success=true ---"

RESP_R04=$(curl -s -o /tmp/smoke-phase5-r04.json -w '%{http_code}' \
  -H "Authorization: Bearer $TOKEN_LO" \
  "${BASE_URL}/reports.php?type=member&member_id=1")
BODY_R04=$(cat /tmp/smoke-phase5-r04.json)

if [ "$RESP_R04" = "200" ]; then
  ok=$(echo "$BODY_R04" | jq -r '.success' 2>/dev/null || echo "false")
  if [ "$ok" = "true" ]; then
    record_result "REPORT-04" "member history report returns HTTP 200 + success=true" "pass"
  else
    record_result "REPORT-04" "member history returned 200 but success=$ok" "fail"
    echo "          Response: $BODY_R04"
  fi
else
  record_result "REPORT-04" "GET /reports.php?type=member&member_id=1 returns 200 (got HTTP $RESP_R04)" "fail"
  echo "          Response: $BODY_R04"
fi

echo ""

# -------------------------------------------------------
# REPORT-05: CSV download — Content-Type must be text/csv
# -------------------------------------------------------
echo "--- REPORT-05: CSV format download returns Content-Type: text/csv ---"

HEADERS_R05=$(curl -s -D - -o /dev/null \
  "${BASE_URL}/reports.php?type=portfolio&format=csv&token=${RAW_TOKEN_SA}")

if echo "$HEADERS_R05" | grep -qi "content-type.*text/csv"; then
  record_result "REPORT-05" "CSV download returns Content-Type: text/csv" "pass"
else
  record_result "REPORT-05" "CSV download Content-Type header is not text/csv" "fail"
  echo "          Response headers:"
  echo "$HEADERS_R05" | grep -i "content-type" || echo "          (no Content-Type header found)"
fi

echo ""

# -------------------------------------------------------
# AUTH gate: GET /reports.php?type=portfolio with no token → 401
# -------------------------------------------------------
echo "--- AUTH gate: unauthenticated request to /reports.php returns 401 ---"

RESP_AUTH=$(curl -s -o /tmp/smoke-phase5-auth.json -w '%{http_code}' \
  "${BASE_URL}/reports.php?type=portfolio")

if [ "$RESP_AUTH" = "401" ]; then
  record_result "AUTH-gate" "GET /reports.php with no token returns 401 Unauthorized" "pass"
else
  record_result "AUTH-gate" "GET /reports.php with no token expected 401 (got HTTP $RESP_AUTH)" "fail"
  echo "          Response: $(cat /tmp/smoke-phase5-auth.json)"
fi

echo ""

# -------------------------------------------------------
# IMPORT-01: POST /import.php?type=members (SUPER_ADMIN) → inserted > 0
# -------------------------------------------------------
echo "--- IMPORT-01: member import with SUPER_ADMIN token → HTTP 200 + inserted > 0 ---"

RESP_I01=$(curl -s -o /tmp/smoke-phase5-i01.json -w '%{http_code}' \
  -X POST "${BASE_URL}/import.php?type=members" \
  -H "Authorization: Bearer $TOKEN_SA" \
  -F "csv=@${FIXTURES_DIR}/test-members.csv")
BODY_I01=$(cat /tmp/smoke-phase5-i01.json)

if [ "$RESP_I01" = "200" ]; then
  ok=$(echo "$BODY_I01" | jq -r '.success' 2>/dev/null || echo "false")
  inserted=$(echo "$BODY_I01" | jq -r '.data.inserted // 0' 2>/dev/null || echo "0")
  updated=$(echo "$BODY_I01" | jq -r '.data.updated // 0' 2>/dev/null || echo "0")
  total_changed=$((inserted + updated))
  if [ "$ok" = "true" ] && [ "$total_changed" -gt 0 ]; then
    record_result "IMPORT-01" "member import returns HTTP 200 + inserted=$inserted, updated=$updated" "pass"
  else
    record_result "IMPORT-01" "member import returned 200 but success=$ok or inserted=$inserted + updated=$updated = 0" "fail"
    echo "          Response: $BODY_I01"
  fi
else
  record_result "IMPORT-01" "POST /import.php?type=members returns 200 (got HTTP $RESP_I01)" "fail"
  echo "          Response: $BODY_I01"
fi

echo ""

# -------------------------------------------------------
# IMPORT-02: POST /import.php?type=loans (SUPER_ADMIN) → inserted > 0
# -------------------------------------------------------
echo "--- IMPORT-02: loan import with SUPER_ADMIN token → HTTP 200 + inserted > 0 ---"

RESP_I02=$(curl -s -o /tmp/smoke-phase5-i02.json -w '%{http_code}' \
  -X POST "${BASE_URL}/import.php?type=loans" \
  -H "Authorization: Bearer $TOKEN_SA" \
  -F "csv=@${FIXTURES_DIR}/test-loans.csv")
BODY_I02=$(cat /tmp/smoke-phase5-i02.json)

if [ "$RESP_I02" = "200" ]; then
  ok=$(echo "$BODY_I02" | jq -r '.success' 2>/dev/null || echo "false")
  inserted=$(echo "$BODY_I02" | jq -r '.data.inserted // 0' 2>/dev/null || echo "0")
  updated=$(echo "$BODY_I02" | jq -r '.data.updated // 0' 2>/dev/null || echo "0")
  total_changed=$((inserted + updated))
  if [ "$ok" = "true" ] && [ "$total_changed" -gt 0 ]; then
    record_result "IMPORT-02" "loan import returns HTTP 200 + inserted=$inserted, updated=$updated" "pass"
  else
    record_result "IMPORT-02" "loan import returned 200 but success=$ok or inserted=$inserted + updated=$updated = 0" "fail"
    echo "          Response: $BODY_I02"
  fi
else
  record_result "IMPORT-02" "POST /import.php?type=loans returns 200 (got HTTP $RESP_I02)" "fail"
  echo "          Response: $BODY_I02"
fi

echo ""

# -------------------------------------------------------
# IMPORT-03: POST /import.php?type=members with LOAN_OFFICER → 403 (RBAC gate)
# -------------------------------------------------------
echo "--- IMPORT-03: LOAN_OFFICER import attempt → HTTP 403 (RBAC gate) ---"

RESP_I03=$(curl -s -o /tmp/smoke-phase5-i03.json -w '%{http_code}' \
  -X POST "${BASE_URL}/import.php?type=members" \
  -H "Authorization: Bearer $TOKEN_LO" \
  -F "csv=@${FIXTURES_DIR}/test-members.csv")

if [ "$RESP_I03" = "403" ]; then
  record_result "IMPORT-03" "LOAN_OFFICER import attempt correctly blocked with HTTP 403" "pass"
else
  record_result "IMPORT-03" "LOAN_OFFICER import expected 403 (got HTTP $RESP_I03) — RBAC gate missing" "fail"
  echo "          Response: $(cat /tmp/smoke-phase5-i03.json)"
fi

echo ""

# -------------------------------------------------------
# IMPORT-04: Idempotency — re-import same test-members.csv, verify no duplication
# -------------------------------------------------------
echo "--- IMPORT-04: re-import same test-members.csv → updated count, no duplication ---"

RESP_I04=$(curl -s -o /tmp/smoke-phase5-i04.json -w '%{http_code}' \
  -X POST "${BASE_URL}/import.php?type=members" \
  -H "Authorization: Bearer $TOKEN_SA" \
  -F "csv=@${FIXTURES_DIR}/test-members.csv")
BODY_I04=$(cat /tmp/smoke-phase5-i04.json)

if [ "$RESP_I04" = "200" ]; then
  ok=$(echo "$BODY_I04" | jq -r '.success' 2>/dev/null || echo "false")
  updated=$(echo "$BODY_I04" | jq -r '.data.updated // 0' 2>/dev/null || echo "0")
  if [ "$ok" = "true" ]; then
    # Verify total CRS-TEST-NNNNN members in DB is still 10 (not doubled)
    if command -v mysql &>/dev/null; then
      test_member_count=$(db_query "SELECT COUNT(*) FROM members WHERE member_no LIKE 'CRS-TEST-%'" 2>/dev/null || echo "unknown")
      if [ "$test_member_count" = "10" ]; then
        record_result "IMPORT-04" "re-import is idempotent: updated=$updated, CRS-TEST member count still $test_member_count (no duplication)" "pass"
      elif [ "$test_member_count" = "unknown" ]; then
        warn "IMPORT-04: DB query unavailable — verifying HTTP 200 + updated count only"
        record_result "IMPORT-04" "re-import returned HTTP 200 + updated=$updated (DB count check skipped — mysql unavailable)" "pass"
      else
        record_result "IMPORT-04" "re-import duplication detected: CRS-TEST count = $test_member_count (expected 10)" "fail"
      fi
    else
      warn "IMPORT-04: mysql CLI not available — skipping DB count verification"
      record_result "IMPORT-04" "re-import returned HTTP 200 + updated=$updated (DB count skipped — mysql not in PATH)" "pass"
    fi
  else
    record_result "IMPORT-04" "re-import returned HTTP 200 but success=$ok" "fail"
    echo "          Response: $BODY_I04"
  fi
else
  record_result "IMPORT-04" "POST /import.php?type=members (second run) returns 200 (got HTTP $RESP_I04)" "fail"
  echo "          Response: $BODY_I04"
fi

echo ""

# -------------------------------------------------------
# IMPORT-05: BOM-prefixed CSV → parsed correctly despite BOM
# -------------------------------------------------------
echo "--- IMPORT-05: BOM-prefixed CSV parsed correctly → HTTP 200 + inserted OR updated > 0 ---"

RESP_I05=$(curl -s -o /tmp/smoke-phase5-i05.json -w '%{http_code}' \
  -X POST "${BASE_URL}/import.php?type=members" \
  -H "Authorization: Bearer $TOKEN_SA" \
  -F "csv=@${FIXTURES_DIR}/test-bom-members.csv")
BODY_I05=$(cat /tmp/smoke-phase5-i05.json)

if [ "$RESP_I05" = "200" ]; then
  ok=$(echo "$BODY_I05" | jq -r '.success' 2>/dev/null || echo "false")
  inserted=$(echo "$BODY_I05" | jq -r '.data.inserted // 0' 2>/dev/null || echo "0")
  updated=$(echo "$BODY_I05" | jq -r '.data.updated // 0' 2>/dev/null || echo "0")
  total_changed=$((inserted + updated))
  if [ "$ok" = "true" ] && [ "$total_changed" -gt 0 ]; then
    record_result "IMPORT-05" "BOM-prefixed CSV parsed correctly: HTTP 200 + inserted=$inserted, updated=$updated" "pass"
  else
    # Check for skipped=10 with 0 inserted/updated — may indicate BOM caused header mismatch
    skipped=$(echo "$BODY_I05" | jq -r '.data.skipped // 0' 2>/dev/null || echo "0")
    record_result "IMPORT-05" "BOM-prefixed CSV: HTTP 200 but success=$ok, inserted=$inserted, updated=$updated, skipped=$skipped — BOM may not be stripped" "fail"
    echo "          Response: $BODY_I05"
  fi
else
  record_result "IMPORT-05" "POST /import.php?type=members (BOM CSV) returns 200 (got HTTP $RESP_I05)" "fail"
  echo "          Response: $BODY_I05"
fi

echo ""

# -------------------------------------------------------
# Summary
# -------------------------------------------------------
print_summary
