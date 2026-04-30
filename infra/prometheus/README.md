# Prometheus / AlertManager Setup

This directory contains the scrape config + alert rules for monitoring the Team Resource Dashboard.

## Files

| File | Purpose |
|---|---|
| `prometheus.yml.example` | Scrape config template. Copy to `prometheus.yml` and fill `METRICS_TOKEN`. |
| `alerts.yml` | AlertManager rules (4 alerts: `LastAdminLockBurst`, `CannotChangeOwnRoleBurst`, `EmailTakenBurst`, `AdminMetricsScrapeDown`). |

## Backend prerequisite

The backend exposes `GET /api/metrics` only when env `METRICS_TOKEN` is set. Generate a 64-char token:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Set it on the backend:

```bash
# backend/.env (production)
METRICS_TOKEN=<your-generated-token>
```

Without the env var, `GET /api/metrics` returns 404 — the route's existence is never disclosed.

## Quick local run

```bash
# 1. Backend with metrics enabled
echo "METRICS_TOKEN=devtoken" >> backend/.env
docker compose up -d

# 2. Verify the endpoint responds
curl -H "Authorization: Bearer devtoken" http://localhost:8080/api/metrics

# 3. Start Prometheus pointing at this directory
cp infra/prometheus/prometheus.yml.example infra/prometheus/prometheus.yml
sed -i 's/REPLACE_WITH_METRICS_TOKEN/devtoken/' infra/prometheus/prometheus.yml
docker run --rm -p 9090:9090 \
  -v "$PWD/infra/prometheus:/etc/prometheus" \
  --add-host=host.docker.internal:host-gateway \
  prom/prometheus:latest

# 4. Open http://localhost:9090 → Status → Targets to verify scrape.
#    Then Alerts to see all rules listed (firing or not).
```

## Alerts

| Alert | Threshold | Severity | Why |
|---|---|---|---|
| `LastAdminLockBurst` | `>= 3` last-admin-lock denials in 5 min | `page` | Either UI bug or attack — needs immediate look |
| `CannotChangeOwnRoleBurst` | `>= 5` self-role-change denials in 15 min | `warn` | UI regression: the disable check on the user's own row stopped working |
| `EmailTakenBurst` | `>= 10` email-taken denials in 5 min | `warn` | Possible email enumeration probe via the admin create endpoint |
| `AdminMetricsScrapeDown` | `up == 0` for 5 min | `page` | Without this, all the above alerts are inert (false-negative risk) |

Tune thresholds as the user base grows. Starting points are calibrated for a small (< 10 admins) team.

## Counters reference

The alerts read from these counters exposed at `/api/metrics`:

```
admin_user_created_total                    # happy path  (audit_logs)
admin_user_role_changed_total               # happy path  (audit_logs)
admin_user_password_reset_total             # happy path  (audit_logs)
admin_user_email_taken_total                # denial path (Cache)
admin_user_last_admin_lock_total            # denial path (Cache)
admin_user_cannot_change_own_role_total     # denial path (Cache)
```

See `backend/app/Http/Controllers/MetricsController.php` for the source mapping.

## Datadog alternative

The same `/api/metrics` endpoint works with Datadog's [OpenMetrics integration](https://docs.datadoghq.com/integrations/openmetrics/). Configure:

```yaml
init_config:
instances:
  - openmetrics_endpoint: http://team-resource.internal/api/metrics
    headers:
      Authorization: Bearer <METRICS_TOKEN>
    namespace: team_resource_dashboard
    metrics:
      - admin_user_*
```

Datadog Monitors map roughly to the alerts here:
- `admin_user_last_admin_lock_total` → 5-min sum > 3 → page
- `admin_user_cannot_change_own_role_total` → 15-min sum > 5 → warn
- etc.
