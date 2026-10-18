#!/usr/bin/env bash
set -e

# Version bumper script for RealPanel
# Usage: ./scripts/bump-version.sh [patch|minor|major|<specific-version>]

TYPE="${1:-patch}"
VERSION_FILE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/version.txt"

if [ ! -f "$VERSION_FILE" ]; then
    echo "2.9.17" > "$VERSION_FILE"
fi

CURRENT_VERSION="$(tr -d '[:space:]' < "$VERSION_FILE")"
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"

MAJOR=${MAJOR:-2}
MINOR=${MINOR:-9}
PATCH=${PATCH:-17}

case "$TYPE" in
    patch)
        PATCH=$((PATCH + 1))
        NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"
        ;;
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"
        ;;
    *)
        NEW_VERSION="$TYPE"
        ;;
esac

echo "$NEW_VERSION" > "$VERSION_FILE"
echo "RealPanel version bumped: $CURRENT_VERSION -> $NEW_VERSION"
