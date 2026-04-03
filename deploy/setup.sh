#!/usr/bin/env bash
# =============================================================
# GlobexSky — Automated Deployment Script (One-Click Setup)
# Usage: bash deploy/setup.sh [--force-db]
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
FORCE_DB="${1:-}"
cd "$ROOT_DIR"

echo -e "${BOLD}${CYAN}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║       GlobexSky Deployment Setup         ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${RESET}"

# ── Step 1: PHP version check ────────────────────────────────
step "Step 1/10 — PHP version check"
if ! command -v php &>/dev/null; then
    err "PHP not found. Install PHP 8.0+ before running this script."
    exit 1
fi
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;")
PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;")
if [ "$PHP_MAJOR" -ge 8 ]; then
    ok "PHP $PHP_VERSION — OK (>= 8.0 required)"
else
    err "PHP $PHP_VERSION is too old. GlobexSky requires PHP 8.0 or higher."
    exit 1
fi

# Check required PHP extensions
info "Checking required PHP extensions …"
REQUIRED_EXTS=("pdo" "pdo_mysql" "mbstring" "json" "curl" "gd" "openssl" "fileinfo")
MISSING_EXTS=()
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null; then
        ok "Extension: $ext"
    else
        err "Extension missing: $ext"
        MISSING_EXTS+=("$ext")
    fi
done
if [ ${#MISSING_EXTS[@]} -gt 0 ]; then
    warn "Missing extensions: ${MISSING_EXTS[*]}"
    warn "On Namecheap cPanel: MultiPHP INI Editor → enable missing extensions"
fi

# ── Step 2: .env file ─────────────────────────────────────────
step "Step 2/10 — Environment file"
ENV_FILE="$ROOT_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
    if [ -f "$ROOT_DIR/deploy/production.env.template" ]; then
        cp "$ROOT_DIR/deploy/production.env.template" "$ENV_FILE"
        ok ".env created from deploy/production.env.template"
    elif [ -f "$ROOT_DIR/.env.example" ]; then
        cp "$ROOT_DIR/.env.example" "$ENV_FILE"
        ok ".env created from .env.example"
    else
        err "No .env template found — cannot create .env"
        exit 1
    fi
    warn "IMPORTANT: Edit .env now and fill in all <FILL_IN> values before continuing."
    warn "Use deploy/production.env.template as a reference."
    echo ""
    read -r -p "  Press ENTER after editing .env to continue, or Ctrl+C to exit …"
else
    ok ".env already exists — skipping copy"
fi

# Enforce production settings
sed -i 's/^APP_ENV=.*/APP_ENV=production/' "$ENV_FILE"
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' "$ENV_FILE"
ok "APP_ENV=production and APP_DEBUG=false enforced"

# ── Helper: read .env value ───────────────────────────────────
read_env() { grep -E "^$1=" "$ENV_FILE" | cut -d= -f2- | tr -d "\"'" | xargs 2>/dev/null || true; }

DB_HOST=$(read_env DB_HOST)
DB_PORT=$(read_env DB_PORT); DB_PORT="${DB_PORT:-3306}"
DB_NAME=$(read_env DB_NAME)
DB_USER=$(read_env DB_USER)
DB_PASS=$(read_env DB_PASS)
ADMIN_EMAIL=$(read_env ADMIN_EMAIL)
ADMIN_PASSWORD=$(read_env ADMIN_PASSWORD)

# ── Step 3: MySQL connection check ───────────────────────────
step "Step 3/10 — MySQL connection check"
if [ -z "$DB_NAME" ] || echo "$DB_NAME" | grep -qi "fill_in"; then
    warn "DB_NAME not set in .env — skipping MySQL connection check"
else
    # Write temporary MySQL config to avoid password in process listing
    MYSQL_CNF=$(mktemp /tmp/globexsky_mysql_XXXXXX.cnf)
    chmod 600 "$MYSQL_CNF"
    cat > "$MYSQL_CNF" <<CFG
[client]
host=${DB_HOST:-localhost}
port=${DB_PORT:-3306}
user=${DB_USER:-}
password=${DB_PASS:-}
CFG
    trap 'rm -f "$MYSQL_CNF"' EXIT

    if mysql --defaults-extra-file="$MYSQL_CNF" -e "SELECT 1;" "$DB_NAME" &>/dev/null; then
        ok "MySQL connection successful — database '$DB_NAME' accessible"
    else
        err "MySQL connection FAILED for database '$DB_NAME'"
        err "Check DB_HOST, DB_NAME, DB_USER, DB_PASS in .env"
        warn "On Namecheap cPanel: find credentials under MySQL Databases"
        exit 1
    fi
fi

# ── Step 4: File permissions ──────────────────────────────────
step "Step 4/10 — File permissions"
info "Setting directories to 755 …"
find "$ROOT_DIR" -not -path '*/vendor/*' -not -path '*/.git/*' \
    -not -path '*/node_modules/*' -type d -exec chmod 755 {} \;
ok "Directories set to 755"

info "Setting files to 644 …"
find "$ROOT_DIR" -not -path '*/vendor/*' -not -path '*/.git/*' \
    -not -path '*/node_modules/*' -type f \
    -not -name "*.sh" -exec chmod 644 {} \;
ok "Files set to 644"

info "Setting shell scripts to 755 …"
find "$ROOT_DIR" -not -path '*/vendor/*' -not -path '*/.git/*' \
    -name "*.sh" -exec chmod 755 {} \;
ok "Shell scripts set to 755"

if [ -f "$ENV_FILE" ]; then
    chmod 640 "$ENV_FILE"
    ok ".env permissions set to 640 (owner rw, group r, others none)"
fi
ok "File permissions applied"

# ── Step 5: Required directories ─────────────────────────────
step "Step 5/10 — Upload & storage directories"
DIRS=(
    "uploads/kyc"
    "uploads/products"
    "uploads/avatars"
    "assets/uploads"
    "storage"
    "storage/logs"
    "storage/cache"
    "storage/sessions"
    "logs"
    "cache"
)
for dir in "${DIRS[@]}"; do
    if [ ! -d "$ROOT_DIR/$dir" ]; then
        mkdir -p "$ROOT_DIR/$dir"
        ok "Created $dir"
    else
        info "$dir already exists"
    fi
    chmod 755 "$ROOT_DIR/$dir"
done
ok "All required directories created and set to 755"

# ── Step 6: PHP dependencies ──────────────────────────────────
step "Step 6/10 — PHP dependencies (composer)"
if command -v composer &>/dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction \
        --working-dir="$ROOT_DIR"
    ok "composer install --no-dev completed"
else
    warn "composer not found — skipping PHP dependency install"
    warn "Run manually: composer install --no-dev --optimize-autoloader"
fi

# ── Step 7: Database schema import ───────────────────────────
step "Step 7/10 — Database schema import"
if [ -z "$DB_NAME" ] || echo "$DB_NAME" | grep -qi "fill_in"; then
    warn "DB credentials not set — skipping database import"
    warn "Run manually: bash deploy/database-setup.sh"
elif [ "$FORCE_DB" = "--force-db" ]; then
    bash "$DEPLOY_DIR/database-setup.sh" --force
else
    bash "$DEPLOY_DIR/database-setup.sh"
fi

# ── Step 8: Create admin user if not exists ──────────────────
step "Step 8/10 — Admin user setup"
if [ -z "$ADMIN_EMAIL" ] || echo "$ADMIN_EMAIL" | grep -qi "fill_in"; then
    warn "ADMIN_EMAIL not set in .env — skipping admin user creation"
    warn "Set ADMIN_EMAIL and ADMIN_PASSWORD in .env and re-run, or create admin manually."
elif [ -z "$ADMIN_PASSWORD" ] || echo "$ADMIN_PASSWORD" | grep -qi "fill_in\|changeme"; then
    warn "ADMIN_PASSWORD is empty or default — skipping admin creation for security"
    warn "Set a strong ADMIN_PASSWORD in .env (min 12 chars, mixed case, numbers, symbols)"
else
    php -r "
        define('ROOT_DIR', '$ROOT_DIR');
        \$envFile = ROOT_DIR . '/.env';
        foreach (file(\$envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as \$line) {
            if (strpos(trim(\$line), '#') === 0 || !strpos(\$line, '=')) continue;
            [\$k, \$v] = explode('=', \$line, 2);
            putenv(trim(\$k) . '=' . trim(\$v));
        }
        \$host   = getenv('DB_HOST') ?: 'localhost';
        \$port   = getenv('DB_PORT') ?: '3306';
        \$dbname = getenv('DB_NAME');
        \$user   = getenv('DB_USER');
        \$pass   = getenv('DB_PASS');
        \$email  = getenv('ADMIN_EMAIL');
        \$rawPw  = getenv('ADMIN_PASSWORD');
        try {
            \$pdo = new PDO(
                \"mysql:host=\$host;port=\$port;dbname=\$dbname;charset=utf8mb4\",
                \$user, \$pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            \$exists = \$pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            \$exists->execute([\$email]);
            if (\$exists->fetch()) {
                echo \"ADMIN_EXISTS\n\";
            } else {
                \$hash = password_hash(\$rawPw, PASSWORD_BCRYPT, ['cost' => 12]);
                \$stmt = \$pdo->prepare(
                    'INSERT INTO users
                     (email, password_hash, name, role, admin_role, status, is_verified, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())'
                );
                \$stmt->execute([\$email, \$hash, 'Admin', 'admin', 'super_admin', 'active']);
                echo 'ADMIN_CREATED:' . \$pdo->lastInsertId() . \"\n\";
            }
        } catch (Exception \$e) {
            echo 'ADMIN_ERROR:' . \$e->getMessage() . \"\n\";
        }
    " 2>/dev/null | while IFS= read -r line; do
        case "$line" in
            ADMIN_CREATED:*) ok "Admin user created (ID: ${line#ADMIN_CREATED:}) — Email: $ADMIN_EMAIL" ;;
            ADMIN_EXISTS)    ok "Admin user already exists ($ADMIN_EMAIL) — skipping" ;;
            ADMIN_ERROR:*)   err "Admin creation error: ${line#ADMIN_ERROR:}" ;;
        esac
    done
fi

# ── Step 9: Node.js dependencies ─────────────────────────────
step "Step 9/10 — Node.js dependencies"
if [ -d "$ROOT_DIR/nodejs" ]; then
    if command -v npm &>/dev/null; then
        (cd "$ROOT_DIR/nodejs" && npm install --production --no-audit)
        ok "npm install --production completed"
    else
        warn "npm not found — skipping Node.js dependency install"
        warn "Run manually: cd nodejs && npm install --production"
        warn "See deploy/nodejs-setup.md for cPanel Node.js App setup instructions."
    fi
else
    warn "nodejs/ directory not found — skipping"
fi

# ── Step 10: Health check & summary ──────────────────────────
step "Step 10/10 — Health check & deployment summary"

check_env() {
    local key="$1"
    local val
    val=$(read_env "$key")
    if [ -n "$val" ] && ! echo "$val" | grep -qi "fill_in\|xxx\|your_"; then
        ok "$key is set"
    else
        err "$key is EMPTY or placeholder — set a real value in .env"
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
check_env "ADMIN_EMAIL"
check_env "ADMIN_PASSWORD"

echo ""
info "Checking directory permissions …"
for dir in "uploads/kyc" "uploads/products" "uploads/avatars" "storage"; do
    if [ -w "$ROOT_DIR/$dir" ]; then
        ok "$dir is writable"
    else
        err "$dir is NOT writable — run: chmod 755 $dir"
    fi
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

# Run PHP health check
echo ""
if [ -f "$DEPLOY_DIR/health-check.php" ]; then
    info "Running PHP health check …"
    php "$DEPLOY_DIR/health-check.php" || true
else
    warn "health-check.php not found — skipping"
fi

echo ""
echo -e "${BOLD}${GREEN}═══════════════════════════════════════════════════${RESET}"
echo -e "${BOLD}${GREEN}  Deployment setup complete!${RESET}"
echo -e "${GREEN}  Next steps:${RESET}"
echo -e "  1. Review any ✘ errors above and fix them in .env"
echo -e "  2. Set cron jobs per deploy/cron-setup.md"
echo -e "  3. Configure Node.js per deploy/nodejs-setup.md"
echo -e "  4. Verify SSL per deploy/ssl-checklist.md"
echo -e "  5. Enable HTTPS in .htaccess (uncomment RewriteRule)"
echo -e "  6. Full health check: php deploy/health-check.php"
echo -e "${BOLD}${GREEN}═══════════════════════════════════════════════════${RESET}"
