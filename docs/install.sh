#!/bin/bash

# =============================================================================
# install.sh — AetherDB AI Environment Setup
# =============================================================================
# Stack: Laravel 13 + Vue 3 + Inertia.js + Tauri v2 + Redis
# Usage: bash docs/install.sh
# =============================================================================

set -e  # Exit on any error

# ── Colors ────────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# ── Helpers ───────────────────────────────────────────────────────────────────
log()     { echo -e "${BLUE}[AetherDB]${NC} $1"; }
success() { echo -e "${GREEN}[✓]${NC} $1"; }
warn()    { echo -e "${YELLOW}[!]${NC} $1"; }
error()   { echo -e "${RED}[✗]${NC} $1"; exit 1; }
section() { echo -e "\n${CYAN}══════════════════════════════════════${NC}"; echo -e "${CYAN}  $1${NC}"; echo -e "${CYAN}══════════════════════════════════════${NC}"; }

# ── Banner ────────────────────────────────────────────────────────────────────
echo -e "${CYAN}"
echo "  █████╗ ███████╗████████╗██╗  ██╗███████╗██████╗ ██████╗ ██████╗ "
echo " ██╔══██╗██╔════╝╚══██╔══╝██║  ██║██╔════╝██╔══██╗██╔══██╗██╔══██╗"
echo " ███████║█████╗     ██║   ███████║█████╗  ██████╔╝██║  ██║██████╔╝"
echo " ██╔══██║██╔══╝     ██║   ██╔══██║██╔══╝  ██╔══██╗██║  ██║██╔══██╗"
echo " ██║  ██║███████╗   ██║   ██║  ██║███████╗██║  ██║██████╔╝██████╔╝"
echo " ╚═╝  ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝╚═════╝ ╚═════╝ "
echo -e "${NC}"
echo -e "  ${BLUE}AI-Powered Database Intelligence Desktop App${NC}"
echo -e "  ${BLUE}Environment Setup Script v1.0.0${NC}\n"

# ── Prerequisite Check ────────────────────────────────────────────────────────
section "Checking Prerequisites"

check_command() {
    if command -v "$1" &> /dev/null; then
        success "$1 found: $(command -v $1)"
    else
        error "$1 not found. Please install $1 first."
    fi
}

check_command_optional() {
    if command -v "$1" &> /dev/null; then
        success "$1 found: $(command -v $1)"
    else
        warn "$1 not found — optional, skip untuk sekarang. ($2)"
    fi
}

check_version() {
    local cmd=$1
    local required=$2
    local actual=$($3 2>&1 | head -1)
    log "Checking $cmd version: $actual (required: $required+)"
}

# ── Required ──
check_command php
check_command composer
check_command node
check_command npm
check_command git

# ── Optional (tidak stop proses jika tidak ada) ──
check_command_optional redis-cli   "Diperlukan di Phase 4 (AI Engine). Untuk sekarang pakai CACHE_DRIVER=file"
check_command_optional rustc       "Diperlukan di Phase 5 (Tauri). Install via: curl https://sh.rustup.rs -sSf | sh"
check_command_optional cargo       "Diperlukan di Phase 5 (Tauri). Install bersama rustc"

check_version "PHP"    "8.4" "php -v"
check_version "Node"   "20"  "node -v"

success "Prerequisite check selesai"

# ── Project Root Check ────────────────────────────────────────────────────────
section "Verifying Project Structure"

if [ ! -f "composer.json" ]; then
    error "composer.json not found. Run this script from the project root."
fi

if [ ! -f "package.json" ]; then
    error "package.json not found. Run this script from the project root."
fi

success "Project root confirmed: $(pwd)"

# ── PHP Dependencies ──────────────────────────────────────────────────────────
section "Installing PHP Dependencies"

log "Running composer install..."
composer install --no-interaction --prefer-dist --optimize-autoloader

success "PHP dependencies installed"

# ── Node Dependencies ─────────────────────────────────────────────────────────
section "Installing Node Dependencies"

log "Running npm install..."
npm install

success "Node dependencies installed"

# ── Environment File ──────────────────────────────────────────────────────────
section "Setting Up Environment"

if [ ! -f ".env" ]; then
    log "Copying .env.example to .env..."
    cp .env.example .env
    success ".env created from .env.example"
else
    warn ".env already exists, skipping copy"
fi

# ── Laravel App Key ───────────────────────────────────────────────────────────
section "Generating Laravel Application Key"

if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=\"\"" .env; then
    log "Generating application key..."
    php artisan key:generate --force
    success "Application key generated"
else
    warn "APP_KEY already set, skipping"
fi

# ── Storage & Cache Directories ───────────────────────────────────────────────
section "Creating Storage Directories"

php artisan storage:link 2>/dev/null || true

mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

chmod -R 775 storage bootstrap/cache
success "Storage directories ready"

# ── Database Setup ────────────────────────────────────────────────────────────
section "Database Setup"

# Check if DB is configured
DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2)
DB_DATABASE=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2)

if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    warn "DB_HOST is set to default (127.0.0.1)"
fi

log "Checking database connection..."
if php artisan db:show --no-ansi &>/dev/null; then
    success "Database connection successful"

    log "Running migrations..."
    php artisan migrate --no-interaction --force
    success "Migrations complete"
else
    warn "Database connection failed — skipping migrations"
    warn "Please configure DB_* variables in .env and run: php artisan migrate"
fi

# ── Redis Check ───────────────────────────────────────────────────────────────
section "Redis Check"

if redis-cli ping &>/dev/null; then
    success "Redis is running"
else
    warn "Redis is not running"
    warn "Start Redis with: redis-server"
    warn "Queue and cache may not work until Redis is running"
fi

# ── Cache Clear ───────────────────────────────────────────────────────────────
section "Clearing Caches"

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

success "All caches cleared"

# ── Tauri / Rust Setup ────────────────────────────────────────────────────────
section "Tauri / Rust Setup"

if [ -d "src-tauri" ]; then
    log "Checking Tauri CLI..."
    if npx tauri --version &>/dev/null; then
        success "Tauri CLI found: $(npx tauri --version)"
    else
        log "Installing Tauri CLI..."
        npm install --save-dev @tauri-apps/cli@next
        success "Tauri CLI installed"
    fi

    log "Building Rust dependencies (this may take a few minutes on first run)..."
    cd src-tauri && cargo fetch && cd ..
    success "Rust dependencies fetched"
else
    warn "src-tauri/ not found — Tauri not set up yet"
    warn "Run: npx tauri init when ready"
fi

# ── shadcn/ui Check ───────────────────────────────────────────────────────────
section "Frontend UI Libraries"

if [ -d "resources/js/components/ui" ]; then
    success "shadcn/ui components directory exists"
else
    warn "shadcn/ui not initialized yet"
    warn "Run: npx shadcn-vue@latest init"
fi

# ── Vue Flow Check ────────────────────────────────────────────────────────────
if grep -q "@vue-flow/core" package.json; then
    success "Vue Flow installed"
else
    warn "Vue Flow not installed yet"
    warn "Run: npm install @vue-flow/core @vue-flow/background @vue-flow/controls @vue-flow/minimap"
fi

# ── Environment Variable Reminder ─────────────────────────────────────────────
section "Environment Variables to Configure"

echo -e "${YELLOW}Please make sure the following are set in your .env file:${NC}\n"

ENV_VARS=(
    "DB_HOST           — MySQL/MariaDB host"
    "DB_PORT           — Database port (default: 3306)"
    "DB_DATABASE       — AetherDB app database name"
    "DB_USERNAME       — Database username"
    "DB_PASSWORD       — Database password"
    "REDIS_HOST        — Redis host (default: 127.0.0.1)"
    "REDIS_PORT        — Redis port (default: 6379)"
    "OPENAI_API_KEY    — OpenAI API key (for AI features)"
    "ANTHROPIC_API_KEY — Anthropic Claude API key"
    "OPENROUTER_API_KEY — OpenRouter API key (for DeepSeek)"
    "AETHERDB_VAULT_KEY — Master key for credential encryption"
)

for var in "${ENV_VARS[@]}"; do
    echo -e "  ${CYAN}•${NC} $var"
done

echo ""

# ── Final Summary ─────────────────────────────────────────────────────────────
section "Setup Complete"

echo -e "${GREEN}AetherDB AI environment is ready!${NC}\n"
echo -e "Start development with:\n"
echo -e "  ${CYAN}Terminal 1${NC} — Laravel API:"
echo -e "  ${YELLOW}  php artisan serve${NC}\n"
echo -e "  ${CYAN}Terminal 2${NC} — Queue Worker:"
echo -e "  ${YELLOW}  php artisan queue:work${NC}\n"
echo -e "  ${CYAN}Terminal 3${NC} — Vite HMR:"
echo -e "  ${YELLOW}  npm run dev${NC}\n"
echo -e "  ${CYAN}Terminal 4${NC} — Tauri Desktop:"
echo -e "  ${YELLOW}  npx tauri dev${NC}\n"
echo -e "Docs:"
echo -e "  ${CYAN}PRD   →${NC} docs/PRD.md"
echo -e "  ${CYAN}PLAN  →${NC} docs/PLAN.md"
echo -e "  ${CYAN}AGENT →${NC} docs/AGENTS.md"
echo -e "  ${CYAN}SKILL →${NC} docs/skill.sh\n"
