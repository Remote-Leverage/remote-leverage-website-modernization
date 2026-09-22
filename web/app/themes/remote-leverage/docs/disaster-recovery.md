# Disaster recovery (same region)

If the AWS account and `us-east-1` are still up, this is how to rebuild the marketing
site after deletion, corruption, or a bad deploy. A full regional outage is out of
scope.

**Practice every restore on staging** (`wordpress-staging`, `staging.remoteleverage.com`)
before touching production.

## What is copied, and what is not

| Piece | Durable copy | Retention |
| :--- | :--- | :--- |
| Theme / app | git + ECR (`wordpress-staging` / `wordpress-production`, last 20 images) | git history + 20 images |
| Database | Aurora automated backups + PITR | 7 days staging, 14 days production |
| Media library (`web/app/uploads`) | AWS Backup vault `wordpress-*-backup`, daily 08:00 UTC | 7 days staging, 14 days production |
| Secrets | GitHub Environment secrets (source of truth) and Secrets Manager | 7-day recovery window after a secret delete |
| Redis | none — object cache only | — |
| Infra | Terraform in `terraform/envs/wordpress-*` | S3 state |

`remote-leverage-wordpress-backups` is the legacy plugin / one-time import bucket. It is not the EFS backup target. Restore jobs may stage files there; AWS Backup owns uploads.

Terraform: `terraform/modules/wordpress-bedrock/backup.tf`. Aurora backup window is `08:00-09:00` UTC; maintenance is `sun:09:30-sun:10:00` UTC so the two do not overlap.

## Recover a destroyed stack

1. Recreate empty infra:
   ```bash
   export AWS_PROFILE=BootstrapAdministrator-742621604050
   ./scripts/plan.sh envs/wordpress-staging   # or wordpress-production
   ./scripts/apply.sh envs/wordpress-staging
   ```
   This brings back VPC, ECS, a **new empty** Aurora cluster, a **new empty** EFS, CloudFront, and the backup vault (empty until the next daily job — existing recovery points stay in the vault if only the filesystem was lost).
2. Restore Aurora (below) onto or instead of that empty cluster.
3. Restore EFS (below) onto the new filesystem, or restore into a directory and copy into `/uploads`.
4. Run **Sync app secrets** (`sync-app-secrets.yml`) for that GitHub Environment, or deploy (deploys sync first).
5. Force an ECS deployment of the `staging` / `production` ECR tag.
6. Flush Redis, invalidate CloudFront `/*`, smoke-test the booking form and a known media URL.

If leftover Client VPN resources would be destroyed, apply backup and data-plane resources with `-target` instead of a full stack apply. See `terraform/README.md`.

## Restore Aurora

PITR creates a **new** cluster. Production has deletion protection; disable it before replacing the live cluster.

Latest restorable time:

```bash
aws rds restore-db-cluster-to-point-in-time \
  --region us-east-1 \
  --source-db-cluster-identifier wordpress-staging \
  --db-cluster-identifier wordpress-staging-restored \
  --use-latest-restorable-time \
  --restore-type full-copy
```

Then create an instance on the restored cluster (`db.serverless`, same subnet group and security group as the original), point the ECS task `DB_HOST` at the new endpoint (Terraform `aws_rds_cluster.this` / a targeted replace), and drop the broken cluster once the site answers.

To a specific time, pass `--restore-to-time 2026-09-22T12:00:00Z` instead of `--use-latest-restorable-time`.

The master password stays in Secrets Manager (`rds_secret_arn`). A brand-new restored cluster gets its own managed secret — copy the new ARN into the task definition the same way Terraform does today.

## Restore EFS (uploads)

List recovery points:

```bash
export AWS_PROFILE=BootstrapAdministrator-742621604050
STACK=wordpress-staging ./scripts/restore-efs-from-backup.sh list
```

Deleted files, live filesystem still exists — restore into it. AWS Backup writes a timestamped directory (`aws-backup-restore_*`) at the **filesystem root**. That directory is a sibling of `/uploads`, which is the access-point root the container sees, so the task will not list the restore directory at `/`. Prefer `--new-filesystem` when the live volume is gone or untrusted; use `--into-existing` only when you will copy from the restore directory onto `/uploads` (temporary mount without the access point, operator-only).

```bash
STACK=wordpress-staging ./scripts/restore-efs-from-backup.sh start --into-existing --confirm
```

Wait until `aws backup describe-restore-job --restore-job-id …` is `COMPLETED`.

When the live volume is gone or untrusted:

```bash
STACK=wordpress-staging ./scripts/restore-efs-from-backup.sh start --new-filesystem --confirm
```

The job creates a new EFS id. Put that id on `aws_efs_file_system.this` (import or replace), recreate the `/uploads` access point if the restore did not keep POSIX paths, update the ECS volume, and apply. Recreate mount targets in the same subnets/security group as `terraform/modules/wordpress-bedrock/efs.tf`.

Pass `--recovery-point ARN` to restore a point other than the latest.

## Secrets

GitHub Environment secrets win. If Secrets Manager was deleted, the 7-day recovery window lets you restore the secret, then run **Sync app secrets** so GitHub overwrites it with the current values.

Do **not** seed production from the local `env` file. That path is staging-only (`scripts/seed-staging-secrets.sh`).

## After the data is back

```bash
aws ecs update-service \
  --cluster wordpress-staging \
  --service wordpress-staging \
  --force-new-deployment

# Redis is object cache only — reboot the node or wait for TTL.
aws elasticache reboot-cache-cluster --cache-cluster-id wordpress-staging-001

aws cloudfront list-distributions \
  --query "DistributionList.Items[?contains(Aliases.Items, 'staging.remoteleverage.com')].Id" \
  --output text
aws cloudfront create-invalidation --distribution-id <ID> --paths '/*'
```

Production: cluster/service `wordpress-production`, hostname `remoteleverage.com`.

Check `/` (a known `uploads` image), `/book-consultation`, and wp-admin login. Staging is `noindex`; production must not be.

## What this does not cover

- `us-east-1` itself going away (no cross-region copies).
- Vault Lock / immutable backups (ransomware that also deletes recovery points).
- Redis contents, CloudWatch logs, ALB access logs.
- GoDaddy DNS — recreate from `terraform output godaddy_records` if the registrar records were lost.
