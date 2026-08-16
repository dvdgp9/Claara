#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
  echo "Usage: sudo bash scripts/ops/verify_restore_instance.sh --backup-dir PATH"
}

backup_dir=""
qdrant_url="http://127.0.0.1:6333"

while (($# > 0)); do
  case "$1" in
    --backup-dir) backup_dir="${2:-}"; shift 2 ;;
    --qdrant-url) qdrant_url="${2:-}"; shift 2 ;;
    --help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [[ $EUID -ne 0 ]]; then
  echo "This script must run as root" >&2
  exit 2
fi
if [[ -z $backup_dir || ! -d $backup_dir ]]; then
  echo "Backup directory does not exist" >&2
  exit 2
fi

backup_dir="$(realpath "$backup_dir")"
case "$backup_dir" in
  /var/backups/claara/*) ;;
  *) echo "Backup must stay under /var/backups/claara" >&2; exit 2 ;;
esac
if [[ ! -f $backup_dir/COMPLETE || -f $backup_dir/INCOMPLETE ]]; then
  echo "Backup is not marked complete" >&2
  exit 2
fi

for command_name in mariadb mariadb-check gzip tar curl jq sha256sum diff realpath; do
  command -v "$command_name" >/dev/null || {
    echo "Missing required command: $command_name" >&2
    exit 2
  }
done

manifest="$backup_dir/manifest.json"
db_dump="$(find "$backup_dir/database" -maxdepth 1 -type f -name '*.sql.gz' -print -quit)"
if [[ ! -f $manifest || -z $db_dump || ! -f $backup_dir/files/storage.tar.gz || ! -f $backup_dir/qdrant/collections.tsv ]]; then
  echo "Backup is missing required components" >&2
  exit 2
fi

run_id="$(date -u +%Y%m%dT%H%M%SZ)_$$"
temp_db="claara_restore_verify_${run_id}"
if [[ ! $temp_db =~ ^claara_restore_verify_[0-9TZ]+_[0-9]+$ ]]; then
  echo "Unsafe temporary database name" >&2
  exit 2
fi
restore_dir="$(mktemp -d /var/tmp/claara-restore-verify.XXXXXX)"
actual_storage_checksums="$restore_dir/storage-files.sha256"
declare -a temp_collections=()

cleanup() {
  local exit_code=$?
  set +e
  for collection in "${temp_collections[@]}"; do
    if [[ $collection =~ ^restore_verify_[A-Za-z0-9_]+$ ]]; then
      curl -fsS -X DELETE "$qdrant_url/collections/$collection?timeout=60" >/dev/null 2>&1
    fi
  done
  if [[ $temp_db =~ ^claara_restore_verify_[0-9TZ]+_[0-9]+$ ]]; then
    mariadb -e "DROP DATABASE IF EXISTS \`${temp_db}\`;" >/dev/null 2>&1
  fi
  if [[ $restore_dir == /var/tmp/claara-restore-verify.* && -d $restore_dir ]]; then
    rm -rf -- "$restore_dir"
  fi
  exit "$exit_code"
}
trap cleanup EXIT

echo "[1/6] Verifying backup checksums"
(
  cd "$backup_dir"
  sha256sum -c checksums.sha256
)

echo "[2/6] Restoring MariaDB into isolated database $temp_db"
mariadb -e "CREATE DATABASE \`${temp_db}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gzip -dc "$db_dump" \
  | sed "s/\`iaiapro_db\`/\`${temp_db}\`/g" \
  | mariadb

expected_tables="$(jq -er '.database_metrics.tables' "$manifest")"
expected_users="$(jq -er '.database_metrics.users' "$manifest")"
expected_messages="$(jq -er '.database_metrics.messages' "$manifest")"
expected_documents="$(jq -er '.database_metrics.context_documents' "$manifest")"
actual_tables="$(mariadb -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${temp_db}';")"
actual_users="$(mariadb -NBe "SELECT COUNT(*) FROM \`${temp_db}\`.users;")"
actual_messages="$(mariadb -NBe "SELECT COUNT(*) FROM \`${temp_db}\`.messages;")"
actual_documents="$(mariadb -NBe "SELECT COUNT(*) FROM \`${temp_db}\`.context_documents;")"

[[ $actual_tables == "$expected_tables" ]]
[[ $actual_users == "$expected_users" ]]
[[ $actual_messages == "$expected_messages" ]]
[[ $actual_documents == "$expected_documents" ]]
mariadb-check --silent "$temp_db"
echo "[PASS] MariaDB schema, key counts, and table checks"

echo "[3/6] Restoring persistent files into isolated directory"
tar -xzf "$backup_dir/files/storage.tar.gz" -C "$restore_dir"
(
  cd "$restore_dir"
  find storage -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum
) > "$actual_storage_checksums"
diff -u "$backup_dir/files/storage-files.sha256" "$actual_storage_checksums" >/dev/null
echo "[PASS] Persistent file contents"

echo "[4/6] Restoring Qdrant snapshots into temporary collections"
while IFS=$'\t' read -r source_collection expected_points expected_vector_size snapshot_file; do
  [[ $source_collection == collection ]] && continue
  [[ -z $source_collection ]] && continue
  safe_source="${source_collection//[^A-Za-z0-9]/_}"
  temp_collection="restore_verify_${run_id//[^A-Za-z0-9]/_}_${safe_source}"
  if [[ ! $temp_collection =~ ^restore_verify_[A-Za-z0-9_]+$ ]]; then
    echo "Unsafe temporary Qdrant collection name" >&2
    exit 2
  fi
  snapshot_path="$backup_dir/qdrant/$snapshot_file"
  test -s "$snapshot_path"
  temp_collections+=("$temp_collection")
  restore_result="$(curl -fsS -X POST \
    "$qdrant_url/collections/$temp_collection/snapshots/upload?priority=snapshot&wait=true" \
    -F "snapshot=@${snapshot_path}")"
  [[ $(jq -er '.result' <<< "$restore_result") == true ]]
  collection_info="$(curl -fsS "$qdrant_url/collections/$temp_collection")"
  actual_points="$(jq -er '.result.points_count' <<< "$collection_info")"
  actual_vector_size="$(jq -er '.result.config.params.vectors.size' <<< "$collection_info")"
  [[ $actual_points == "$expected_points" ]]
  [[ $actual_vector_size == "$expected_vector_size" ]]
  echo "[PASS] Qdrant $source_collection: $actual_points points, vector size $actual_vector_size"
done < "$backup_dir/qdrant/collections.tsv"

echo "[5/6] Verifying protected configuration artifacts"
test -s "$backup_dir/config/application.env"
test -s "$backup_dir/config/web-config.tar.gz"
[[ $(stat -c '%a' "$backup_dir/config/application.env") == 600 ]]
tar -tzf "$backup_dir/config/web-config.tar.gz" >/dev/null
echo "[PASS] Environment and web configuration artifacts"

echo "[6/6] Restore verification complete"
echo "RESTORE_VERIFIED=$backup_dir"
echo "Temporary database, files, and Qdrant collections will now be removed."

