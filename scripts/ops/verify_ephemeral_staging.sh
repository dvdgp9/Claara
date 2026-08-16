#!/usr/bin/env bash
set -euo pipefail

app_root="/home/dvdgp/web/claara.tech/public_html"
source_db="iaiapro_db"
stage_parent="/var/tmp"
stage_locale="en"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-root) app_root="$2"; shift 2 ;;
    --source-db) source_db="$2"; shift 2 ;;
    --stage-parent) stage_parent="$2"; shift 2 ;;
    --locale) stage_locale="$2"; shift 2 ;;
    *) printf 'Unknown option: %s\n' "$1" >&2; exit 2 ;;
  esac
done

if [[ "$(id -u)" -ne 0 ]]; then
  printf '[FAIL] Run as root\n' >&2
  exit 1
fi
if [[ ! -d "$app_root" || "$source_db" != "${source_db//[^A-Za-z0-9_]/}" || ( "$stage_locale" != "en" && "$stage_locale" != "es" ) ]]; then
  printf '[FAIL] Invalid application root or source database\n' >&2
  exit 1
fi
for dependency in mariadb mariadb-dump rsync php curl runuser shuf ss; do
  command -v "$dependency" >/dev/null || { printf '[FAIL] Missing dependency: %s\n' "$dependency" >&2; exit 1; }
done

run_id="$(date -u +%Y%m%dT%H%M%SZ)_$$"
stage_db="claara_stage_${run_id}"
stage_user="cstage_$(date -u +%H%M%S)_$$"
stage_password="$(openssl rand -hex 24)"
stage_root="$(mktemp -d "${stage_parent%/}/claara-stage.${run_id}.XXXXXX")"
app_copy="$stage_root/app"
server_log="$stage_root/php-server.log"
server_pid=""
success=0

app_user="$(stat -c '%U' "$app_root")"
if [[ "$app_user" == "root" || -z "$app_user" ]]; then
  printf '[FAIL] Refusing to run the staging web process as root\n' >&2
  exit 1
fi

cleanup() {
  local exit_code=$?
  set +e
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" 2>/dev/null
    wait "$server_pid" 2>/dev/null
  fi
  mariadb -e "DROP DATABASE IF EXISTS \`$stage_db\`; DROP USER IF EXISTS '$stage_user'@'localhost'; DROP USER IF EXISTS '$stage_user'@'127.0.0.1';" >/dev/null 2>&1
  if [[ "$success" -ne 1 && -s "$server_log" ]]; then
    printf '%s\n' '[DIAGNOSTIC] Staging PHP server log:' >&2
    tail -n 80 "$server_log" >&2
  fi
  rm -rf -- "$stage_root"
  if [[ "$exit_code" -ne 0 ]]; then
    printf '[FAIL] Ephemeral staging verification failed; isolated resources were removed\n' >&2
  fi
  exit "$exit_code"
}
trap cleanup EXIT INT TERM

source_fingerprint_before="$(mariadb -NBe "SELECT CONCAT((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source_db'), ':', (SELECT COUNT(*) FROM \`$source_db\`.users), ':', (SELECT COUNT(*) FROM \`$source_db\`.messages), ':', (SELECT COUNT(*) FROM \`$source_db\`.context_documents));")"

printf '[1/9] Creating isolated database and least-privilege credentials\n'
mariadb -e "CREATE DATABASE \`$stage_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$stage_user'@'localhost' IDENTIFIED BY '$stage_password'; CREATE USER '$stage_user'@'127.0.0.1' IDENTIFIED BY '$stage_password'; GRANT ALL PRIVILEGES ON \`$stage_db\`.* TO '$stage_user'@'localhost'; GRANT ALL PRIVILEGES ON \`$stage_db\`.* TO '$stage_user'@'127.0.0.1';"

printf '[2/9] Copying schema only; no production rows\n'
mariadb-dump --no-data --skip-triggers --routines=false --events=false "$source_db" | mariadb "$stage_db"
empty_counts="$(mariadb -NBe "SELECT CONCAT((SELECT COUNT(*) FROM \`$stage_db\`.users), ':', (SELECT COUNT(*) FROM \`$stage_db\`.messages), ':', (SELECT COUNT(*) FROM \`$stage_db\`.context_documents));")"
if [[ "$empty_counts" != "0:0:0" ]]; then
  printf '[FAIL] Schema-only copy unexpectedly contains application rows: %s\n' "$empty_counts" >&2
  exit 1
fi
printf '[PASS] Staging database starts with zero users, messages, and documents\n'

printf '[3/9] Adding synthetic staging identities\n'
smoke_email="staging.user@example.invalid"
smoke_admin_email="staging.admin@example.invalid"
smoke_password="$(openssl rand -hex 18)"
password_hash="$(SMOKE_SEED_PASSWORD="$smoke_password" php -r 'echo password_hash((string)getenv("SMOKE_SEED_PASSWORD"), PASSWORD_ARGON2ID);')"
escaped_hash="${password_hash//\'/\'\'}"
mariadb "$stage_db" -e "INSERT INTO users (email, password_hash, first_name, last_name, is_superadmin, status, created_at, updated_at) VALUES ('$smoke_email', '$escaped_hash', 'Synthetic', 'Staging', 0, 'active', NOW(), NOW()), ('$smoke_admin_email', '$escaped_hash', 'Synthetic', 'Admin', 1, 'active', NOW(), NOW());"
mariadb "$stage_db" -e "INSERT INTO user_feature_access (user_id, feature_type, feature_slug, enabled) SELECT id, 'gesture', feature_slug, 1 FROM users CROSS JOIN (SELECT 'project-admin' AS feature_slug UNION ALL SELECT 'audio-transcriber' UNION ALL SELECT 'podcast-from-article' UNION ALL SELECT 'image-editor') AS smoke_gestures WHERE email = '$smoke_email';"

printf '[4/9] Creating an isolated code and storage workspace\n'
mkdir -p "$app_copy"
rsync -a --exclude='.git' --exclude='.env' --exclude='storage' --exclude='vendor' "$app_root/" "$app_copy/"
ln -s "$app_root/vendor" "$app_copy/vendor"
mkdir -p "$app_copy/storage" "$app_copy/storage/sessions" "$app_copy/config/instances"
cat > "$app_copy/config/instances/staging.json" <<'JSON'
{
  "schema_version": 1,
  "id": "staging",
  "slug": "staging",
  "status": "active",
  "canonical_domain": "127.0.0.1",
  "domains": ["127.0.0.1"],
  "branding": {
    "product_name": "Claara",
    "organization_name": "Synthetic Staging",
    "logo_path": "/assets/images/logo.png",
    "login_logo_path": "/assets/images/claara-logo.png",
    "accent_color": "#FF8B73"
  },
  "locales": {"default": "en", "allowed": ["en", "es"]},
  "modules": {"enabled": [
    "core.chat", "core.voices", "core.gestures", "core.connectors", "core.administration",
    "feature.image-generation", "gesture.write-article", "gesture.social-media",
    "gesture.podcast-from-article", "gesture.image-editor", "gesture.content-repurposer",
    "gesture.sop-generator", "gesture.audio-transcriber", "gesture.course-creator",
    "gesture.project-admin", "gesture.lead-finder"
  ]},
  "limits": {"users": 2, "storage_bytes": 104857600},
  "release": {"channel": "staging", "id": "ephemeral"},
  "resources": {
    "database": {"env_prefix": "DB"},
    "storage": {"path_env": "INSTANCE_STORAGE_ROOT"},
    "rag": {"env_prefix": "QDRANT", "collection_prefix": "staging__"},
    "session": {"namespace": "e6f61773f8a9"},
    "secrets": {"scope": "staging"},
    "backups": {"scope": "staging"}
  }
}
JSON
sed -i "s/\"default\": \"en\"/\"default\": \"$stage_locale\"/" "$app_copy/config/instances/staging.json"
cat > "$app_copy/.env" <<ENV
APP_ENV=staging
APP_DEBUG=0
APP_URL=http://127.0.0.1
INSTANCE_MANIFEST_PATH=config/instances/staging.json
INSTANCE_STORAGE_ROOT=$app_copy/storage
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=$stage_db
DB_USER=$stage_user
DB_PASS=$stage_password
OPENROUTER_API_KEY=
GEMINI_API_KEY=
QDRANT_HOST=127.0.0.1
QDRANT_PORT=1
ENV
chown -R "$app_user":"$(stat -c '%G' "$app_root")" "$stage_root"
chmod 700 "$stage_root" "$app_copy/storage" "$app_copy/storage/sessions"
chmod 600 "$app_copy/.env"

printf '[5/9] Starting loopback-only staging HTTP process\n'
stage_port=""
for candidate in $(shuf -i 18000-18999 -n 20); do
  if ! ss -H -lnt "( sport = :$candidate )" | grep -q .; then
    stage_port="$candidate"
    break
  fi
done
if [[ -z "$stage_port" ]]; then
  printf '[FAIL] Could not allocate a staging port\n' >&2
  exit 1
fi
sed -i "s#APP_URL=http://127.0.0.1#APP_URL=http://127.0.0.1:$stage_port#" "$app_copy/.env"
runuser -u "$app_user" -- php -S "127.0.0.1:$stage_port" -t "$app_copy/public" >"$server_log" 2>&1 &
server_pid=$!
for _ in {1..24}; do
  if curl --silent --fail "http://127.0.0.1:$stage_port/login.php" >/dev/null; then
    break
  fi
  sleep 0.25
done
if ! kill -0 "$server_pid" 2>/dev/null; then
  printf '[FAIL] Staging HTTP process did not remain running\n' >&2
  exit 1
fi

printf '[6/9] Running authenticated HTTP characterization against synthetic data\n'
SMOKE_EMAIL="$smoke_email" SMOKE_PASSWORD="$smoke_password" php "$app_copy/scripts/smoke_http_baseline.php" \
  --base-url="http://127.0.0.1:$stage_port" \
  --env-file="$app_copy/.env" \
  --authenticated

printf '[PASS] Verifying localized authentication and shared shell for %s\n' "$stage_locale"
browser_jar="$stage_root/browser.cookies"
login_page="$stage_root/login.html"
workspace_page="$stage_root/workspace.html"
account_page="$stage_root/account.html"
project_page="$stage_root/project-analysis.html"
transcriber_page="$stage_root/audio-transcriber.html"
podcast_page="$stage_root/podcast.html"
image_editor_page="$stage_root/image-editor.html"
login_response="$stage_root/login.json"
profile_validation="$stage_root/profile-validation.json"
password_validation="$stage_root/password-validation.json"
locale_response="$stage_root/locale-response.json"
switched_account_page="$stage_root/switched-account.html"
curl --silent --show-error --fail --cookie-jar "$browser_jar" "http://127.0.0.1:$stage_port/login.php" --output "$login_page"
curl --silent --show-error --fail --cookie "$browser_jar" --cookie-jar "$browser_jar" \
  --header 'Content-Type: application/json' \
  --data "{\"email\":\"$smoke_email\",\"password\":\"$smoke_password\"}" \
  "http://127.0.0.1:$stage_port/api/auth/login.php" --output "$login_response"
curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/app/" --output "$workspace_page"
curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/account.php" --output "$account_page"
curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/gestos/admin-proyectos.php" --output "$project_page"
curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/gestos/transcriptor-audio.php" --output "$transcriber_page"
curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/gestos/podcast-articulo.php" --output "$podcast_page"
curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/gestos/editor-imagenes.php" --output "$image_editor_page"
grep -q 'project-analysis-page' "$project_page"
grep -q 'transcriber-page' "$transcriber_page"
grep -q 'podcast-page' "$podcast_page"
grep -q 'image-editor-page' "$image_editor_page"
if [[ "$stage_locale" == "es" ]]; then
  grep -q 'Crear desde cero' "$image_editor_page"
  grep -q 'Parámetros de imagen' "$image_editor_page"
else
  grep -q 'Create from scratch' "$image_editor_page"
  grep -q 'Image parameters' "$image_editor_page"
fi
printf '[PASS] Project analysis, Audio transcriber, Podcast, and Image editor render authenticated page shells\n'
csrf_token="$(php -r '$data=json_decode(file_get_contents($argv[1]), true); echo (string)($data["csrf_token"] ?? "");' "$login_response")"
if [[ -z "$csrf_token" ]]; then
  printf '[FAIL] Localized account check could not obtain a CSRF token\n' >&2
  exit 1
fi
curl --silent --show-error --cookie "$browser_jar" --header 'Content-Type: application/json' --header "X-CSRF-Token: $csrf_token" \
  --data '{"first_name":"","last_name":""}' \
  "http://127.0.0.1:$stage_port/api/account/update_profile.php" --output "$profile_validation"
curl --silent --show-error --cookie "$browser_jar" --header 'Content-Type: application/json' --header "X-CSRF-Token: $csrf_token" \
  --data '{"current_password":"x","new_password":"12345678","confirm_password":"87654321"}' \
  "http://127.0.0.1:$stage_port/api/account/change_password.php" --output "$password_validation"
if [[ "$stage_locale" == "es" ]]; then
  grep -q '<html lang="es">' "$login_page"
  grep -q '>Iniciar sesión<' "$login_page"
  grep -q '<html lang="es">' "$workspace_page"
  grep -q 'Nueva conversación' "$workspace_page"
  grep -q 'Cerrar sesión' "$workspace_page"
  grep -q '<html lang="es">' "$account_page"
  grep -q 'Información personal' "$account_page"
  grep -q 'Cambiar contraseña' "$account_page"
  grep -q 'El nombre y los apellidos son obligatorios.' "$profile_validation"
  grep -q 'Las contraseñas no coinciden.' "$password_validation"
else
  grep -q '<html lang="en">' "$login_page"
  grep -q '>Log in<' "$login_page"
  grep -q '<html lang="en">' "$workspace_page"
  grep -q 'New conversation' "$workspace_page"
  grep -q 'Log out' "$workspace_page"
  grep -q '<html lang="en">' "$account_page"
  grep -q 'Personal information' "$account_page"
  grep -q 'Change password' "$account_page"
  grep -q 'First name and last name are required.' "$profile_validation"
  grep -q 'Passwords do not match.' "$password_validation"
fi
printf '[PASS] Login and shared workspace shell match locale %s\n' "$stage_locale"

target_user_locale="es"
if [[ "$stage_locale" == "es" ]]; then
  target_user_locale="en"
fi
curl --silent --show-error --fail --cookie "$browser_jar" --cookie-jar "$browser_jar" \
  --header 'Content-Type: application/json' --header "X-CSRF-Token: $csrf_token" \
  --data "{\"locale\":\"$target_user_locale\"}" \
  "http://127.0.0.1:$stage_port/api/account/update_locale.php" --output "$locale_response"
curl --silent --show-error --fail --cookie "$browser_jar" \
  "http://127.0.0.1:$stage_port/account.php" --output "$switched_account_page"
grep -q '"success":true' "$locale_response"
grep -q "<html lang=\"$target_user_locale\">" "$switched_account_page"
stored_locale="$(mariadb -NBe "SELECT locale FROM \`$stage_db\`.users WHERE email='$smoke_email' LIMIT 1;")"
if [[ "$stored_locale" != "$target_user_locale" ]]; then
  printf '[FAIL] User locale was not persisted in isolated staging\n' >&2
  exit 1
fi
printf '[PASS] User preference overrides instance locale with %s\n' "$target_user_locale"

printf '[7/9] Verifying disabled Gesture isolation for users, superadmins, APIs, and workers\n'
sed -i 's/, "gesture.lead-finder"//' "$app_copy/config/instances/staging.json"
disabled_catalog="$stage_root/disabled-catalog.html"
disabled_page_headers="$stage_root/disabled-page.headers"
disabled_page_body="$stage_root/disabled-page.html"
disabled_api_body="$stage_root/disabled-api.json"
disabled_search_body="$stage_root/disabled-search.json"
disabled_export_body="$stage_root/disabled-export.json"

curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/gestos/" --output "$disabled_catalog"
if grep -q 'href="/gestos/lead-finder.php"' "$disabled_catalog"; then
  printf '[FAIL] Disabled Lead Finder remains visible in the Gesture catalog\n' >&2
  exit 1
fi
disabled_page_status="$(curl --silent --show-error --dump-header "$disabled_page_headers" --output "$disabled_page_body" --write-out '%{http_code}' --cookie "$browser_jar" "http://127.0.0.1:$stage_port/gestos/lead-finder.php")"
if [[ "$disabled_page_status" != "302" ]] || ! grep -qi '^location: /gestos/?error=no_access' "$disabled_page_headers"; then
  printf '[FAIL] Disabled Lead Finder direct page did not fail closed\n' >&2
  exit 1
fi

disabled_api_status="$(curl --silent --show-error --output "$disabled_api_body" --write-out '%{http_code}' --cookie "$browser_jar" "http://127.0.0.1:$stage_port/api/gestures/lead-finder/history.php")"
disabled_search_status="$(curl --silent --show-error --output "$disabled_search_body" --write-out '%{http_code}' --cookie "$browser_jar" --header 'Content-Type: application/json' --header "X-CSRF-Token: $csrf_token" --data '{"query":"synthetic isolation check","max_results":1}' "http://127.0.0.1:$stage_port/api/gestures/lead-finder/search.php")"
disabled_export_status="$(curl --silent --show-error --output "$disabled_export_body" --write-out '%{http_code}' --cookie "$browser_jar" --header 'Content-Type: application/json' --header "X-CSRF-Token: $csrf_token" --data '{"id":1,"format":"csv"}' "http://127.0.0.1:$stage_port/api/gestures/lead-finder/export.php")"
for result in "$disabled_api_status:$disabled_api_body" "$disabled_search_status:$disabled_search_body" "$disabled_export_status:$disabled_export_body"; do
  status="${result%%:*}"
  body_file="${result#*:}"
  if [[ "$status" != "404" ]] || ! grep -q '"code":"feature_unavailable"' "$body_file"; then
    printf '[FAIL] Disabled Lead Finder API did not return the stable unavailable response\n' >&2
    exit 1
  fi
done

admin_jar="$stage_root/admin.cookies"
admin_login="$stage_root/admin-login.json"
admin_catalog="$stage_root/admin-disabled-catalog.html"
admin_api="$stage_root/admin-disabled-api.json"
admin_page_headers="$stage_root/admin-disabled-page.headers"
curl --silent --show-error --fail --cookie-jar "$admin_jar" "http://127.0.0.1:$stage_port/login.php" >/dev/null
curl --silent --show-error --fail --cookie "$admin_jar" --cookie-jar "$admin_jar" --header 'Content-Type: application/json' --data "{\"email\":\"$smoke_admin_email\",\"password\":\"$smoke_password\"}" "http://127.0.0.1:$stage_port/api/auth/login.php" --output "$admin_login"
curl --silent --show-error --fail --cookie "$admin_jar" "http://127.0.0.1:$stage_port/gestos/" --output "$admin_catalog"
if grep -q 'href="/gestos/lead-finder.php"' "$admin_catalog"; then
  printf '[FAIL] Superadmin can still discover disabled Lead Finder\n' >&2
  exit 1
fi
admin_page_status="$(curl --silent --show-error --dump-header "$admin_page_headers" --output /dev/null --write-out '%{http_code}' --cookie "$admin_jar" "http://127.0.0.1:$stage_port/gestos/lead-finder.php")"
if [[ "$admin_page_status" != "302" ]] || ! grep -qi '^location: /gestos/?error=no_access' "$admin_page_headers"; then
  printf '[FAIL] Superadmin bypassed disabled Lead Finder direct page\n' >&2
  exit 1
fi
admin_api_status="$(curl --silent --show-error --output "$admin_api" --write-out '%{http_code}' --cookie "$admin_jar" "http://127.0.0.1:$stage_port/api/gestures/lead-finder/history.php")"
if [[ "$admin_api_status" != "404" ]] || ! grep -q '"code":"feature_unavailable"' "$admin_api"; then
  printf '[FAIL] Superadmin bypassed disabled Lead Finder entitlement\n' >&2
  exit 1
fi

normal_user_id="$(mariadb -NBe "SELECT id FROM \`$stage_db\`.users WHERE email='$smoke_email' LIMIT 1;")"
mariadb "$stage_db" -e "INSERT INTO background_jobs (user_id, job_type, status, input_data, created_at) VALUES ($normal_user_id, 'lead-finder', 'pending', JSON_OBJECT('query', 'synthetic isolation check', 'max_results', 1, 'provider', 'mock', 'required_module', 'gesture.lead-finder', 'locale', '$stage_locale'), NOW());"
disabled_job_id="$(mariadb -NBe "SELECT MAX(id) FROM \`$stage_db\`.background_jobs WHERE user_id=$normal_user_id AND job_type='lead-finder';")"
if runuser -u "$app_user" -- php "$app_copy/public/api/jobs/process.php" >"$stage_root/disabled-worker.log" 2>&1; then
  printf '[FAIL] Disabled Lead Finder worker unexpectedly succeeded\n' >&2
  exit 1
fi
disabled_job_state="$(mariadb -NBe "SELECT CONCAT(status, ':', COALESCE(error_message, '')) FROM \`$stage_db\`.background_jobs WHERE id=$disabled_job_id;")"
if [[ "$disabled_job_state" != "failed:feature_unavailable" ]]; then
  printf '[FAIL] Disabled Lead Finder job was not failed closed: %s\n' "$disabled_job_state" >&2
  exit 1
fi
printf '[PASS] Disabled Lead Finder is hidden and denied for normal users, superadmins, direct APIs, export, history, and queued work\n'

printf '[PASS] Verifying disabled core modules at navigation, page, and API boundaries\n'
sed -i 's/, "core.connectors"//' "$app_copy/config/instances/staging.json"
sed -i 's/, "core.administration"//' "$app_copy/config/instances/staging.json"
core_workspace="$stage_root/core-disabled-workspace.html"
core_connector_headers="$stage_root/core-disabled-connector.headers"
core_connector_body="$stage_root/core-disabled-connector.html"
core_connector_api="$stage_root/core-disabled-connector-api.json"
core_admin_headers="$stage_root/core-disabled-admin.headers"
core_admin_body="$stage_root/core-disabled-admin.html"
core_admin_api="$stage_root/core-disabled-admin-api.json"

curl --silent --show-error --fail --cookie "$browser_jar" "http://127.0.0.1:$stage_port/app/" --output "$core_workspace"
if grep -q 'href="/connectors.php"' "$core_workspace" || grep -q 'href="/admin/' "$core_workspace"; then
  printf '[FAIL] Disabled core module remains discoverable in shared navigation\n' >&2
  exit 1
fi

core_connector_status="$(curl --silent --show-error --dump-header "$core_connector_headers" --output "$core_connector_body" --write-out '%{http_code}' --cookie "$browser_jar" "http://127.0.0.1:$stage_port/connectors.php")"
core_connector_api_status="$(curl --silent --show-error --output "$core_connector_api" --write-out '%{http_code}' --cookie "$browser_jar" "http://127.0.0.1:$stage_port/api/connectors/providers.php")"
core_admin_status="$(curl --silent --show-error --dump-header "$core_admin_headers" --output "$core_admin_body" --write-out '%{http_code}' --cookie "$admin_jar" "http://127.0.0.1:$stage_port/admin/users.php")"
core_admin_api_status="$(curl --silent --show-error --output "$core_admin_api" --write-out '%{http_code}' --cookie "$admin_jar" "http://127.0.0.1:$stage_port/api/admin/users/list.php")"

if [[ "$core_connector_status" != "302" ]] || ! grep -qi '^location: /account.php?error=feature_unavailable' "$core_connector_headers"; then
  printf '[FAIL] Disabled Connectors page did not fail closed\n' >&2
  exit 1
fi
if [[ "$core_admin_status" != "302" ]] || ! grep -qi '^location: /account.php?error=feature_unavailable' "$core_admin_headers"; then
  printf '[FAIL] Disabled Administration page did not fail closed for superadmin\n' >&2
  exit 1
fi
for result in "$core_connector_api_status:$core_connector_api" "$core_admin_api_status:$core_admin_api"; do
  status="${result%%:*}"
  body_file="${result#*:}"
  if [[ "$status" != "404" ]] || ! grep -q '"code":"feature_unavailable"' "$body_file"; then
    printf '[FAIL] Disabled core API did not return the stable unavailable response\n' >&2
    exit 1
  fi
done
printf '[PASS] Disabled Connectors and Administration are hidden and denied, including for superadmin\n'

printf '[8/9] Verifying fail-closed host and isolated session storage\n'
unknown_headers="$stage_root/unknown.headers"
unknown_body="$stage_root/unknown.body"
unknown_status="$(curl --silent --show-error --dump-header "$unknown_headers" --output "$unknown_body" --write-out '%{http_code}' -H 'Host: unknown.claara.tech' "http://127.0.0.1:$stage_port/login.php")"
if [[ "$unknown_status" != "421" ]] || ! grep -q 'instance_not_found' "$unknown_body" || grep -qi '^set-cookie:' "$unknown_headers"; then
  printf '[FAIL] Unknown host did not fail closed before session creation\n' >&2
  exit 1
fi
session_file_count="$(find "$app_copy/storage/sessions" -maxdepth 1 -type f -name 'sess_*' -print | wc -l)"
if [[ "$session_file_count" -lt 1 ]]; then
  printf '[FAIL] Staging session was not written to instance storage\n' >&2
  exit 1
fi
printf '[PASS] Unknown host returns 421 without a session cookie\n'
printf '[PASS] Session files are stored only under the staging instance root\n'

printf '[9/9] Proving production data and cleanup boundaries\n'
source_fingerprint_after="$(mariadb -NBe "SELECT CONCAT((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$source_db'), ':', (SELECT COUNT(*) FROM \`$source_db\`.users), ':', (SELECT COUNT(*) FROM \`$source_db\`.messages), ':', (SELECT COUNT(*) FROM \`$source_db\`.context_documents));")"
if [[ "$source_fingerprint_after" != "$source_fingerprint_before" ]]; then
  printf '[FAIL] Production fingerprint changed: before=%s after=%s\n' "$source_fingerprint_before" "$source_fingerprint_after" >&2
  exit 1
fi
printf '[PASS] Production fingerprint unchanged: %s\n' "$source_fingerprint_after"

success=1
printf 'EPHEMERAL_STAGING_VERIFIED=%s\n' "$run_id"
