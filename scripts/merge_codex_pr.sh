#!/usr/bin/env bash
set -euo pipefail

PR="${1:-}"

if [ -z "$PR" ]; then
  echo "Usage: $0 <pr-number>"
  exit 1
fi

REPO="rommelfs/taka-tour-wp-plugin"

echo "Fetching PR #$PR metadata..."
BRANCH="$(gh pr view "$PR" --repo "$REPO" --json headRefName --jq .headRefName)"

if [ -z "$BRANCH" ]; then
  echo "Could not determine PR branch."
  exit 1
fi

echo "PR branch: $BRANCH"

git fetch origin

echo "Switching to main..."
git switch main
git pull --ff-only

echo "Switching to PR branch..."
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
  git switch "$BRANCH"
  git reset --hard "origin/$BRANCH"
else
  git switch -c "$BRANCH" "origin/$BRANCH"
fi

echo "Merging origin/main..."
set +e
git merge origin/main
MERGE_STATUS=$?
set -e

if [ "$MERGE_STATUS" -ne 0 ]; then
  echo "Merge conflicts detected. Keeping PR/Codex version (--ours)."
  git checkout --ours .
  git add .
  git commit -m "Resolve merge conflicts with main"
fi

echo "Running PHP syntax check..."
find . -name "*.php" -print0 | xargs -0 -n1 php -l

echo "Checking for conflict markers..."
if grep -R "<<<<<<<\|=======\|>>>>>>>" -n . --exclude-dir=.git; then
  echo "Conflict markers found. Aborting."
  exit 1
fi

echo "Pushing branch..."
git push

echo "Done. Reload GitHub PR #$PR and merge if checks look good."
