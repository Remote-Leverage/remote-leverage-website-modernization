#!/usr/bin/env bash
# List EFS recovery points, or start an AWS Backup restore job.
#
# Practice on staging before production. A restore job does not swap the live
# mount by itself — see web/app/themes/remote-leverage/docs/disaster-recovery.md
# for what to do after it finishes.
#
# Usage (from the website repo root):
#   AWS_PROFILE=BootstrapAdministrator-742621604050 \
#     ./scripts/restore-efs-from-backup.sh list
#
#   AWS_PROFILE=BootstrapAdministrator-742621604050 \
#     ./scripts/restore-efs-from-backup.sh start --into-existing --confirm
#
#   AWS_PROFILE=BootstrapAdministrator-742621604050 \
#     ./scripts/restore-efs-from-backup.sh start --new-filesystem --confirm
#
# Environment:
#   AWS_REGION          default us-east-1
#   STACK               wordpress-staging (default) or wordpress-production
#   RECOVERY_POINT_ARN  optional; latest vault recovery point is used otherwise
set -euo pipefail

REGION="${AWS_REGION:-us-east-1}"
STACK="${STACK:-wordpress-staging}"
VAULT="${BACKUP_VAULT_NAME:-${STACK}-backup}"
ROLE_ARN="${BACKUP_ROLE_ARN:-}"
FS_ID="${EFS_FILE_SYSTEM_ID:-}"
ACTION="${1:-list}"
shift || true

INTO_EXISTING=0
NEW_FILESYSTEM=0
CONFIRM=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --into-existing) INTO_EXISTING=1 ;;
    --new-filesystem) NEW_FILESYSTEM=1 ;;
    --confirm) CONFIRM=1 ;;
    --recovery-point)
      RECOVERY_POINT_ARN="$2"
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
  shift
done

latest_recovery_point() {
  aws backup list-recovery-points-by-backup-vault \
    --region "$REGION" \
    --backup-vault-name "$VAULT" \
    --query 'reverse(sort_by(RecoveryPoints,&CreationDate))[0].RecoveryPointArn' \
    --output text
}

resolve_role_arn() {
  if [[ -n "$ROLE_ARN" ]]; then
    printf '%s\n' "$ROLE_ARN"
    return
  fi
  aws iam get-role \
    --role-name "${STACK}-backup" \
    --query 'Role.Arn' \
    --output text
}

resolve_fs_id() {
  if [[ -n "$FS_ID" ]]; then
    printf '%s\n' "$FS_ID"
    return
  fi
  aws efs describe-file-systems \
    --region "$REGION" \
    --query "FileSystems[?Tags[?Key=='Name' && Value=='${STACK}-uploads']].FileSystemId | [0]" \
    --output text
}

build_metadata() {
  local restore_meta="$1" new_fs="$2" fs="$3"
  RESTORE_META="$restore_meta" NEW_FS="$new_fs" FS_ID="$fs" STACK="$STACK" python3 -c '
import json, os, time
meta = json.loads(os.environ["RESTORE_META"])
fs = os.environ.get("FS_ID") or ""
if fs and fs != "None":
    meta["file-system-id"] = fs
elif "file-system-id" not in meta:
    raise SystemExit("Could not resolve an EFS file-system-id")
meta["Encrypted"] = meta.get("Encrypted") or "true"
meta["PerformanceMode"] = meta.get("PerformanceMode") or "generalPurpose"
if os.environ["NEW_FS"] == "1":
    meta["newFileSystem"] = "true"
    meta["CreationToken"] = "restore-%s-%s" % (os.environ["STACK"], int(time.time()))
else:
    meta["newFileSystem"] = "false"
    meta.pop("CreationToken", None)
print(json.dumps(meta))
'
}

list_points() {
  echo "Vault $VAULT ($REGION)"
  aws backup list-recovery-points-by-backup-vault \
    --region "$REGION" \
    --backup-vault-name "$VAULT" \
    --query 'reverse(sort_by(RecoveryPoints,&CreationDate))[].{created:CreationDate,status:Status,arn:RecoveryPointArn}' \
    --output table
}

start_restore() {
  if [[ "$INTO_EXISTING" -eq "$NEW_FILESYSTEM" ]]; then
    echo "Choose exactly one of --into-existing or --new-filesystem" >&2
    exit 1
  fi
  if [[ "$CONFIRM" -ne 1 ]]; then
    echo "Refusing to start a restore without --confirm" >&2
    exit 1
  fi

  local arn role fs restore_meta metadata metadata_file
  arn="${RECOVERY_POINT_ARN:-$(latest_recovery_point)}"
  if [[ -z "$arn" || "$arn" == "None" ]]; then
    echo "No recovery point in $VAULT. Wait for the first daily backup, or start an on-demand job." >&2
    exit 1
  fi
  role="$(resolve_role_arn)"
  fs="$(resolve_fs_id)"
  restore_meta="$(aws backup get-recovery-point-restore-metadata \
    --region "$REGION" \
    --backup-vault-name "$VAULT" \
    --recovery-point-arn "$arn" \
    --query RestoreMetadata \
    --output json)"

  if [[ "$NEW_FILESYSTEM" -eq 1 ]]; then
    metadata="$(build_metadata "$restore_meta" 1 "$fs")"
  else
    if [[ -z "$fs" || "$fs" == "None" ]]; then
      echo "Could not resolve the live EFS id. Set EFS_FILE_SYSTEM_ID." >&2
      exit 1
    fi
    metadata="$(build_metadata "$restore_meta" 0 "$fs")"
  fi

  echo "Starting EFS restore"
  echo "  vault:    $VAULT"
  echo "  point:    $arn"
  echo "  role:     $role"
  echo "  metadata: $metadata"

  metadata_file="$(mktemp)"
  printf '%s\n' "$metadata" >"$metadata_file"
  aws backup start-restore-job \
    --region "$REGION" \
    --recovery-point-arn "$arn" \
    --iam-role-arn "$role" \
    --resource-type EFS \
    --metadata "file://$metadata_file"
  rm -f "$metadata_file"
}

case "$ACTION" in
  list) list_points ;;
  start) start_restore ;;
  *)
    echo "Usage: $0 list|start [--into-existing|--new-filesystem] [--confirm] [--recovery-point ARN]" >&2
    exit 1
    ;;
esac
