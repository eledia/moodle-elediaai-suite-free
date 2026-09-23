#!/usr/bin/env bash
# Build a clean Moodle Plugins Directory ZIP for local_literag.
# Usage: ./bin/release.sh [output-dir]   (default: /tmp)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "${SCRIPT_DIR}")"
OUT_DIR="${1:-/tmp}"

cd "${PLUGIN_DIR}"

GIT_ROOT="$(git rev-parse --show-toplevel)"
PLUGIN_PATH="$(git rev-parse --show-prefix)"
PLUGIN_PATH="${PLUGIN_PATH%/}"

COMPONENT="$(sed -nE "s/.*\\\$plugin->component[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" version.php)"
SHORTNAME="${COMPONENT#*_}"
VERSION="$(sed -nE "s/.*\\\$plugin->release[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" version.php)"

if [[ -z "${COMPONENT}" || -z "${SHORTNAME}" || -z "${VERSION}" || "${COMPONENT}" == "${SHORTNAME}" ]]; then
    echo "ERROR: could not parse component/release from version.php" >&2
    exit 2
fi

dirty="$(git -C "${GIT_ROOT}" status --porcelain -- "${PLUGIN_PATH}" || true)"
if [[ -n "${dirty}" ]]; then
    echo "ERROR: plugin has uncommitted changes. Commit or stash first." >&2
    echo "${dirty}"
    exit 3
fi

mkdir -p "${OUT_DIR}"
ZIP="${OUT_DIR}/${SHORTNAME}-${VERSION}.zip"
TMPDIR="$(mktemp -d)"
trap 'rm -rf "${TMPDIR}"' EXIT

echo "Building ${ZIP}"
echo "  component:   ${COMPONENT}"
echo "  shortname:   ${SHORTNAME}"
echo "  version:     ${VERSION}"
echo "  plugin path: ${PLUGIN_PATH}"
echo

git -C "${GIT_ROOT}" archive \
    --format=tar \
    --prefix="${SHORTNAME}/" \
    HEAD:"${PLUGIN_PATH}" \
    | tar -x -C "${TMPDIR}"

rm -rf "${TMPDIR}/${SHORTNAME}/.github"
rm -f "${ZIP}"
(cd "${TMPDIR}" && zip -qr "${ZIP}" "${SHORTNAME}")

echo "-- ZIP contents (first 25 entries) --"
unzip -l "${ZIP}" | sed -n '1,25p'

echo
echo "-- Forbidden-path scan --"
forbidden="$(unzip -l "${ZIP}" | grep -E '\.git/|node_modules/|\.DS_Store|\.idea/|\.vscode/|\.github/|(^|/)bin/' || true)"
if [[ -n "${forbidden}" ]]; then
    echo "ERROR: ZIP contains paths that should be excluded:" >&2
    echo "${forbidden}" >&2
    exit 4
fi
echo "(clean)"

echo
echo "-- Top-level structure check --"
top="$(unzip -l "${ZIP}" | awk 'NR>3 {print $4}' | grep -v '^$' | cut -d/ -f1 | sort -u)"
echo "${top}"
if [[ "$(echo "${top}" | wc -l | tr -d ' ')" != "1" || "${top}" != "${SHORTNAME}" ]]; then
    echo "ERROR: top-level dir must be exactly '${SHORTNAME}/'." >&2
    exit 5
fi

if ! unzip -l "${ZIP}" "${SHORTNAME}/version.php" >/dev/null; then
    echo "ERROR: ${SHORTNAME}/version.php is missing from ZIP." >&2
    exit 6
fi

echo
echo "Release ZIP ready: ${ZIP}"
echo "Size: $(du -h "${ZIP}" | cut -f1)"
