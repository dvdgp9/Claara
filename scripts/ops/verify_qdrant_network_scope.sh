#!/usr/bin/env bash
set -euo pipefail

container="${1:-ebonia_qdrant}"

if [[ "$(id -u)" -ne 0 ]]; then
  printf '[FAIL] Run as root so Docker state can be inspected\n' >&2
  exit 1
fi

binding_ips="$(docker inspect "$container" | jq -r '.[0].HostConfig.PortBindings["6333/tcp"][]?.HostIp')"
if [[ "$binding_ips" != "127.0.0.1" ]]; then
  printf '[FAIL] Qdrant REST binding is not loopback-only: %s\n' "${binding_ips:-0.0.0.0}" >&2
  exit 1
fi
printf '[PASS] Docker publishes Qdrant REST only on 127.0.0.1\n'

listeners="$(ss -H -lnt '( sport = :6333 )')"
if [[ "$listeners" != *"127.0.0.1:6333"* ]] || [[ "$listeners" == *"0.0.0.0:6333"* ]] || [[ "$listeners" == *"[::]:6333"* ]]; then
  printf '[FAIL] Unexpected host listener for port 6333: %s\n' "$listeners" >&2
  exit 1
fi
printf '[PASS] Host listener is loopback-only\n'

health="$(curl --fail --silent --show-error http://127.0.0.1:6333/)"
if [[ "$(jq -r '.title // empty' <<<"$health")" != "qdrant - vector search engine" ]]; then
  printf '[FAIL] Local Qdrant health response is invalid\n' >&2
  exit 1
fi
printf '[PASS] Local Qdrant health endpoint responds\n'

collections="$(curl --fail --silent --show-error http://127.0.0.1:6333/collections)"
collection_count="$(jq '.result.collections | length' <<<"$collections")"
if [[ "$collection_count" -lt 1 ]]; then
  printf '[FAIL] No Qdrant collections are available\n' >&2
  exit 1
fi
printf '[PASS] Qdrant collections remain available: %s\n' "$collection_count"
