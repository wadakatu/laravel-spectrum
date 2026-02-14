#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEMO_DIR="${ROOT_DIR}/demo-app"
VALIDATOR="${DEMO_DIR}/tools/validate_openapi.php"

if [[ ! -f "${VALIDATOR}" ]]; then
  echo "Validator script not found: ${VALIDATOR}" >&2
  exit 1
fi

timestamp="$(date '+%Y%m%d-%H%M%S')"
run_dir="${DEMO_DIR}/reports/${timestamp}"
mkdir -p "${run_dir}"

matrix=(
  "laravel-11-app|3.0.0"
  "laravel-11-app|3.1.0"
  "laravel-12-app|3.0.0"
  "laravel-12-app|3.1.0"
)

declare -a rows=()
overall_status=0

echo "OpenAPI compliance check started."
echo "Run directory: ${run_dir}"
echo

for item in "${matrix[@]}"; do
  IFS='|' read -r app version <<<"${item}"
  app_dir="${DEMO_DIR}/${app}"
  spec_file="${run_dir}/${app}-openapi-${version}.json"
  log_file="${run_dir}/${app}-openapi-${version}.log"
  status="PASS"
  note="ok"

  echo "==> ${app} / OpenAPI ${version}"

  if [[ ! -d "${app_dir}" ]]; then
    status="FAIL"
    note="app directory not found"
    overall_status=1
  else
    if ! (
      cd "${app_dir}"
      SPECTRUM_OPENAPI_VERSION="${version}" php artisan spectrum:generate --no-cache --output="${spec_file}"
    ) >"${log_file}" 2>&1; then
      status="FAIL"
      note="generation failed"
      overall_status=1
    elif ! php "${VALIDATOR}" "${spec_file}" "${version}" "${app}" >>"${log_file}" 2>&1; then
      status="FAIL"
      note="validation failed"
      overall_status=1
    fi
  fi

  rows+=("${app}|${version}|${status}|${note}|${spec_file}|${log_file}")
  echo "    ${status} (${note})"
  echo
done

summary_file="${run_dir}/summary.md"
{
  echo "# OpenAPI Compliance Summary"
  echo
  echo "- Generated at: $(date '+%Y-%m-%d %H:%M:%S %z')"
  echo "- Workspace: ${ROOT_DIR}"
  echo "- Overall result: $([[ ${overall_status} -eq 0 ]] && echo "PASS" || echo "FAIL")"
  echo
  echo "| App | Version | Result | Note | Spec | Log |"
  echo "|---|---|---|---|---|---|"
  for row in "${rows[@]}"; do
    IFS='|' read -r app version status note spec_file log_file <<<"${row}"
    echo "| ${app} | ${version} | ${status} | ${note} | \`${spec_file}\` | \`${log_file}\` |"
  done
} >"${summary_file}"

echo "Summary: ${summary_file}"
if [[ ${overall_status} -ne 0 ]]; then
  echo "OpenAPI compliance check failed. Review logs in ${run_dir}." >&2
  exit 1
fi

echo "OpenAPI compliance check passed."
