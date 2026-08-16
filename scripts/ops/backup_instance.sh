#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
  echo "Usage: sudo bash scripts/ops/backup_instance.sh --instance SLUG --app-root PATH --db-name NAME --web-config-root PATH"
}

instance=""
app_root=""
db_name=""
web_config_root=""
backup_root="/var/backups/claara/instance-platform"
qdrant_url="http://127.0.0.1:6333"

while (($# > 0)); do
  case "$1" in
    --instance) instance="${2:-}"; shift 2 ;;
    --app-root) app_root="${2:-}"; shift 2 ;;
    --db-name) db_name="${2:-}"; shift 2 ;;
    --web-config-root) web_config_root="${2:-}"; shift 2 ;;
    --backup-root) backup_root="${2:-}"; shift 2 ;;
    --qdrant-url) qdrant_url="${2:-}"; shift 2 ;;
    --help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [[ $EUID -ne 0 ]]; then
  echo "This script must run as root" >&2
  exit 2
fi
if [[ ! $instance =~ ^[a-z0-9][a-z0-9-]{0,62}$ ]]; then
  echo "Invalid instance slug" >&2
  exit 2
fi
if [[ ! $db_name =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "Invalid database name" >&2
  exit 2
fi
if [[ -z $app_root || ! -d $app_root || ! -d $app_root/storage || ! -f $app_root/.env ]]; then
  echo "Application root must contain storage/ and .env" >&2
  exit 2
fi
if [[ -z $web_config_root || ! -d $web_config_root ]]; then
  echo "Invalid web configuration root" >&2
  exit 2
fi

app_root="$(realpath "$app_root")"
web_config_root="$(realpath "$web_config_root")"
backup_root="$(realpath -m "$backup_root")"
case "$backup_root" in
  /var/backups/claara|/var/backups/claara/*) ;;
  *) echo "Backup root must stay under /var/backups/claara" >&2; exit 2 ;;
esac

for command_name in mariadb mariadb-dump gzip tar curl jq sha256sum git realpath; do
  command -v "$command_name" >/dev/null || {
    echo "Missing required command: $command_name" >&2
    exit 2
  }
done

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="${backup_root}/${instance}-${timestamp}"
if [[ -e $backup_dir ]]; then
  echo "Backup target already exists: $backup_dir" >&2
  exit 2
fi

umask 077
install -d -m 700 "$backup_root" "$backup_dir" "$backup_dir/database" "$backup_dir/files" "$backup_dir/config" "$backup_dir/qdrant"
touch "$backup_dir/INCOMPLETE"

on_error() {
  local exit_code=$?
  echo "Backup failed; partial data retained at $backup_dir with INCOMPLETE marker" >&2
  exit "$exit_code"
}
trap on_error ERR

echo "[1/7] Recording source metadata"
git_commit="$(git -C "$app_root" rev-parse HEAD)"
git -C "$app_root" status --short > "$backup_dir/config/git-status.txt"
php_version="$(php -r 'echo PHP_VERSION;')"
mariadb_version="$(mariadb -NBe 'SELECT VERSION();')"
qdrant_version="$(curl -fsS "$qdrant_url/" | jq -er '.version')"

read -r db_table_count db_size_mb < <(
  mariadb -NBe "SELECT COUNT(*), ROUND(COALESCE(SUM(data_length+index_length),0)/1024/1024,2) FROM information_schema.tables WHERE table_schema='${db_name}';"
)
db_users="$(mariadb -NBe "SELECT COUNT(*) FROM \`${db_name}\`.users;")"
db_messages="$(mariadb -NBe "SELECT COUNT(*) FROM \`${db_name}\`.messages;")"
db_documents="$(mariadb -NBe "SELECT COUNT(*) FROM \`${db_name}\`.context_documents;")"

echo "[2/7] Dumping MariaDB"
mariadb-dump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  --databases "$db_name" \
  | gzip -9 > "$backup_dir/database/${db_name}.sql.gz"
gzip -t "$backup_dir/database/${db_name}.sql.gz"

echo "[3/7] Archiving persistent files"
(
  cd "$app_root"
  find storage -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum
) > "$backup_dir/files/storage-files.sha256"
tar -C "$app_root" -czf "$backup_dir/files/storage.tar.gz" storage
tar -tzf "$backup_dir/files/storage.tar.gz" >/dev/null

echo "[4/7] Archiving instance and web configuration"
install -m 600 "$app_root/.env" "$backup_dir/config/application.env"
tar -C "$(dirname "$web_config_root")" -czf "$backup_dir/config/web-config.tar.gz" "$(basename "$web_config_root")"
tar -tzf "$backup_dir/config/web-config.tar.gz" >/dev/null

echo "[5/7] Creating and downloading Qdrant collection snapshots"
printf 'collection\tpoints\tvector_size\tsnapshot_file\n' > "$backup_dir/qdrant/collections.tsv"
mapfile -t collections < <(curl -fsS "$qdrant_url/collections" | jq -er '.result.collections[].name' | LC_ALL=C sort)
for collection in "${collections[@]}"; do
  if [[ ! $collection =~ ^[A-Za-z0-9_.-]+$ ]]; then
    echo "Unsafe Qdrant collection name: $collection" >&2
    exit 2
  fi
  collection_info="$(curl -fsS "$qdrant_url/collections/$collection")"
  points="$(jq -er '.result.points_count' <<< "$collection_info")"
  vector_size="$(jq -er '.result.config.params.vectors.size' <<< "$collection_info")"
  snapshot_response="$(curl -fsS -X POST "$qdrant_url/collections/$collection/snapshots?wait=true")"
  snapshot_name="$(jq -er '.result.name' <<< "$snapshot_response")"
  if [[ ! $snapshot_name =~ ^[A-Za-z0-9_.-]+$ ]]; then
    echo "Unsafe Qdrant snapshot name: $snapshot_name" >&2
    exit 2
  fi
  snapshot_file="${collection}/${snapshot_name}"
  install -d -m 700 "$backup_dir/qdrant/$collection"
  curl -fsS "$qdrant_url/collections/$collection/snapshots/$snapshot_name" -o "$backup_dir/qdrant/$snapshot_file"
  test -s "$backup_dir/qdrant/$snapshot_file"
  printf '%s\t%s\t%s\t%s\n' "$collection" "$points" "$vector_size" "$snapshot_file" >> "$backup_dir/qdrant/collections.tsv"
  curl -fsS -X DELETE "$qdrant_url/collections/$collection/snapshots/$snapshot_name?wait=true" >/dev/null
done

echo "[6/7] Writing manifest and checksums"
jq -n \
  --arg instance "$instance" \
  --arg created_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg app_root "$app_root" \
  --arg db_name "$db_name" \
  --arg git_commit "$git_commit" \
  --arg php_version "$php_version" \
  --arg mariadb_version "$mariadb_version" \
  --arg qdrant_version "$qdrant_version" \
  --argjson db_table_count "$db_table_count" \
  --argjson db_size_mb "$db_size_mb" \
  --argjson db_users "$db_users" \
  --argjson db_messages "$db_messages" \
  --argjson db_documents "$db_documents" \
  '{
    instance: $instance,
    created_at: $created_at,
    source: {app_root: $app_root, database: $db_name, git_commit: $git_commit},
    versions: {php: $php_version, mariadb: $mariadb_version, qdrant: $qdrant_version},
    database_metrics: {
      tables: $db_table_count,
      size_mb: $db_size_mb,
      users: $db_users,
      messages: $db_messages,
      context_documents: $db_documents
    }
  }' > "$backup_dir/manifest.json"

(
  cd "$backup_dir"
  find . -type f \
    ! -name checksums.sha256 \
    ! -name INCOMPLETE \
    ! -name COMPLETE \
    -print0 \
    | LC_ALL=C sort -z \
    | xargs -0 sha256sum
) > "$backup_dir/checksums.sha256"
(
  cd "$backup_dir"
  sha256sum -c checksums.sha256 >/dev/null
)

echo "[7/7] Marking backup complete"
rm "$backup_dir/INCOMPLETE"
touch "$backup_dir/COMPLETE"
chmod -R go-rwx "$backup_dir"
trap - ERR

echo "BACKUP_COMPLETE=$backup_dir"

