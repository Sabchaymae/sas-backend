#!/bin/bash
# =============================================================================
#  Oriotel ERP — Deploy Script
#  Usage: ./deploy.sh [--build] [--seed] [--reset-db]
#
#  Flags:
#    --build      Force Docker image rebuild (runs docker compose build)
#    --seed       Re-run database seeders after migrations
#    --reset-db   Drop all tables and re-migrate from scratch (migrate:fresh)
#                 ⚠️  WARNING: This deletes all data in the DB!
# =============================================================================
# Usage: ./deploy.sh               # Normal deploy: down → up → migrate → cache clear
# ./deploy.sh --build       # Same + rebuilds Docker images
# ./deploy.sh --seed        # Same + re-runs database seeders
# ./deploy.sh --reset-db    # ⚠️  Drops all tables + fresh migrate + seed

set -e

# ─── Colours ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*"; exit 1; }
step()    { echo -e "\n${BOLD}${CYAN}══ $* ══${RESET}"; }

# ─── Flags ───────────────────────────────────────────────────────────────────
DO_BUILD=false
DO_SEED=false
RESET_DB=false
for arg in "$@"; do
  case $arg in
    --build)    DO_BUILD=true ;;
    --seed)     DO_SEED=true ;;
    --reset-db) RESET_DB=true; DO_SEED=true ;;
    --help|-h)
      echo "Usage: ./deploy.sh [--build] [--seed] [--reset-db]"
      exit 0
      ;;
  esac
done

# ─── PHP services list ───────────────────────────────────────────────────────
PHP_SERVICES=(
  "oriotel-identity-php"
  "oriotel-operations-php"
  "oriotel-communication-php"
  "oriotel-subscription-php"
)

# ─── Health check helper ─────────────────────────────────────────────────────
wait_healthy() {
  local container="$1"
  local max_attempts=30
  local attempt=0
  info "Waiting for ${container} to be healthy..."
  while [ $attempt -lt $max_attempts ]; do
    status=$(docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null || echo "missing")
    if [ "$status" = "healthy" ]; then
      success "${container} is healthy."
      return 0
    fi
    attempt=$((attempt + 1))
    sleep 3
  done
  warn "${container} did not become healthy after ${max_attempts} attempts. Continuing anyway."
}

# ─── Artisan helper (clears cache + migrates) ────────────────────────────────
artisan_bootstrap() {
  local container="$1"
  info "Bootstrapping ${container}..."

  # Clear all stale cache
  docker exec "$container" php artisan optimize:clear       --quiet && success "  Cache cleared"
  docker exec "$container" php artisan config:clear         --quiet
  docker exec "$container" php artisan route:clear          --quiet
  docker exec "$container" php artisan view:clear           --quiet
  docker exec "$container" php artisan event:clear          --quiet 2>/dev/null || true

  # Remove stale compiled bootstrap files
  docker exec "$container" sh -c "rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/services.php bootstrap/cache/packages.php" \
    && success "  Bootstrap cache cleared"

  # Run migrations
  if [ "$RESET_DB" = true ]; then
    warn "  Running migrate:fresh --seed on ${container} (DATA WILL BE LOST!)"
    if [ "$DO_SEED" = true ]; then
      docker exec "$container" php artisan migrate:fresh --seed --force
    else
      docker exec "$container" php artisan migrate:fresh --force
    fi
  else
    docker exec "$container" php artisan migrate --force && success "  Migrations complete"
    if [ "$DO_SEED" = true ]; then
      docker exec "$container" php artisan db:seed --force && success "  Seeding complete"
    fi
  fi

  success "  ${container} ready!"
}

# =============================================================================
#  MAIN FLOW
# =============================================================================
echo ""
echo -e "${BOLD}╔════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}║   🚀  Oriotel ERP — Deploy Script      ║${RESET}"
echo -e "${BOLD}╚════════════════════════════════════════╝${RESET}"
echo ""

# ─── Step 1: Pre-flight info ─────────────────────────────────────────────────
step "Step 1 — Pre-flight"
if [ "$DO_BUILD" = true ]; then
  info "Images will be rebuilt (--build flag set)."
else
  info "Using existing images. Pass --build to force a rebuild."
fi
if [ "$RESET_DB" = true ]; then
  warn "Database will be reset! All data will be lost (--reset-db flag set)."
fi

# ─── Step 2: Bring containers up ─────────────────────────────────────────────
step "Step 2 — Stopping Existing Containers"
info "Bringing down any running containers..."
docker compose down --remove-orphans
success "Containers stopped."

step "Step 2b — Starting Containers"
info "Bringing up all services..."
if [ "$DO_BUILD" = true ]; then
  docker compose up -d --build
else
  docker compose up -d
fi
success "All containers started."

# ─── Step 3: Wait for infrastructure ─────────────────────────────────────────
step "Step 3 — Infrastructure Health Check"
wait_healthy "oriotel-mysql"
wait_healthy "oriotel-redis"

# ─── Step 4: Bootstrap each PHP service ──────────────────────────────────────
step "Step 4 — Bootstrapping PHP Services"
for container in "${PHP_SERVICES[@]}"; do
  # Check the container actually exists before attempting
  if docker inspect "$container" &>/dev/null; then
    artisan_bootstrap "$container"
  else
    warn "Container ${container} not found, skipping."
  fi
done

# ─── Step 5: Verify API gateway health ───────────────────────────────────────
step "Step 5 — Gateway Health Check"
GATEWAY_PORT=${GATEWAY_PORT:-8080}
sleep 2
HEALTH_RESP=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:${GATEWAY_PORT}/api/identity/v1/health" 2>/dev/null || echo "000")
if [ "$HEALTH_RESP" = "200" ]; then
  success "API Gateway is responding → http://localhost:${GATEWAY_PORT}"
else
  warn "Gateway health check returned HTTP ${HEALTH_RESP}. The service may still be starting up."
fi

# ─── Step 6: Container status summary ────────────────────────────────────────
step "Step 6 — Container Status"
docker compose ps

echo ""
echo -e "${BOLD}${GREEN}════════════════════════════════════════${RESET}"
echo -e "${BOLD}${GREEN}  ✅ Deployment complete!${RESET}"
echo -e "${GREEN}  Gateway   → http://localhost:${GATEWAY_PORT}${RESET}"
echo -e "${GREEN}  phpMyAdmin → http://localhost:8081${RESET}"
echo -e "${BOLD}${GREEN}════════════════════════════════════════${RESET}"
echo ""
