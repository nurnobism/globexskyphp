#!/usr/bin/env bash
# =============================================================
# GlobexSky — Automated Deployment Script
# Usage: bash deploy/setup.sh
# Target: Namecheap Shared Hosting (Apache, cPanel, no root)
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
cd "$ROOT_DIR"

echo -e "${BOLD}${CYAN}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║       GlobexSky Deployment Setup         ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${RESET}"

# ── Step 1: .env file ─────────────────────────────────────────
step "Step 1/7 — Environment file"
if [ ! -f "$ROOT_DIR/.env" ]; then
    if [ -f "$ROOT_DIR/.env.example" ]; then
        cp "$ROOT_DIR/.env.example" "$ROOT_DIR/.env"
        ok ".env created from .env.example"
        warn "Edit .env now and fill in real credentials before continuing."
        warn "Use deploy/production.env.template as a reference."
    else
        err ".env.example not found — cannot create .env"
        exit 1
    fi
else
    ok ".env already exists — skipping copy"
fi

# ── Step 2: Set production values in .env ────────────────────
step "Step 2/7 — Enforce production settings in .env"
sed -i 's/^APP_ENV=.*/APP_ENV=production/' "$ROOT_DIR/.env"
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' "$ROOT_DIR/.env"
ok "APP_ENV=production and APP_DEBUG=false applied"

# ── Step 3: Composer install ──────────────────────────────────
step "Step 3/7 — PHP dependencies (composer)"
if command -v composer &>/dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction \
        --working-dir="$ROOT_DIR"
    ok "composer install completed"
else
    warn "composer not found — skipping PHP dependency install"
    warn "Run manually: composer install --no-dev --optimize-autoloader"
fi

# ── Step 4: Create upload / storage directories ───────────────
step "Step 4/7 — Upload & storage directories"
DIRS=(
    "uploads/kyc"
    "uploads/products"
    "uploads/avatars"
    "storage"
    "storage/logs"
    "storage/cache"
    "storage/sessions"
)
for dir in "${DIRS[@]}"; do
    if [ ! -d "$ROOT_DIR/$dir" ]; then
        mkdir -p "$ROOT_DIR/$dir"
        ok "Created $dir"
    else
        info "$dir already exists"
    fi
    chmod 750 "$ROOT_DIR/$dir"
done
ok "Directory permissions set to 750"

# ── Step 5: Node.js dependencies ─────────────────────────────
step "Step 5/7 — Node.js dependencies"
if [ -d "$ROOT_DIR/nodejs" ]; then
    if command -v npm &>/dev/null; then
        (cd "$ROOT_DIR/nodejs" && npm install --production --no-audit)
        ok "npm install --production completed"
    else
        warn "npm not found — skipping Node.js dependency install"
        warn "Run manually: cd nodejs && npm install --production"
    fi
else
    warn "nodejs/ directory not found — skipping"
fi

# ── Step 6: Database migration reminder ───────────────────────
step "Step 6/7 — Database migration"
warn "Database import must be done manually. Run:"
echo ""
echo "  bash deploy/database-setup.sh"
echo ""
warn "Or import via cPanel → phpMyAdmin."

# ── Step 7: Checklist summary ────────────────────────────────
step "Step 7/7 — Deployment checklist"

ENV_FILE="$ROOT_DIR/.env"
check_env() {
    local key="$1"
    if grep -q "^${key}=.\+" "$ENV_FILE" 2>/dev/null; then
        ok "$key is set"
    else
        err "$key is EMPTY — set a real value in .env"
    fi
}

check_dir_writable() {
    if [ -w "$ROOT_DIR/$1" ]; then
        ok "$1 is writable"
    else
        err "$1 is NOT writable — run: chmod 750 $1"
    fi
}

echo ""
info "Checking critical .env values …"
check_env "DB_HOST"
check_env "DB_NAME"
check_env "DB_USER"
check_env "DB_PASS"
check_env "MAIL_HOST"
check_env "MAIL_USERNAME"
check_env "MAIL_PASSWORD"
check_env "STRIPE_SECRET_KEY"
check_env "STRIPE_PUBLISHABLE_KEY"
check_env "JWT_SECRET"
check_env "INTERNAL_API_KEY"
check_env "ADMIN_PASSWORD"

echo ""
info "Checking directory permissions …"
for dir in "uploads/kyc" "uploads/products" "uploads/avatars" "storage"; do
    check_dir_writable "$dir"
done

echo ""
if grep -q "^APP_ENV=production" "$ENV_FILE" 2>/dev/null; then
    ok "APP_ENV=production ✔"
else
    err "APP_ENV is NOT production"
fi
if grep -q "^APP_DEBUG=false" "$ENV_FILE" 2>/dev/null; then
    ok "APP_DEBUG=false ✔"
else
    err "APP_DEBUG is NOT false — NEVER expose debug output in production"
fi

echo ""
echo -e "${BOLD}${GREEN}═══════════════════════════════════════════════════${RESET}"
echo -e "${BOLD}${GREEN}  Deployment setup complete!${RESET}"
echo -e "${GREEN}  Next steps:${RESET}"
echo -e "  1. Fill in ALL empty values in .env"
echo -e "  2. Run: bash deploy/database-setup.sh"
echo -e "  3. Set cron jobs per deploy/cron-setup.md"
echo -e "  4. Configure Node.js per deploy/nodejs-setup.md"
echo -e "  5. Verify SSL per deploy/ssl-checklist.md"
echo -e "  6. Run: php deploy/health-check.php"
echo -e "${BOLD}${GREEN}═══════════════════════════════════════════════════${RESET}"
