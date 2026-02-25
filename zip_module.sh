#!/usr/bin/env bash
set -euo pipefail

if [ $# -ne 1 ]; then
    echo "Usage: $0 <module_name>"
    echo "Example: $0 mzclandingnewsletter"
    exit 1
fi

MODULE="$1"

if [ ! -d "$MODULE" ]; then
    echo "Error: directory '$MODULE' not found in $(pwd)"
    exit 1
fi

OUTPUT="${MODULE}.zip"
rm -f "$OUTPUT"

zip -r "$OUTPUT" "$MODULE" \
    -x "${MODULE}/.git/*" \
    -x "${MODULE}/node_modules/*" \
    -x "${MODULE}/.DS_Store" \
    -x "*/.DS_Store"

echo "✅ Created $OUTPUT ($(du -h "$OUTPUT" | cut -f1))"
