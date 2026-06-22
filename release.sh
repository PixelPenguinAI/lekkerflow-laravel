#!/usr/bin/env bash
#
# Release helper: bumps the semver tag and pushes the branch + tag to origin.
# Packagist picks up the new tag automatically (via its GitHub webhook).
#
# Usage:
#   ./release.sh            # bump patch  (v1.2.3 -> v1.2.4)
#   ./release.sh minor      # bump minor  (v1.2.3 -> v1.3.0)
#   ./release.sh major      # bump major  (v1.2.3 -> v2.0.0)
#
# With no existing tags the base is v0.0.0, so the first run with `major`
# produces v1.0.0, `minor` produces v0.1.0, and `patch` produces v0.0.1.

set -euo pipefail

BUMP="${1:-patch}"

case "$BUMP" in
    major | minor | patch) ;;
    *)
        echo "Usage: $0 [major|minor|patch]" >&2
        exit 1
        ;;
esac

BRANCH="$(git rev-parse --abbrev-ref HEAD)"

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Working tree is not clean. Commit or stash your changes first." >&2
    exit 1
fi

LATEST="$(git tag --list 'v*' --sort=-v:refname | head -n1)"
LATEST="${LATEST:-v0.0.0}"

IFS='.' read -r MAJOR MINOR PATCH <<<"${LATEST#v}"

case "$BUMP" in
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    patch)
        PATCH=$((PATCH + 1))
        ;;
esac

NEW="v${MAJOR}.${MINOR}.${PATCH}"

if git rev-parse "$NEW" >/dev/null 2>&1; then
    echo "Tag $NEW already exists." >&2
    exit 1
fi

echo "Releasing ${LATEST} -> ${NEW} (${BUMP} bump on ${BRANCH})"

git push origin "$BRANCH"
git tag -a "$NEW" -m "Release ${NEW}"
git push origin "$NEW"

echo "Released ${NEW}"
