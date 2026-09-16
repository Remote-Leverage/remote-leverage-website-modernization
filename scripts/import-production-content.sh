#!/usr/bin/env bash
# One-time production content import: SQL dump + uploads.zip via S3 and a
# one-off Fargate task (Aurora and EFS are reachable from tasks only).
#
# Usage (from the website repo root):
#   AWS_REGION=us-east-1 ./scripts/import-production-content.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REGION="${AWS_REGION:-us-east-1}"
CLUSTER="${ECS_CLUSTER:-wordpress-production}"
SERVICE="${ECS_SERVICE:-wordpress-production}"
BUCKET="${IMPORT_BUCKET:-remote-leverage-wordpress-backups}"
PREFIX="${IMPORT_PREFIX:-imports/wordpress-production}"
SQL="${SQL_DUMP:-$ROOT/remoteleveragev2-2026-09-15-534bed7.sql}"
ZIP="${UPLOADS_ZIP:-$ROOT/uploads.zip}"
SITE_URL="${SITE_URL:-https://production.remoteleverage.com}"
CONTAINER="${CONTAINER_NAME:-app}"

if [[ ! -f "$SQL" ]]; then
  echo "SQL dump not found: $SQL" >&2
  exit 1
fi
if [[ ! -f "$ZIP" ]]; then
  echo "uploads zip not found: $ZIP" >&2
  exit 1
fi

echo "Uploading import artifacts to s3://$BUCKET/$PREFIX/"
aws s3 cp "$SQL" "s3://$BUCKET/$PREFIX/dump.sql" --region "$REGION"
aws s3 cp "$ZIP" "s3://$BUCKET/$PREFIX/uploads.zip" --region "$REGION"

echo "Importing via Fargate (Aurora and EFS are task-only)"

# WP-CLI search-replace rewrites serialized Gutenberg/ACF data. Do not rewrite
# https://remoteleverage.com during the preview period — those are live-site links.
# uploads.zip has a top-level uploads/ directory; strip it so files land on EFS.
# EFS access point already forces uid/gid 33 (www-data); do not chown.
REMOTE=$(cat <<EOF
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y --no-install-recommends default-mysql-client awscli unzip >/dev/null
cd /tmp
aws s3 cp "s3://$BUCKET/$PREFIX/dump.sql" ./dump.sql --region "$REGION"
aws s3 cp "s3://$BUCKET/$PREFIX/uploads.zip" ./uploads.zip --region "$REGION"
cd /var/www/html
wp db import /tmp/dump.sql --allow-root
wp search-replace 'http://remoteleverage-v2.test' '$SITE_URL' --all-tables --precise --allow-root
wp search-replace 'https://remoteleverage-v2.test' '$SITE_URL' --all-tables --precise --allow-root
wp option update home '$SITE_URL' --allow-root
wp option update siteurl '$SITE_URL/wp' --allow-root
rm -rf /tmp/uploads-extract
mkdir -p /tmp/uploads-extract
unzip -qo /tmp/uploads.zip -d /tmp/uploads-extract
if [ -d /tmp/uploads-extract/uploads ]; then
  cp -R /tmp/uploads-extract/uploads/. /var/www/html/web/app/uploads/
else
  cp -R /tmp/uploads-extract/. /var/www/html/web/app/uploads/
fi
wp acorn rl:deploy --allow-root || true
wp cache flush --allow-root || true
rm -rf /tmp/dump.sql /tmp/uploads.zip /tmp/uploads-extract
aws s3 rm "s3://$BUCKET/$PREFIX/dump.sql" --region "$REGION"
aws s3 rm "s3://$BUCKET/$PREFIX/uploads.zip" --region "$REGION"
echo import-complete
EOF
)

NET="$(aws ecs describe-services \
  --region "$REGION" \
  --cluster "$CLUSTER" \
  --services "$SERVICE" \
  --query 'services[0].networkConfiguration.awsvpcConfiguration' \
  --output json)"
SUBNETS="$(python3 -c 'import json,sys; d=json.load(sys.stdin); print(",".join(d["subnets"]))' <<<"$NET")"
SGS="$(python3 -c 'import json,sys; d=json.load(sys.stdin); print(",".join(d["securityGroups"]))' <<<"$NET")"
PUBLIC_IP="$(python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("assignPublicIp","ENABLED"))' <<<"$NET")"
OVERRIDES="$(python3 -c 'import json,sys; print(json.dumps({"containerOverrides":[{"name":sys.argv[1],"command":["/bin/bash","-lc",sys.argv[2]}]}]))' "$CONTAINER" "$REMOTE")"

TASK_ARN="$(aws ecs run-task \
  --region "$REGION" \
  --cluster "$CLUSTER" \
  --task-definition "$CLUSTER" \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNETS],securityGroups=[$SGS],assignPublicIp=$PUBLIC_IP}" \
  --overrides "$OVERRIDES" \
  --query 'tasks[0].taskArn' \
  --output text)"

echo "Import task $TASK_ARN"
aws ecs wait tasks-stopped --region "$REGION" --cluster "$CLUSTER" --tasks "$TASK_ARN"
EXIT_CODE="$(aws ecs describe-tasks --region "$REGION" --cluster "$CLUSTER" --tasks "$TASK_ARN" --query 'tasks[0].containers[0].exitCode' --output text)"
if [[ "$EXIT_CODE" != "0" ]]; then
  echo "Import task exited $EXIT_CODE" >&2
  exit 1
fi

echo "Content import finished."
