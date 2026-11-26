#!/usr/bin/env bash
#
# Puts the catalogue's committed images in the public bucket on AWS. Run it
# from a checkout, with AWS credentials that can write to the bucket:
#
#   AWS_PUBLIC_BUCKET=regray-public bash scripts/upload-catalog-images.sh --dry-run
#   AWS_PUBLIC_BUCKET=regray-public bash scripts/upload-catalog-images.sh
#   bash scripts/upload-catalog-images.sh --bucket=regray-public
#
# The images the seeders name (brand logos, stones, showcases, furniture, the
# Inquire page's table shapes and bases) are committed under
# storage/app/public/<folder>/ and never baked into the Docker images, so a
# production database seeded with them needs the same files in the bucket,
# at the same paths.
#
# - The folders are the ones storage/app/public/.gitignore commits (its
#   `!folder/` lines), so a folder committed there is uploaded too.
# - Only files git tracks go: an image uploaded on a local Products page sits
#   in the same folders but is not content.
# - A file already in the bucket is left alone, never overwritten, and
#   nothing is ever deleted there: uploads made on production live beside
#   them. The names are random, so the same name is the same file.
# - Uploaded with a year's cache, since a name never changes its content.
#
# Exits 1 if any upload failed.

set -uo pipefail
cd "$(dirname "$0")/.."

bucket="${AWS_PUBLIC_BUCKET:-}"
dry_run=0

for argument in "$@"; do
    case "$argument" in
        --bucket=*) bucket="${argument#--bucket=}" ;;
        --dry-run) dry_run=1 ;;
        *) echo "Unknown option: $argument" >&2; exit 2 ;;
    esac
done

if [[ -z "$bucket" ]]; then
    echo "Name the bucket: AWS_PUBLIC_BUCKET=... or --bucket=..." >&2
    exit 2
fi

if ! command -v aws >/dev/null 2>&1; then
    echo "The AWS CLI (aws) is needed: https://aws.amazon.com/cli/" >&2
    exit 2
fi

folders=$(sed -n 's#^!\([A-Za-z0-9_-]*\)/$#\1#p' storage/app/public/.gitignore)
uploaded=0
kept=0
failed=0

for folder in $folders; do
    # What the bucket already holds under the folder; none yet is no error.
    existing=$(aws s3 ls "s3://$bucket/$folder/" --recursive 2>/dev/null | awk '{ $1 = $2 = $3 = ""; sub(/^ +/, ""); print }' || true)

    while IFS= read -r file; do
        [[ -z "$file" || "$(basename "$file")" == ".gitignore" ]] && continue
        key="${file#storage/app/public/}"

        if grep -qxF "$key" <<<"$existing"; then
            kept=$((kept + 1))
            continue
        fi

        if [[ $dry_run -eq 1 ]]; then
            echo "  would upload  $key"
            uploaded=$((uploaded + 1))
        elif aws s3 cp "$file" "s3://$bucket/$key" --only-show-errors --cache-control "public, max-age=31536000, immutable"; then
            echo "  uploaded      $key"
            uploaded=$((uploaded + 1))
        else
            echo "  FAILED        $key" >&2
            failed=$((failed + 1))
        fi
    done < <(git ls-files "storage/app/public/$folder")
done

echo
echo "Folders: $(echo $folders)"
echo "$([[ $dry_run -eq 1 ]] && echo 'To upload' || echo 'Uploaded'): $uploaded, already there: $kept, failed: $failed"

[[ $failed -eq 0 ]]
