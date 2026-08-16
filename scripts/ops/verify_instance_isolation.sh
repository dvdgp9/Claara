#!/usr/bin/env bash
set -euo pipefail

app_root="/home/dvdgp/web/claara.tech/public_html"
source_db="iaiapro_db"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-root) app_root="$2"; shift 2 ;;
    --source-db) source_db="$2"; shift 2 ;;
    *) printf 'Unknown option: %s\n' "$1" >&2; exit 2 ;;
  esac
done

if [[ "$(id -u)" -ne 0 ]]; then
  printf '[FAIL] Run as root\n' >&2
  exit 1
fi
if [[ ! -d "$app_root" || "$source_db" != "${source_db//[^A-Za-z0-9_]/}" ]]; then
  printf '[FAIL] Invalid application root or source database\n' >&2
  exit 1
fi
for dependency in mariadb mariadb-dump rsync php curl jq runuser shuf ss openssl; do
  command -v "$dependency" >/dev/null || { printf '[FAIL] Missing dependency: %s\n' "$dependency" >&2; exit 1; }
done

short_id="$(date -u +%H%M%S)_$$"
safe_id="${short_id//[^0-9_]/}"
root_dir="$(mktemp -d "/var/tmp/claara-isolation.${safe_id}.XXXXXX")"
alpha_db="claara_iso_a_${safe_id}"
beta_db="claara_iso_b_${safe_id}"
alpha_user="cisoa_${safe_id}"
beta_user="cisob_${safe_id}"
alpha_db_password="$(openssl rand -hex 20)"
beta_db_password="$(openssl rand -hex 20)"
alpha_login_password="$(openssl rand -hex 18)"
beta_login_password="$(openssl rand -hex 18)"
alpha_prefix="iso_a_${safe_id}__"
beta_prefix="iso_b_${safe_id}__"
alpha_collection="${alpha_prefix}knowledge"
beta_collection="${beta_prefix}knowledge"
alpha_pid=""
beta_pid=""
success=0

for identifier in "$alpha_db" "$beta_db" "$alpha_user" "$beta_user" "$alpha_collection" "$beta_collection"; do
  if [[ "$identifier" != "${identifier//[^a-zA-Z0-9_]/}" ]]; then
    printf '[FAIL] Generated unsafe isolation identifier\n' >&2
    exit 1
  fi
done

app_user="$(stat -c '%U' "$app_root")"
app_group="$(stat -c '%G' "$app_root")"
if [[ -z "$app_user" || "$app_user" == "root" ]]; then
  printf '[FAIL] Refusing to run isolation web processes as root\n' >&2
  exit 1
fi
chown "$app_user:$app_group" "$root_dir"
chmod 700 "$root_dir"

cleanup() {
  local exit_code=$?
  trap - EXIT INT TERM
  set +e
  for pid in "$alpha_pid" "$beta_pid"; do
    if [[ -n "$pid" ]]; then
      kill "$pid" 2>/dev/null
      wait "$pid" 2>/dev/null
    fi
  done
  curl --silent --show-error -X DELETE "http://127.0.0.1:6333/collections/$alpha_collection" >/dev/null 2>&1
  curl --silent --show-error -X DELETE "http://127.0.0.1:6333/collections/$beta_collection" >/dev/null 2>&1
  mariadb -e "DROP DATABASE IF EXISTS \`$alpha_db\`; DROP DATABASE IF EXISTS \`$beta_db\`; DROP USER IF EXISTS '$alpha_user'@'localhost'; DROP USER IF EXISTS '$alpha_user'@'127.0.0.1'; DROP USER IF EXISTS '$beta_user'@'localhost'; DROP USER IF EXISTS '$beta_user'@'127.0.0.1';" >/dev/null 2>&1
  if [[ "$success" -ne 1 ]]; then
    for log in "$root_dir"/*/server.log; do
      if [[ -s "$log" ]]; then
        printf '[DIAGNOSTIC] %s\n' "$log" >&2
        tail -n 60 "$log" >&2
      fi
    done
  fi
  rm -rf -- "$root_dir"
  if [[ "$exit_code" -ne 0 ]]; then
    printf '[FAIL] Cross-instance isolation verification failed; temporary resources were removed\n' >&2
  fi
  exit "$exit_code"
}
trap cleanup EXIT INT TERM

fingerprint_before="$(mariadb -NBe "SELECT CONCAT((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source_db'), ':', (SELECT COUNT(*) FROM \`$source_db\`.users), ':', (SELECT COUNT(*) FROM \`$source_db\`.messages), ':', (SELECT COUNT(*) FROM \`$source_db\`.context_documents));")"
production_qdrant_before="$(curl --fail --silent --show-error http://127.0.0.1:6333/collections | jq -r '[.result.collections[].name | select(. == "lex_knowledge_base" or . == "voice_conveniex")] | sort | join(":")')"

printf '[1/8] Creating two isolated databases and credentials\n'
mariadb -e "CREATE DATABASE \`$alpha_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE \`$beta_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$alpha_user'@'localhost' IDENTIFIED BY '$alpha_db_password'; CREATE USER '$alpha_user'@'127.0.0.1' IDENTIFIED BY '$alpha_db_password'; CREATE USER '$beta_user'@'localhost' IDENTIFIED BY '$beta_db_password'; CREATE USER '$beta_user'@'127.0.0.1' IDENTIFIED BY '$beta_db_password'; GRANT ALL PRIVILEGES ON \`$alpha_db\`.* TO '$alpha_user'@'localhost'; GRANT ALL PRIVILEGES ON \`$alpha_db\`.* TO '$alpha_user'@'127.0.0.1'; GRANT ALL PRIVILEGES ON \`$beta_db\`.* TO '$beta_user'@'localhost'; GRANT ALL PRIVILEGES ON \`$beta_db\`.* TO '$beta_user'@'127.0.0.1';"
mariadb-dump --no-data --skip-triggers --routines=false --events=false "$source_db" | mariadb "$alpha_db"
mariadb-dump --no-data --skip-triggers --routines=false --events=false "$source_db" | mariadb "$beta_db"

alpha_hash="$(PASSWORD_TO_HASH="$alpha_login_password" php -r 'echo password_hash((string)getenv("PASSWORD_TO_HASH"), PASSWORD_ARGON2ID);')"
beta_hash="$(PASSWORD_TO_HASH="$beta_login_password" php -r 'echo password_hash((string)getenv("PASSWORD_TO_HASH"), PASSWORD_ARGON2ID);')"
mariadb "$alpha_db" -e "INSERT INTO users (email, password_hash, first_name, last_name, is_superadmin, status, created_at, updated_at) VALUES ('alpha.user@example.invalid', '$alpha_hash', 'Alpha', 'Synthetic', 0, 'active', NOW(), NOW()), ('alpha.admin@example.invalid', '$alpha_hash', 'Alpha', 'Admin', 1, 'active', NOW(), NOW());"
mariadb "$beta_db" -e "INSERT INTO users (email, password_hash, first_name, last_name, is_superadmin, status, created_at, updated_at) VALUES ('beta.user@example.invalid', '$beta_hash', 'Beta', 'Synthetic', 0, 'active', NOW(), NOW()), ('beta.admin@example.invalid', '$beta_hash', 'Beta', 'Admin', 1, 'active', NOW(), NOW());"
mariadb "$alpha_db" -e "INSERT INTO user_feature_access (user_id, feature_type, feature_slug, enabled) SELECT id, 'gesture', 'lead-finder', 1 FROM users WHERE email='alpha.user@example.invalid';"
mariadb "$beta_db" -e "INSERT INTO user_feature_access (user_id, feature_type, feature_slug, enabled) SELECT id, 'gesture', 'lead-finder', 1 FROM users WHERE email='beta.user@example.invalid';"
# MODULE_ISOLATION_ROLE_MATRIX: each instance has a normal user and a superadmin.
# MODULE_ISOLATION_STALE_GRANT: both normal users retain the same database grant.

printf '[2/8] Proving database credentials cannot cross instance boundaries\n'
if MYSQL_PWD="$alpha_db_password" mariadb -h 127.0.0.1 -u "$alpha_user" "$beta_db" -NBe 'SELECT 1' >/dev/null 2>&1; then
  printf '[FAIL] Alpha database credentials reached beta\n' >&2
  exit 1
fi
if MYSQL_PWD="$beta_db_password" mariadb -h 127.0.0.1 -u "$beta_user" "$alpha_db" -NBe 'SELECT 1' >/dev/null 2>&1; then
  printf '[FAIL] Beta database credentials reached alpha\n' >&2
  exit 1
fi
printf '[PASS] Database grants are mutually isolated\n'

create_workspace() {
  local slug="$1" domain="$2" db_name="$3" db_user="$4" db_password="$5" prefix="$6" namespace="$7" lead_enabled="$8"
  local upper="${slug^^}" workspace="$root_dir/$slug" storage="$root_dir/$slug-storage"
  local enabled_modules='"core.chat", "core.voices", "core.gestures"'
  if [[ "$lead_enabled" == "yes" ]]; then
    enabled_modules+=', "gesture.lead-finder"'
  fi
  mkdir -p "$workspace" "$storage/sessions" "$workspace/config/instances"
  rsync -a --exclude='.git' --exclude='.env' --exclude='storage' --exclude='vendor' "$app_root/" "$workspace/"
  ln -s "$app_root/vendor" "$workspace/vendor"
  printf '%s-only\n' "$slug" > "$storage/ownership-marker.txt"
  cat > "$workspace/.env" <<ENV
APP_ENV=staging
APP_DEBUG=0
APP_URL=http://$domain
INSTANCE_MANIFEST_PATH=config/instances/$slug.json
${upper}_STORAGE_ROOT=$storage
${upper}_DB_HOST=127.0.0.1
${upper}_DB_PORT=3306
${upper}_DB_NAME=$db_name
${upper}_DB_USER=$db_user
${upper}_DB_PASS=$db_password
${upper}_QDRANT_HOST=127.0.0.1
${upper}_QDRANT_PORT=6333
${upper}_QDRANT_API_KEY=
OPENROUTER_API_KEY=
GEMINI_API_KEY=
ENV
  cat > "$workspace/config/instances/$slug.json" <<JSON
{
  "schema_version": 1,
  "id": "$slug",
  "slug": "$slug",
  "status": "active",
  "canonical_domain": "$domain",
  "domains": ["$domain"],
  "branding": {"product_name": "Claara", "organization_name": "Synthetic $slug", "logo_path": "/assets/images/logo.png", "login_logo_path": "/assets/images/claara-logo.png", "accent_color": "#FF8B73"},
  "locales": {"default": "en", "allowed": ["en", "es"]},
  "modules": {"enabled": [$enabled_modules]},
  "limits": {"users": 2, "storage_bytes": 104857600},
  "release": {"channel": "isolation-test", "id": "$safe_id"},
  "resources": {
    "database": {"env_prefix": "${upper}_DB"},
    "storage": {"path_env": "${upper}_STORAGE_ROOT"},
    "rag": {"env_prefix": "${upper}_QDRANT", "collection_prefix": "$prefix"},
    "session": {"namespace": "$namespace"},
    "secrets": {"scope": "$slug"},
    "backups": {"scope": "$slug"}
  }
}
JSON
  chown -R "$app_user:$app_group" "$workspace" "$storage"
  chmod 600 "$workspace/.env"
  chmod 700 "$storage" "$storage/sessions"
}

printf '[3/8] Creating separate manifests, secrets, storage, sessions, and module contracts\n'
create_workspace alpha alpha.test "$alpha_db" "$alpha_user" "$alpha_db_password" "$alpha_prefix" 8ed3f6ad685b yes
create_workspace beta beta.test "$beta_db" "$beta_user" "$beta_db_password" "$beta_prefix" f44e64e75f39 no
# MODULE_ISOLATION_ALPHA_ENABLED: Alpha enables Lead Finder; Beta deliberately does not.
if [[ "$(<"$root_dir/alpha-storage/ownership-marker.txt")" != 'alpha-only' ]] || [[ "$(<"$root_dir/beta-storage/ownership-marker.txt")" != 'beta-only' ]]; then
  printf '[FAIL] Storage ownership markers crossed\n' >&2
  exit 1
fi
printf '[PASS] Storage roots and manifests are distinct\n'

printf '[4/8] Creating prefixed synthetic RAG collections\n'
for collection in "$alpha_collection" "$beta_collection"; do
  curl --fail --silent --show-error -X PUT "http://127.0.0.1:6333/collections/$collection" -H 'Content-Type: application/json' --data '{"vectors":{"size":4,"distance":"Cosine"}}' >/dev/null
done
curl --fail --silent --show-error -X PUT "http://127.0.0.1:6333/collections/$alpha_collection/points?wait=true" -H 'Content-Type: application/json' --data '{"points":[{"id":1,"vector":[1,0,0,0],"payload":{"tenant":"alpha"}}]}' >/dev/null
curl --fail --silent --show-error -X PUT "http://127.0.0.1:6333/collections/$beta_collection/points?wait=true" -H 'Content-Type: application/json' --data '{"points":[{"id":1,"vector":[0,1,0,0],"payload":{"tenant":"beta"}}]}' >/dev/null
alpha_rag="$(cd "$root_dir/alpha" && php -r 'require "src/App/bootstrap.php"; $q = new Rag\QdrantClient(); printf("%s:%d", $q->scopedCollectionName("knowledge"), $q->countPoints("knowledge"));')"
beta_rag="$(cd "$root_dir/beta" && php -r 'require "src/App/bootstrap.php"; $q = new Rag\QdrantClient(); printf("%s:%d", $q->scopedCollectionName("knowledge"), $q->countPoints("knowledge"));')"
if [[ "$alpha_rag" != "$alpha_collection:1" || "$beta_rag" != "$beta_collection:1" ]]; then
  printf '[FAIL] RAG prefixes or counts crossed: alpha=%s beta=%s\n' "$alpha_rag" "$beta_rag" >&2
  exit 1
fi
printf '[PASS] RAG clients resolve only their prefixed collection\n'

allocate_port() {
  local candidate
  for candidate in $(shuf -i 19000-19999 -n 30); do
    if ! ss -H -lnt "( sport = :$candidate )" | grep -q .; then
      printf '%s' "$candidate"
      return 0
    fi
  done
  return 1
}

printf '[5/8] Starting both instances on loopback\n'
alpha_port="$(allocate_port)"
beta_port="$(allocate_port)"
while [[ "$beta_port" == "$alpha_port" ]]; do beta_port="$(allocate_port)"; done
sed -i "s#APP_URL=http://alpha.test#APP_URL=http://alpha.test:$alpha_port#" "$root_dir/alpha/.env"
sed -i "s#APP_URL=http://beta.test#APP_URL=http://beta.test:$beta_port#" "$root_dir/beta/.env"
runuser -u "$app_user" -- php -S "127.0.0.1:$alpha_port" -t "$root_dir/alpha/public" >"$root_dir/alpha/server.log" 2>&1 & alpha_pid=$!
runuser -u "$app_user" -- php -S "127.0.0.1:$beta_port" -t "$root_dir/beta/public" >"$root_dir/beta/server.log" 2>&1 & beta_pid=$!
for _ in {1..24}; do
  alpha_ready=0; beta_ready=0
  curl --noproxy '*' --silent --fail --resolve "alpha.test:$alpha_port:127.0.0.1" "http://alpha.test:$alpha_port/login.php" >/dev/null && alpha_ready=1
  curl --noproxy '*' --silent --fail --resolve "beta.test:$beta_port:127.0.0.1" "http://beta.test:$beta_port/login.php" >/dev/null && beta_ready=1
  [[ "$alpha_ready" -eq 1 && "$beta_ready" -eq 1 ]] && break
  sleep 0.25
done
kill -0 "$alpha_pid" 2>/dev/null && kill -0 "$beta_pid" 2>/dev/null || { printf '[FAIL] An isolation HTTP process stopped\n' >&2; exit 1; }

printf '[6/8] Proving authentication cookies and identities cannot cross\n'
alpha_jar="$root_dir/alpha.cookies"
beta_jar="$root_dir/beta.cookies"
alpha_admin_jar="$root_dir/alpha-admin.cookies"
beta_admin_jar="$root_dir/beta-admin.cookies"
alpha_body="$root_dir/alpha.login.json"
beta_body="$root_dir/beta.login.json"
alpha_admin_body="$root_dir/alpha-admin.login.json"
beta_admin_body="$root_dir/beta-admin.login.json"
alpha_payload="$(jq -nc --arg email 'alpha.user@example.invalid' --arg password "$alpha_login_password" '{email:$email,password:$password,remember:false}')"
beta_payload="$(jq -nc --arg email 'beta.user@example.invalid' --arg password "$beta_login_password" '{email:$email,password:$password,remember:false}')"
alpha_admin_payload="$(jq -nc --arg email 'alpha.admin@example.invalid' --arg password "$alpha_login_password" '{email:$email,password:$password,remember:false}')"
beta_admin_payload="$(jq -nc --arg email 'beta.admin@example.invalid' --arg password "$beta_login_password" '{email:$email,password:$password,remember:false}')"
alpha_status="$(curl --noproxy '*' --silent --show-error --resolve "alpha.test:$alpha_port:127.0.0.1" -c "$alpha_jar" -o "$alpha_body" -w '%{http_code}' -H 'Content-Type: application/json' --data "$alpha_payload" "http://alpha.test:$alpha_port/api/auth/login.php")"
beta_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -c "$beta_jar" -o "$beta_body" -w '%{http_code}' -H 'Content-Type: application/json' --data "$beta_payload" "http://beta.test:$beta_port/api/auth/login.php")"
alpha_admin_status="$(curl --noproxy '*' --silent --show-error --resolve "alpha.test:$alpha_port:127.0.0.1" -c "$alpha_admin_jar" -o "$alpha_admin_body" -w '%{http_code}' -H 'Content-Type: application/json' --data "$alpha_admin_payload" "http://alpha.test:$alpha_port/api/auth/login.php")"
beta_admin_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -c "$beta_admin_jar" -o "$beta_admin_body" -w '%{http_code}' -H 'Content-Type: application/json' --data "$beta_admin_payload" "http://beta.test:$beta_port/api/auth/login.php")"
if [[ "$alpha_status" != 200 || "$beta_status" != 200 || "$alpha_admin_status" != 200 || "$beta_admin_status" != 200 ]] \
  || [[ "$(jq -r '.user.email // empty' "$alpha_body")" != 'alpha.user@example.invalid' ]] \
  || [[ "$(jq -r '.user.email // empty' "$beta_body")" != 'beta.user@example.invalid' ]] \
  || [[ "$(jq -r '.user.email // empty' "$alpha_admin_body")" != 'alpha.admin@example.invalid' ]] \
  || [[ "$(jq -r '.user.email // empty' "$beta_admin_body")" != 'beta.admin@example.invalid' ]]; then
  printf '[FAIL] Synthetic instance login failed\n' >&2
  exit 1
fi
cross_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -b "$alpha_jar" -o "$root_dir/cross.json" -w '%{http_code}' "http://beta.test:$beta_port/api/auth/me.php")"
if [[ "$cross_status" != 401 ]] || [[ "$(jq -r '.error.code // empty' "$root_dir/cross.json")" != 'unauthorized' ]]; then
  printf '[FAIL] Alpha session was accepted by beta\n' >&2
  exit 1
fi
if [[ "$(find "$root_dir/alpha-storage/sessions" -type f -name 'sess_*' | wc -l)" -lt 1 ]] || [[ "$(find "$root_dir/beta-storage/sessions" -type f -name 'sess_*' | wc -l)" -lt 1 ]]; then
  printf '[FAIL] Instance session files are missing from isolated roots\n' >&2
  exit 1
fi
printf '[PASS] Each instance authenticates only its user and session namespace\n'

printf '[7/8] Proving one release enforces different module contracts\n'
# MODULE_ISOLATION_ANONYMOUS: anonymous requests must not disclose module state.
alpha_anonymous_headers="$root_dir/alpha-anonymous.headers"
beta_anonymous_headers="$root_dir/beta-anonymous.headers"
alpha_anonymous_api="$root_dir/alpha-anonymous-api.json"
beta_anonymous_api="$root_dir/beta-anonymous-api.json"
alpha_anonymous_page_status="$(curl --noproxy '*' --silent --show-error --resolve "alpha.test:$alpha_port:127.0.0.1" --dump-header "$alpha_anonymous_headers" --output /dev/null --write-out '%{http_code}' "http://alpha.test:$alpha_port/gestos/lead-finder.php")"
beta_anonymous_page_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" --dump-header "$beta_anonymous_headers" --output /dev/null --write-out '%{http_code}' "http://beta.test:$beta_port/gestos/lead-finder.php")"
alpha_anonymous_api_status="$(curl --noproxy '*' --silent --show-error --resolve "alpha.test:$alpha_port:127.0.0.1" --output "$alpha_anonymous_api" --write-out '%{http_code}' "http://alpha.test:$alpha_port/api/gestures/lead-finder/history.php")"
beta_anonymous_api_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" --output "$beta_anonymous_api" --write-out '%{http_code}' "http://beta.test:$beta_port/api/gestures/lead-finder/history.php")"
if [[ "$alpha_anonymous_page_status" != 302 || "$beta_anonymous_page_status" != 302 ]] \
  || ! grep -qi '^location: /login.php' "$alpha_anonymous_headers" \
  || ! grep -qi '^location: /login.php' "$beta_anonymous_headers" \
  || [[ "$alpha_anonymous_api_status" != 401 || "$beta_anonymous_api_status" != 401 ]]; then
  printf '[FAIL] Anonymous module requests disclose instance state or bypass authentication\n' >&2
  exit 1
fi

alpha_catalog="$root_dir/alpha-catalog.html"
beta_catalog="$root_dir/beta-catalog.html"
alpha_page="$root_dir/alpha-lead.html"
alpha_history="$root_dir/alpha-history.json"
alpha_admin_history="$root_dir/alpha-admin-history.json"
curl --noproxy '*' --silent --show-error --fail --resolve "alpha.test:$alpha_port:127.0.0.1" -b "$alpha_jar" "http://alpha.test:$alpha_port/gestos/" --output "$alpha_catalog"
curl --noproxy '*' --silent --show-error --fail --resolve "beta.test:$beta_port:127.0.0.1" -b "$beta_jar" "http://beta.test:$beta_port/gestos/" --output "$beta_catalog"
if ! grep -q 'href="/gestos/lead-finder.php"' "$alpha_catalog" || grep -q 'href="/gestos/lead-finder.php"' "$beta_catalog"; then
  printf '[FAIL] Gesture catalogs do not reflect their instance contracts\n' >&2
  exit 1
fi

# MODULE_ISOLATION_ALPHA_NORMAL: enabled + granted works for page, API, and creation.
curl --noproxy '*' --silent --show-error --fail --resolve "alpha.test:$alpha_port:127.0.0.1" -b "$alpha_jar" "http://alpha.test:$alpha_port/gestos/lead-finder.php" --output "$alpha_page"
curl --noproxy '*' --silent --show-error --fail --resolve "alpha.test:$alpha_port:127.0.0.1" -b "$alpha_jar" "http://alpha.test:$alpha_port/api/gestures/lead-finder/history.php" --output "$alpha_history"
if ! grep -q 'lead-finder-shell' "$alpha_page" || ! grep -q '"success":true' "$alpha_history"; then
  printf '[FAIL] Alpha normal user cannot use enabled Lead Finder\n' >&2
  exit 1
fi

# MODULE_ISOLATION_ALPHA_SUPERADMIN: superadmin bypasses only the user grant.
alpha_admin_page_status="$(curl --noproxy '*' --silent --show-error --resolve "alpha.test:$alpha_port:127.0.0.1" -b "$alpha_admin_jar" --output /dev/null --write-out '%{http_code}' "http://alpha.test:$alpha_port/gestos/lead-finder.php")"
alpha_admin_api_status="$(curl --noproxy '*' --silent --show-error --resolve "alpha.test:$alpha_port:127.0.0.1" -b "$alpha_admin_jar" --output "$alpha_admin_history" --write-out '%{http_code}' "http://alpha.test:$alpha_port/api/gestures/lead-finder/history.php")"
if [[ "$alpha_admin_page_status" != 200 || "$alpha_admin_api_status" != 200 ]]; then
  printf '[FAIL] Alpha superadmin cannot use enabled Lead Finder\n' >&2
  exit 1
fi

# MODULE_ISOLATION_BETA_NORMAL: a stale user grant cannot bypass the manifest.
beta_page_headers="$root_dir/beta-page.headers"
beta_page_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -b "$beta_jar" --dump-header "$beta_page_headers" --output /dev/null --write-out '%{http_code}' "http://beta.test:$beta_port/gestos/lead-finder.php")"
beta_api="$root_dir/beta-api.json"
beta_api_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -b "$beta_jar" --output "$beta_api" --write-out '%{http_code}' "http://beta.test:$beta_port/api/gestures/lead-finder/history.php")"
if [[ "$beta_page_status" != 302 ]] || ! grep -qi '^location: /gestos/?error=no_access' "$beta_page_headers" \
  || [[ "$beta_api_status" != 404 ]] || ! grep -q '"code":"feature_unavailable"' "$beta_api"; then
  printf '[FAIL] Beta normal user bypassed disabled Lead Finder\n' >&2
  exit 1
fi

# MODULE_ISOLATION_BETA_SUPERADMIN: tenant superadmin cannot bypass entitlement.
beta_admin_page_headers="$root_dir/beta-admin-page.headers"
beta_admin_page_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -b "$beta_admin_jar" --dump-header "$beta_admin_page_headers" --output /dev/null --write-out '%{http_code}' "http://beta.test:$beta_port/gestos/lead-finder.php")"
beta_admin_api="$root_dir/beta-admin-api.json"
beta_admin_api_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -b "$beta_admin_jar" --output "$beta_admin_api" --write-out '%{http_code}' "http://beta.test:$beta_port/api/gestures/lead-finder/history.php")"
if [[ "$beta_admin_page_status" != 302 ]] || ! grep -qi '^location: /gestos/?error=no_access' "$beta_admin_page_headers" \
  || [[ "$beta_admin_api_status" != 404 ]] || ! grep -q '"code":"feature_unavailable"' "$beta_admin_api"; then
  printf '[FAIL] Beta superadmin bypassed disabled Lead Finder\n' >&2
  exit 1
fi

alpha_csrf="$(jq -r '.csrf_token // empty' "$alpha_body")"
beta_csrf="$(jq -r '.csrf_token // empty' "$beta_body")"
if [[ -z "$alpha_csrf" || -z "$beta_csrf" ]]; then
  printf '[FAIL] Module isolation could not obtain CSRF tokens\n' >&2
  exit 1
fi

alpha_search="$root_dir/alpha-search.json"
alpha_search_status="$(curl --noproxy '*' --silent --show-error --resolve "alpha.test:$alpha_port:127.0.0.1" -b "$alpha_jar" --output "$alpha_search" --write-out '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $alpha_csrf" --data '{"query":"synthetic schools in Malaga","max_results":2}' "http://alpha.test:$alpha_port/api/gestures/lead-finder/search.php")"
alpha_job_id="$(jq -r '.job_id // empty' "$alpha_search")"
if [[ "$alpha_search_status" != 200 || -z "$alpha_job_id" ]]; then
  printf '[FAIL] Alpha could not queue enabled Lead Finder work\n' >&2
  exit 1
fi

# MODULE_ISOLATION_ALPHA_JOB: enabled work completes through the local mock provider.
runuser -u "$app_user" -- sh -c "cd '$root_dir/alpha' && php public/api/jobs/process.php" >"$root_dir/alpha-worker.log" 2>&1
alpha_job_state="$(mariadb -NBe "SELECT CONCAT(status, ':', JSON_UNQUOTE(JSON_EXTRACT(input_data, '$.required_module'))) FROM \`$alpha_db\`.background_jobs WHERE id=$alpha_job_id;")"
if [[ "$alpha_job_state" != 'completed:gesture.lead-finder' ]]; then
  printf '[FAIL] Alpha enabled Lead Finder job did not complete safely: %s\n' "$alpha_job_state" >&2
  exit 1
fi

beta_jobs_before="$(mariadb -NBe "SELECT COUNT(*) FROM \`$beta_db\`.background_jobs;")"
beta_search="$root_dir/beta-search.json"
beta_search_status="$(curl --noproxy '*' --silent --show-error --resolve "beta.test:$beta_port:127.0.0.1" -b "$beta_jar" --output "$beta_search" --write-out '%{http_code}' -H 'Content-Type: application/json' -H "X-CSRF-Token: $beta_csrf" --data '{"query":"synthetic schools in Malaga","max_results":2}' "http://beta.test:$beta_port/api/gestures/lead-finder/search.php")"
beta_jobs_after="$(mariadb -NBe "SELECT COUNT(*) FROM \`$beta_db\`.background_jobs;")"
if [[ "$beta_search_status" != 404 ]] || ! grep -q '"code":"feature_unavailable"' "$beta_search" || [[ "$beta_jobs_after" != "$beta_jobs_before" ]]; then
  printf '[FAIL] Beta disabled API created work or returned the wrong boundary response\n' >&2
  exit 1
fi

# MODULE_ISOLATION_BETA_JOB: pending work fails before provider execution.
beta_user_id="$(mariadb -NBe "SELECT id FROM \`$beta_db\`.users WHERE email='beta.user@example.invalid' LIMIT 1;")"
mariadb "$beta_db" -e "INSERT INTO background_jobs (user_id, job_type, status, input_data, created_at) VALUES ($beta_user_id, 'lead-finder', 'pending', JSON_OBJECT('query', 'synthetic isolation check', 'max_results', 1, 'provider', 'mock', 'required_module', 'gesture.lead-finder', 'locale', 'en'), NOW());"
beta_job_id="$(mariadb -NBe "SELECT MAX(id) FROM \`$beta_db\`.background_jobs WHERE user_id=$beta_user_id AND job_type='lead-finder';")"
if runuser -u "$app_user" -- sh -c "cd '$root_dir/beta' && php public/api/jobs/process.php" >"$root_dir/beta-worker.log" 2>&1; then
  printf '[FAIL] Beta disabled Lead Finder worker unexpectedly succeeded\n' >&2
  exit 1
fi
beta_job_state="$(mariadb -NBe "SELECT CONCAT(status, ':', COALESCE(error_message, '')) FROM \`$beta_db\`.background_jobs WHERE id=$beta_job_id;")"
if [[ "$beta_job_state" != 'failed:feature_unavailable' ]]; then
  printf '[FAIL] Beta disabled job did not fail closed: %s\n' "$beta_job_state" >&2
  exit 1
fi

printf '[PASS] Alpha serves Lead Finder; Beta denies normal users, superadmins, APIs, and queued work\n'

printf '[8/8] Verifying production resources stayed unchanged\n'
fingerprint_after="$(mariadb -NBe "SELECT CONCAT((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source_db'), ':', (SELECT COUNT(*) FROM \`$source_db\`.users), ':', (SELECT COUNT(*) FROM \`$source_db\`.messages), ':', (SELECT COUNT(*) FROM \`$source_db\`.context_documents));")"
production_qdrant_after="$(curl --fail --silent --show-error http://127.0.0.1:6333/collections | jq -r '[.result.collections[].name | select(. == "lex_knowledge_base" or . == "voice_conveniex")] | sort | join(":")')"
if [[ "$fingerprint_after" != "$fingerprint_before" || "$production_qdrant_after" != "$production_qdrant_before" ]]; then
  printf '[FAIL] Production fingerprint changed\n' >&2
  exit 1
fi
printf '[PASS] Production DB and RAG collection set remain unchanged\n'

success=1
printf 'INSTANCE_ISOLATION_VERIFIED=%s\n' "$safe_id"
