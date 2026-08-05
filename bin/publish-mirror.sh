#!/usr/bin/env bash
#
# Publish the current release tree to the public mirror repository.
#
# Day-to-day development happens in this private repository; the public
# repository (the `mirror` remote) receives one squashed commit per release
# carrying the full source tree, with the dev-only workflow files stripped.
#
# Usage:
#   bin/publish-mirror.sh X.Y.Z [--dry-run]
#   bin/publish-mirror.sh --init "<commit message>" [--dry-run]
#
# Versioned mode tags both repositories with X.Y.Z and pushes the mirror
# commit and tag. --init publishes an unversioned sync (no tags) with a
# custom commit message. --dry-run stops before any push and prints the
# diffstat of the would-be mirror commit.

set -euo pipefail

usage() {
	echo "Usage: $0 X.Y.Z [--dry-run]" >&2
	echo "       $0 --init \"<commit message>\" [--dry-run]" >&2
	exit 1
}

MODE=""
VERSION=""
INIT_MESSAGE=""
DRY_RUN=0

while [ $# -gt 0 ]; do
	case "$1" in
		--init)
			[ $# -ge 2 ] || usage
			MODE="init"
			INIT_MESSAGE="$2"
			shift 2
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		-*)
			usage
			;;
		*)
			[ -z "$MODE" ] || usage
			MODE="version"
			VERSION="$1"
			shift
			;;
	esac
done

[ -n "$MODE" ] || usage

if [ "$MODE" = "version" ] && ! echo "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+(\.[0-9]+)+$'; then
	echo "Error: '$VERSION' does not look like a version number." >&2
	exit 1
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

# Guard: no uncommitted changes to tracked files. Untracked files are
# tolerated: the published tree comes from `git archive HEAD`, so nothing
# outside the commit can reach the mirror.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
	echo "Error: working tree has uncommitted changes. Commit or stash them first." >&2
	exit 1
fi

# Guard: must be on main.
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [ "$BRANCH" != "main" ]; then
	echo "Error: current branch is '$BRANCH', expected 'main'." >&2
	exit 1
fi

# Guard: main must be up to date with origin/main.
git fetch origin main
if [ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]; then
	echo "Error: main is not up to date with origin/main." >&2
	exit 1
fi

# Guard: the mirror remote must exist.
if ! MIRROR_URL="$(git remote get-url mirror 2>/dev/null)"; then
	echo "Error: no 'mirror' remote configured." >&2
	exit 1
fi

# Guard (versioned mode): the plugin header must already carry the version.
if [ "$MODE" = "version" ]; then
	HEADER_VERSION="$(grep -E '^ \* Version:' gtm-kit.php | sed -E 's/^ \* Version:[[:space:]]*//')"
	if [ "$HEADER_VERSION" != "$VERSION" ]; then
		echo "Error: gtm-kit.php Version is '$HEADER_VERSION', expected '$VERSION'." >&2
		exit 1
	fi
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

EXPORT_DIR="$TMP_DIR/export"
CLONE_DIR="$TMP_DIR/mirror"
mkdir -p "$EXPORT_DIR"

# Export the full tree at HEAD. The repo has no .gitattributes
# export-ignore entries today; if any are ever added, switch to
# `git checkout-index` so the mirror stays full-tree.
git archive HEAD | tar -x -C "$EXPORT_DIR"

# Strip dev workflows from the export; only the wp.org deploy workflow
# belongs on the mirror.
if [ -d "$EXPORT_DIR/.github/workflows" ]; then
	find "$EXPORT_DIR/.github/workflows" -type f ! -name 'deploy.yml' -delete
fi

echo "Cloning mirror main..."
git clone --quiet --depth 1 --branch main "$MIRROR_URL" "$CLONE_DIR"

# Replace the mirror tree with the export.
git -C "$CLONE_DIR" rm -rq .
cp -R "$EXPORT_DIR"/. "$CLONE_DIR"/
git -C "$CLONE_DIR" add -A

if git -C "$CLONE_DIR" diff --cached --quiet; then
	if [ "$MODE" = "version" ]; then
		echo "Mirror is already identical to the $VERSION tree; nothing to publish."
		exit 0
	fi
	echo "Error: mirror is already identical to the current tree; nothing to sync." >&2
	exit 1
fi

if [ "$MODE" = "version" ]; then
	COMMIT_MESSAGE="GTM Kit $VERSION"
else
	COMMIT_MESSAGE="$INIT_MESSAGE"
fi

git -C "$CLONE_DIR" commit --quiet -m "$COMMIT_MESSAGE"

if [ "$DRY_RUN" -eq 1 ]; then
	echo "Dry run: no tags created, nothing pushed. The mirror commit would be:"
	git -C "$CLONE_DIR" show --stat HEAD
	exit 0
fi

if [ "$MODE" = "version" ]; then
	# Tag the private repo at HEAD so release diffs against the previous
	# tag keep working in the development history.
	if git rev-parse -q --verify "refs/tags/$VERSION" >/dev/null; then
		echo "Tag $VERSION already exists in the private repo."
	else
		git tag -a "$VERSION" -m "GTM Kit $VERSION"
	fi
	git push origin "refs/tags/$VERSION"

	git -C "$CLONE_DIR" tag -a "$VERSION" -m "GTM Kit $VERSION"
	git -C "$CLONE_DIR" push --quiet origin main "refs/tags/$VERSION"
else
	git -C "$CLONE_DIR" push --quiet origin main
fi

echo "Published to mirror:"
git -C "$CLONE_DIR" show --stat HEAD
