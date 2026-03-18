#!/bin/bash
# Compile all .po files in locale/ to .mo files.
# Requires gettext:
#   macOS:  brew install gettext
#   Linux:  apt install gettext
#
# Usage: bash compile_locale.sh

set -e
cd "$(dirname "$0")"

if ! command -v msgfmt &> /dev/null; then
    echo ""
    echo "  ERROR: msgfmt not found. Install gettext first:"
    echo "    macOS:  brew install gettext"
    echo "    Linux:  apt install gettext"
    echo ""
    exit 1
fi

find locale -name "*.po" | while read po_file; do
    mo_file="${po_file%.po}.mo"
    echo "✓ $po_file → $mo_file"
    msgfmt "$po_file" -o "$mo_file"
done

echo ""
echo "Done. Commit both .po and .mo files, then restart the stack: bash limesurvey.sh"
