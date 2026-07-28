#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR/plugins/system/jblockbadwords"
MANIFEST="$PLUGIN_DIR/jblockbadwords.xml"

if [[ ! -f "$MANIFEST" ]]; then
    echo "Error: manifest not found at $MANIFEST" >&2
    exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
    echo "Error: 'zip' command not found. Please install zip first." >&2
    exit 1
fi

VERSION="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$MANIFEST" | head -n 1)"
if [[ -z "$VERSION" ]]; then
    VERSION="dev"
fi

OUTPUT_ZIP="$SCRIPT_DIR/plg_system_jblockbadwords-$VERSION.zip"

echo "Building Joomla plugin package..."
echo "Source: $PLUGIN_DIR"
echo "Output: $OUTPUT_ZIP"

rm -f "$OUTPUT_ZIP"
(
    cd "$PLUGIN_DIR"
    zip -r "$OUTPUT_ZIP" . -x "*.DS_Store" "*/.DS_Store"
)

echo "Done: $OUTPUT_ZIP"
