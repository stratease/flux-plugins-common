#!/usr/bin/env bash
# Simulates distribution rsync (same excludes as build-plugin.sh) and fails if
# dev-only or audit files that must not ship to WordPress.org are present.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RSYNC_EXCLUDES_FILE="$SCRIPT_DIR/plugin-dist-rsync-excludes.txt"

find_plugin_file() {
    local search_dir=$1
    local file
    for file in "$search_dir"/*.php; do
        if [ -f "$file" ] && grep -q "Plugin Name:" "$file"; then
            echo "$file"
            return 0
        fi
    done
    return 1
}

find_plugin_root() {
    local dir="${1:-$PWD}"
    local file
    while [ "$dir" != "/" ]; do
        file=$(find_plugin_file "$dir") && {
            echo "$dir"
            return 0
        }
        dir="$(dirname "$dir")"
    done
    return 1
}

if [ ! -f "$RSYNC_EXCLUDES_FILE" ]; then
    echo "❌ Missing exclude file: $RSYNC_EXCLUDES_FILE"
    exit 1
fi

PLUGIN_DIR="${1:-}"
if [ -z "$PLUGIN_DIR" ]; then
    PLUGIN_DIR="$(find_plugin_root)" || {
        echo "❌ Could not find plugin root. Pass PLUGIN_DIR as first argument or run from a plugin directory."
        exit 1
    }
fi

if [ ! -d "$PLUGIN_DIR" ]; then
    echo "❌ Not a directory: $PLUGIN_DIR"
    exit 1
fi

TEMP_DIR="$(mktemp -d)"
cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

echo "🔍 Verifying distribution tree (rsync dry-run copy to temp)..."
echo "   Plugin: $PLUGIN_DIR"
rsync -a --exclude-from="$RSYNC_EXCLUDES_FILE" "$PLUGIN_DIR/" "$TEMP_DIR/"

ERRORS=0

# Fail on PHPUnit/audit artifacts, source maps, and the Strauss-copied common asset tree
# (runtime assets must ship only under src/assets/common/).
while IFS= read -r -d '' path; do
    rel="${path#$TEMP_DIR/}"
    echo "❌ Forbidden path in distribution tree: $rel"
    ERRORS=$((ERRORS + 1))
done < <(
    find "$TEMP_DIR" \( \
        -name 'phpunit.xml.dist' \
        -o -name 'audit-*.md' \
        -o -name '*.map' \
        -o -path '*/vendor-prefixed/stratease/flux-plugins-common/src/assets' \
        -o -path '*/vendor-prefixed/stratease/flux-plugins-common/src/assets/*' \
    \) -print0 2>/dev/null || true
)

if [ "$ERRORS" -gt 0 ]; then
    echo "❌ verify-plugin-distribution: $ERRORS forbidden path(s). Update plugin-dist-rsync-excludes.txt or remove files from the plugin tree."
    exit 1
fi

echo "✅ verify-plugin-distribution: no forbidden phpunit/audit/map/prefixed-common-asset paths under simulated trunk."
