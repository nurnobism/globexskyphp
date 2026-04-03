#!/usr/bin/env bash
# =============================================================
# GlobexSky — Rollback Script
# Usage: bash deploy/rollback.sh [commit-sha]
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
BACKUP_DIR="$HOME/globexsky_backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

cd "$ROOT_DIR"

echo -e "${BOLD}${CYAN}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║        GlobexSky Rollback Script         ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${RESET}"

TARGET_SHA="${1:-}"

# ── Show recent commits ───────────────────────────────────────
step "Recent git history (latest 10 commits)"
if command -v git &>/dev/null && [ -d "$ROOT_DIR/.git" ]; then
    git -C "$ROOT_DIR" log --oneline -10
else
    err "Git not found or not a git repository"
    exit 1
fi

# ── Prompt for target if not provided ────────────────────────
if [ -z "$TARGET_SHA" ]; then
    echo ""
    read -r -p "  Enter commit SHA or branch to roll back to (or press Enter to cancel): " TARGET_SHA
    [ -z "$TARGET_SHA" ] && { warn "Rollback cancelled."; exit 0; }
fi

# ── Confirm ───────────────────────────────────────────────────
echo ""
warn "You are about to roll back to: $TARGET_SHA"
warn "This will overwrite the current code on this server."
read -r -p "  Type 'yes' to confirm: " confirm
[ "$confirm" != "yes" ] && { warn "Rollback cancelled."; exit 0; }

# ── Step 1: Database backup ──────────────────────────────────
step "Step 1/4 — Database backup before rollback"
mkdir -p "$BACKUP_DIR"
ENV_FILE="$ROOT_DIR/.env"
if [ -f "$ENV_FILE" ]; then
    DB_HOST=$(grep -E '^DB_HOST=' "$ENV_FILE" | cut -d= -f2- | xargs 2>/dev/null || echo "localhost")
    DB_PORT=$(grep -E '^DB_PORT=' "$ENV_FILE" | cut -d= -f2- | xargs 2>/dev/null || echo "3306")
    DB_NAME=$(grep -E '^DB_NAME=' "$ENV_FILE" | cut -d= -f2- | xargs 2>/dev/null || echo "")
    DB_USER=$(grep -E '^DB_USER=' "$ENV_FILE" | cut -d= -f2- | xargs 2>/dev/null || echo "")
    DB_PASS=$(grep -E '^DB_PASS=' "$ENV_FILE" | cut -d= -f2- | xargs 2>/dev/null || echo "")
fi

BACKUP_SQL="$BACKUP_DIR/db_backup_${TIMESTAMP}.sql"
if command -v mysqldump &>/dev/null && [ -n "${DB_NAME:-}" ]; then
    if mysqldump -h "${DB_HOST:-localhost}" -P "${DB_PORT:-3306}" \
                 -u "${DB_USER:-}" "-p${DB_PASS:-}" \
                 "${DB_NAME:-}" > "$BACKUP_SQL" 2>/dev/null; then
        ok "Database backed up to: $BACKUP_SQL"
    else
        warn "Could not back up database automatically."
        warn "Back up manually via cPanel → phpMyAdmin before continuing."
        read -r -p "  Continue without DB backup? [y/N]: " cont
        [[ "$cont" =~ ^[Yy]$ ]] || exit 1
    fi
else
    warn "mysqldump not found or DB credentials missing — skipping DB backup"
    warn "Back up your database manually BEFORE proceeding."
    read -r -p "  Continue? [y/N]: " cont
    [[ "$cont" =~ ^[Yy]$ ]] || exit 1
fi

# ── Step 2: Code backup ──────────────────────────────────────
step "Step 2/4 — Code snapshot before rollback"
BACKUP_CODE="$BACKUP_DIR/code_backup_${TIMESTAMP}"
mkdir -p "$BACKUP_CODE"
rsync -a --exclude=".git" --exclude="vendor" --exclude="node_modules" \
    "$ROOT_DIR/" "$BACKUP_CODE/" 2>/dev/null || true
ok "Code snapshot saved to: $BACKUP_CODE"

# ── Step 3: Git rollback ──────────────────────────────────────
step "Step 3/4 — Rolling back code to $TARGET_SHA"
git -C "$ROOT_DIR" fetch --all
git -C "$ROOT_DIR" checkout "$TARGET_SHA" -- .
ok "Code rolled back to $TARGET_SHA"

# ── Step 4: Reinstall dependencies ───────────────────────────
step "Step 4/4 — Reinstalling dependencies"
if command -v composer &>/dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction \
        --working-dir="$ROOT_DIR"
    ok "composer install completed"
else
    warn "composer not found — reinstall PHP dependencies manually"
fi

if [ -d "$ROOT_DIR/nodejs" ] && command -v npm &>/dev/null; then
    (cd "$ROOT_DIR/nodejs" && npm install --production --no-audit)
    ok "npm install completed"
fi

echo ""
echo -e "${BOLD}${GREEN}═══════════════════════════════════════════════════${RESET}"
echo -e "${GREEN}  Rollback complete!${RESET}"
echo -e "  Rolled back to: ${BOLD}$TARGET_SHA${RESET}"
echo -e "  DB backup:   $BACKUP_SQL"
echo -e "  Code backup: $BACKUP_CODE"
echo ""
echo -e "  ${YELLOW}NEXT STEPS:${RESET}"
echo -e "  • Check the application is working: php deploy/health-check.php"
echo -e "  • If DB schema changed, you may need to restore the DB backup:"
echo -e "    mysql -u \$DB_USER -p\$DB_PASS \$DB_NAME < $BACKUP_SQL"
echo -e "  • Restart the Node.js server via cPanel → Setup Node.js App"
echo -e "${BOLD}${GREEN}═══════════════════════════════════════════════════${RESET}"
