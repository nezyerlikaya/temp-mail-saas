# Queue Workers Checklist

## Required Review

- Confirm queue driver.
- Confirm queue names include inbound mail queues.
- Confirm workers are supervised on VPS deployments.
- Confirm failed job review process.

## Shared Hosting

Use scheduled queue processing only if the host supports it. Keep provider throughput conservative.

## VPS

Run persistent workers with restart guidance after deployment. Monitor `queue_metrics` and failed jobs.
