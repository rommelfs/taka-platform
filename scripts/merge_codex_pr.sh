#!/usr/bin/env bash

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

PR="${1:-}"

if [ -z "$PR" ]; then
    echo "Usage:"
    echo "    ./scripts/merge_codex_pr.sh <PR-number>"
    exit 1
fi

REPO="rommelfs/taka-platform"

echo
echo "Fetching PR information..."

BRANCH=$(gh pr view "$PR" \
    --repo "$REPO" \
    --json headRefName \
    --jq .headRefName)

echo
echo "PR Branch:"
echo "    $BRANCH"

echo
echo "Updating main..."

git fetch origin

git switch main

git pull --ff-only

echo
echo "Switching to PR branch..."

if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    git switch "$BRANCH"
    git reset --hard "origin/$BRANCH"
else
    git switch -c "$BRANCH" "origin/$BRANCH"
fi

echo
echo "Merging origin/main..."

set +e
git merge origin/main
STATUS=$?
set -e

if [ "$STATUS" != "0" ]; then

    echo
    echo "Merge conflicts detected."

    CONFLICTS=$(git diff --name-only --diff-filter=U)

    echo "$CONFLICTS"

    echo
    echo "Keeping Codex version..."

    while read -r FILE; do
        [ -z "$FILE" ] && continue
        git checkout --ours -- "$FILE"
        git add "$FILE"
    done <<< "$CONFLICTS"

    git commit -m "Resolve merge conflicts with main"
fi

echo
echo "Running lint..."

./scripts/lint.sh

echo
echo "Pushing..."

git push

echo
echo "Done."
echo
echo "Open:"
echo "https://github.com/$REPO/pull/$PR"
