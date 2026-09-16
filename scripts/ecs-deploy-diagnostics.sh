#!/usr/bin/env bash
#
# Explain why an ECS deploy failed.
#
# `aws ecs wait services-stable` reports only that it stopped waiting:
#
#     aws: [ERROR]: Waiter ServicesStable failed: Max attempts exceeded
#
# Everything that would identify the cause — the task's stopped reason, the
# container's exit code, the lines it logged on the way down — stays in ECS and
# CloudWatch, so a failed deploy costs a trip to the console before anyone can
# even tell whether it was the application or the infrastructure. This prints
# all three into the job log at the moment of failure.
#
# Never fails the job. It runs when the build has already failed, and a
# diagnostic that errors on a missing permission would replace the real error
# with its own.

set -uo pipefail

CLUSTER="${ECS_CLUSTER:?ECS_CLUSTER is required}"
SERVICE="${ECS_SERVICE:?ECS_SERVICE is required}"
REGION="${AWS_REGION:-us-east-1}"
MAX_TASKS="${DIAG_MAX_TASKS:-3}"
LOG_LINES="${DIAG_LOG_LINES:-120}"

aws_q() { aws --region "$REGION" --no-cli-pager "$@" 2>&1; }

section() { printf '\n::group::%s\n' "$1"; }
endsection() { printf '::endgroup::\n'; }

section "ECS service events (most recent 20)"
aws_q ecs describe-services \
  --cluster "$CLUSTER" --services "$SERVICE" \
  --query 'services[0].events[0:20].[createdAt,message]' \
  --output text || echo "could not read service events"
endsection

section "Deployment rollout state"
aws_q ecs describe-services \
  --cluster "$CLUSTER" --services "$SERVICE" \
  --query 'services[0].deployments[].{id:id,status:status,taskDef:taskDefinition,desired:desiredCount,running:runningCount,pending:pendingCount,failed:failedTasks,rollout:rolloutState,reason:rolloutStateReason}' \
  --output table || echo "could not read deployments"
endsection

# The heart of it: why did the task stop?
#
# `--desired-status STOPPED` is what makes this work. A task that failed its
# entrypoint is gone by the time anyone looks, and describing running tasks
# shows nothing wrong precisely because the broken ones are no longer running.
STOPPED_TASKS=$(aws --region "$REGION" --no-cli-pager ecs list-tasks \
  --cluster "$CLUSTER" --service-name "$SERVICE" \
  --desired-status STOPPED --max-items "$MAX_TASKS" \
  --query 'taskArns' --output text 2>/dev/null)

if [ -z "${STOPPED_TASKS:-}" ] || [ "$STOPPED_TASKS" = "None" ]; then
  section "Stopped tasks"
  echo "No stopped tasks found."
  echo
  echo "No task stopped, yet the service never stabilised. That points away from the"
  echo "application and towards placement: the task may never have started at all."
  echo "Check capacity, subnet IP exhaustion, and whether the image tag exists in ECR."
  endsection
  exit 0
fi

for TASK_ARN in $STOPPED_TASKS; do
  TASK_ID="${TASK_ARN##*/}"

  section "Stopped task $TASK_ID"
  aws_q ecs describe-tasks \
    --cluster "$CLUSTER" --tasks "$TASK_ARN" \
    --query 'tasks[0].{stoppedReason:stoppedReason,stopCode:stopCode,startedAt:startedAt,stoppedAt:stoppedAt,taskDef:taskDefinitionArn,containers:containers[].{name:name,exitCode:exitCode,reason:reason,lastStatus:lastStatus,health:healthStatus}}' \
    --output json || echo "could not describe task"
  endsection

  # The container's own output. `exit 1` from the entrypoint shows up here as the
  # line printed just before it, which is usually the whole answer.
  TASK_DEF=$(aws --region "$REGION" --no-cli-pager ecs describe-tasks \
    --cluster "$CLUSTER" --tasks "$TASK_ARN" \
    --query 'tasks[0].taskDefinitionArn' --output text 2>/dev/null)

  [ -z "${TASK_DEF:-}" ] && continue

  CONTAINERS=$(aws --region "$REGION" --no-cli-pager ecs describe-task-definition \
    --task-definition "$TASK_DEF" \
    --query "taskDefinition.containerDefinitions[?logConfiguration.logDriver=='awslogs'].[name,logConfiguration.options.\"awslogs-group\",logConfiguration.options.\"awslogs-stream-prefix\"]" \
    --output text 2>/dev/null)

  [ -z "${CONTAINERS:-}" ] && continue

  while IFS=$'\t' read -r NAME GROUP PREFIX; do
    [ -z "${NAME:-}" ] && continue

    section "Logs: $NAME ($TASK_ID)"
    aws_q logs get-log-events \
      --log-group-name "$GROUP" \
      --log-stream-name "${PREFIX}/${NAME}/${TASK_ID}" \
      --limit "$LOG_LINES" \
      --query 'events[].message' \
      --output text || echo "could not read log stream ${PREFIX}/${NAME}/${TASK_ID} in ${GROUP}"
    endsection
  done <<< "$CONTAINERS"
done

exit 0
