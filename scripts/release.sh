#!/usr/bin/env bash
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

if ! command -v composer >/dev/null 2>&1; then
    echo "error: composer is required" >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "error: working tree is dirty — commit or stash first" >&2
    exit 1
fi

branch="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$branch" != "main" && "$branch" != "master" ]]; then
    read -rp "Current branch is '$branch' (not main). Continue? [y/N] " ans
    [[ "$ans" =~ ^[Yy]$ ]] || exit 1
fi

latest="$(git tag --list --sort=-version:refname | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+$' | head -n1 || true)"

if [[ -z "$latest" ]]; then
    current="0.0.0"
    prefix=""
    echo "No existing version tags found. Starting from 0.0.0."
else
    if [[ "$latest" == v* ]]; then
        prefix="v"
        current="${latest#v}"
    else
        prefix=""
        current="$latest"
    fi
    echo "Current version: $latest"
fi

IFS='.' read -r major minor patch <<<"$current"

next_patch="$major.$minor.$((patch + 1))"
next_minor="$major.$((minor + 1)).0"
next_major="$((major + 1)).0.0"

cat <<EOF

Select next version:
  1) patch  → ${prefix}${next_patch}
  2) minor  → ${prefix}${next_minor}
  3) major  → ${prefix}${next_major}
  4) custom
  q) quit

EOF

read -rp "Choice [1-4/q]: " choice

case "$choice" in
    1) new="$next_patch" ;;
    2) new="$next_minor" ;;
    3) new="$next_major" ;;
    4)
        read -rp "Enter version (without prefix, e.g. 1.2.3): " new
        [[ "$new" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Invalid semver"; exit 1; }
        ;;
    q|Q) exit 0 ;;
    *) echo "Invalid choice"; exit 1 ;;
esac

tag="${prefix}${new}"

if git rev-parse -q --verify "refs/tags/$tag" >/dev/null; then
    echo "error: tag $tag already exists" >&2
    exit 1
fi

echo
echo "About to release: $tag"
read -rp "Proceed? [y/N] " ans
[[ "$ans" =~ ^[Yy]$ ]] || { echo "Aborted"; exit 0; }

composer config version "$new" --no-interaction

git add composer.json
git commit -m "chore(release): $tag"
git tag -a "$tag" -m "$tag"

echo
echo "Created commit and tag $tag locally."
read -rp "Push to origin now? [y/N] " ans
if [[ "$ans" =~ ^[Yy]$ ]]; then
    git push origin "$branch"
    git push origin "$tag"
    echo "Pushed."
else
    echo "Not pushed. Run: git push origin $branch && git push origin $tag"
fi
