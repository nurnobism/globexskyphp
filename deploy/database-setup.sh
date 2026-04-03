#!/usr/bin/env bash
# =============================================================
# GlobexSky — Database Import Script
# Usage: bash deploy/database-setup.sh
# Target: Namecheap Shared Hosting (no root access)
# =============================================================

set -euo pipefail

# ── Colours ──────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

ok()   { echo -e "${GREEN}  ✔  $*${RESET}"; }
warn() { echo -e "${YELLOW}  ⚠  $*${RESET}"; }
err()  { echo -e "${RED}  ✘  $*${RESET}"; }
info() { echo -e "${CYAN}  →  $*${RESET}"; }
step() { echo -e "\n${BOLD}${CYAN}▶ $*${RESET}"; }

DEPLOY_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$DEPLOY_DIR")"
DB_DIR="$ROOT_DIR/database"

echo -e "${BOLD}${CYAN}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║     GlobexSky Database Setup Script      ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${RESET}"

# ── Verify mysql client is available ─────────────────────────
if ! command -v mysql &>/dev/null; then
    err "mysql client not found."
    warn "On Namecheap shared hosting, use cPanel → phpMyAdmin to import SQL files."
    warn "Import these files IN ORDER:"
    echo ""
    for f in schema.sql schema_v2.sql schema_v3.sql schema_v4.sql schema_v5.sql \
              schema_v7.sql schema_v8.sql schema_v5_kyc.sql schema_v9.sql \
              schema_v10.sql schema_v11.sql seed.sql; do
        [ -f "$DB_DIR/$f" ] && echo "    $DB_DIR/$f"
    done
    exit 1
fi

# ── Read .env or prompt for credentials ───────────────────────
ENV_FILE="$ROOT_DIR/.env"
if [ -f "$ENV_FILE" ]; then
    info "Reading credentials from .env …"
    DB_HOST=$(grep -E '^DB_HOST=' "$ENV_FILE" | cut -d= -f2- | tr -d '"'"'" | xargs)
    DB_PORT=$(grep -E '^DB_PORT=' "$ENV_FILE" | cut -d= -f2- | tr -d '"'"'" | xargs)
    DB_NAME=$(grep -E '^DB_NAME=' "$ENV_FILE" | cut -d= -f2- | tr -d '"'"'" | xargs)
    DB_USER=$(grep -E '^DB_USER=' "$ENV_FILE" | cut -d= -f2- | tr -d '"'"'" | xargs)
    DB_PASS=$(grep -E '^DB_PASS=' "$ENV_FILE" | cut -d= -f2- | tr -d '"'"'" | xargs)
fi

# Prompt if values are still empty / placeholder
ask_if_empty() {
    local var_name="$1"
    local prompt_text="$2"
    local current_val="${!var_name:-}"
    if [ -z "$current_val" ] || echo "$current_val" | grep -qi 'fill_in\|example\|your_'; then
        read -r -p "  $prompt_text: " current_val
        eval "$var_name=\"$current_val\""
    fi
}

ask_if_empty DB_HOST  "Database host   [default: localhost]"
DB_HOST="${DB_HOST:-localhost}"
ask_if_empty DB_PORT  "Database port   [default: 3306]"
DB_PORT="${DB_PORT:-3306}"
ask_if_empty DB_NAME  "Database name"
ask_if_empty DB_USER  "Database user"
if [ -z "${DB_PASS:-}" ]; then
    read -r -s -p "  Database password (hidden): " DB_PASS; echo ""
fi

# ── Test connection before importing ─────────────────────────
step "Testing database connection …"
if ! mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "-p${DB_PASS}" \
       -e "SELECT 1;" "$DB_NAME" &>/dev/null; then
    err "Cannot connect to MySQL with the supplied credentials."
    warn "Check DB_HOST, DB_NAME, DB_USER, DB_PASS in your .env"
    exit 1
fi
ok "Connected to MySQL successfully"

# ── Import SQL files in correct order ────────────────────────
# Schema files must be imported in the order listed below.
# Each migration builds on the previous schema.
SQL_FILES=(
    "schema.sql"          # Core tables (users, products, orders, …)
    "schema_v2.sql"       # v2 additions
    "schema_v3.sql"       # v3 additions
    "schema_v4.sql"       # v4 additions
    "schema_v5.sql"       # Messaging, webmail, notifications
    "schema_v7.sql"       # Dropshipping
    "schema_v8.sql"       # API platform
    "schema_v5_kyc.sql"   # KYC document verification
    "schema_v9.sql"       # Advanced analytics / affiliate
    "schema_v10.sql"      # i18n, multi-currency, PWA
    "schema_v11.sql"      # Security hardening tables
    "seed.sql"            # Optional seed data (admin user, categories)
)

MYSQL_CMD="mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p${DB_PASS} $DB_NAME"
IMPORTED=0
SKIPPED=0
FAILED=0

step "Importing SQL files …"
for sql_file in "${SQL_FILES[@]}"; do
    full_path="$DB_DIR/$sql_file"
    if [ ! -f "$full_path" ]; then
        warn "File not found, skipping: $sql_file"
        ((SKIPPED++)) || true
        continue
    fi

    info "Importing $sql_file …"
    if $MYSQL_CMD < "$full_path"; then
        ok "$sql_file imported successfully"
        ((IMPORTED++)) || true
    else
        err "Failed to import: $sql_file"
        ((FAILED++)) || true
        warn "Check the error above. You may need to run this file manually."
        read -r -p "  Continue with remaining files? [y/N]: " cont
        [[ "$cont" =~ ^[Yy]$ ]] || exit 1
    fi
done

echo ""
echo -e "${BOLD}═══════════════════════════════════════════════════${RESET}"
echo -e "  Imported: ${GREEN}${IMPORTED}${RESET} | Skipped: ${YELLOW}${SKIPPED}${RESET} | Failed: ${RED}${FAILED}${RESET}"
if [ "$FAILED" -eq 0 ]; then
    echo -e "${GREEN}  Database setup complete!${RESET}"
else
    echo -e "${RED}  Database setup finished with errors. Fix and re-run.${RESET}"
fi
echo -e "${BOLD}═══════════════════════════════════════════════════${RESET}"
