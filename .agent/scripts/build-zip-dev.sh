#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────
# CustomPreOrderManager — DEV ZIP Build Script
# Erstellt ein Plugin-ZIP inklusive Test-Infrastruktur
# und Dokumentation für die NAS Dev-Instanz (192.168.2.222:8080).
#
# Nutzung:
#   .agent/scripts/build-zip-dev.sh
#
# Output:
#   dist/CustomPreOrderManager-<version>-dev.zip
# ──────────────────────────────────────────────────────────────
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
PLUGIN_NAME="CustomPreOrderManager"

# Version aus Git-Tag lesen (Fallback: "dev")
VERSION="dev"
if git -C "$PROJECT_ROOT" describe --tags --abbrev=0 2>/dev/null; then
    VERSION=$(git -C "$PROJECT_ROOT" describe --tags --abbrev=0 | sed 's/^v//')
fi

DIST_DIR="$PROJECT_ROOT/dist"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}-dev.zip"
TEMP_DIR=$(mktemp -d)

echo "╔══════════════════════════════════════════════╗"
echo "║  CustomPreOrderManager — DEV ZIP Builder     ║"
echo "╚══════════════════════════════════════════════╝"
echo ""
echo "  Version:  $VERSION-dev"
echo "  Output:   dist/$ZIP_NAME"
echo "  Inhalt:   Produktions-Code + Tests + Doku"
echo ""

# Dist-Ordner anlegen
mkdir -p "$DIST_DIR"

# Altes ZIP löschen falls vorhanden
[ -f "$DIST_DIR/$ZIP_NAME" ] && rm "$DIST_DIR/$ZIP_NAME"

# Plugin-Dateien in temporären Ordner kopieren
echo "→ Dateien kopieren..."
mkdir -p "$TEMP_DIR/$PLUGIN_NAME"

rsync -a \
    --exclude='.git' \
    --exclude='.git/**' \
    --exclude='.agent' \
    --exclude='.agent/**' \
    --exclude='.gemini' \
    --exclude='.gemini/**' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='.idea' \
    --exclude='.vscode' \
    --exclude='node_modules' \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='*.swp' \
    --exclude='*.swo' \
    --exclude='.gitignore' \
    --exclude='backup' \
    --exclude='dist' \
    --exclude='task.md' \
    --exclude='walkthrough.md' \
    "$PROJECT_ROOT/" "$TEMP_DIR/$PLUGIN_NAME/"

# Shopware erwartet zwingend das dist-Verzeichnis beim Plugin-Upload
echo "→ Storefront dist-Verzeichnis sicherstellen..."
mkdir -p "$TEMP_DIR/$PLUGIN_NAME/src/Resources/app/storefront/dist/storefront/js"
touch "$TEMP_DIR/$PLUGIN_NAME/src/Resources/app/storefront/dist/storefront/js/.gitkeep"

# ZIP erstellen
echo "→ ZIP erstellen..."
cd "$TEMP_DIR"
zip -r -q "$DIST_DIR/$ZIP_NAME" "$PLUGIN_NAME/"

# Aufräumen
rm -rf "$TEMP_DIR"

# Ergebnis
FILE_SIZE=$(du -h "$DIST_DIR/$ZIP_NAME" | cut -f1 | xargs)
echo ""
echo "✔ Fertig: dist/$ZIP_NAME ($FILE_SIZE)"
echo ""
echo "Deployment auf der NAS (192.168.2.222:8080):"
echo "  1. ZIP im Shopware Admin hochladen"
echo "  2. Oder via SSH: unzip nach custom/plugins/"
echo "  3. bin/console plugin:install --activate CustomPreOrderManager"
echo "  4. bin/console cache:clear && bin/console assets:install"
