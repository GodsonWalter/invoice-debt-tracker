# IDT production operations package

This directory contains deployment templates for a Linux production host running
Apache, PHP-FPM, Supervisor, MySQL or MariaDB, and private application storage.
The local development environment is WAMP/Windows; none of the external commands
below have been run against production infrastructure.

## Launch status

Application tests and build checks have passed, but launch approval remains blocked
until a production-like staging environment verifies HTTPS, wildcard DNS, SMTP,
real queue processing, scheduler effects, encrypted off-server backups, and a full
database plus private-file restore.

## Required topology

- Point the web server at the `public/` directory only.
- Use a wildcard DNS record for workspace hosts, for example `*.example.com`.
- Use a certificate covering both `example.com` and `*.example.com`.
- Keep report exports, invoice files, and workspace-private uploads on a private disk.
- Run queue workers separately from Apache and run the scheduler once per minute.
- Use a database/cache-backed session and cache store when multiple application nodes exist.

Copy `.env.example` to the release's shared `.env`, set real values through the
secret manager, and never commit that file.

## Apache, DNS, and TLS

1. Adapt and enable [`apache/idt-vhost.conf.example`](apache/idt-vhost.conf.example).
2. Enable `mod_rewrite`, `mod_ssl`, `mod_headers`, and `mod_expires` as applicable.
3. Confirm Apache's PHP handler matches the deployed PHP 8.3 runtime.
4. Create DNS records for the root domain and wildcard workspace domain.
5. Obtain a certificate using a DNS-01 challenge or the hosting provider's wildcard certificate.

Use placeholders until the real domain is approved:

```bash
dig example.com
dig testworkspace.example.com
curl -I https://example.com/up
curl -I https://testworkspace.example.com/up
openssl s_client -connect example.com:443 -servername testworkspace.example.com
```

These commands verify DNS and TLS only when run against the real target domain.

## Database deployment

Use a dedicated runtime database user, not `root`, with only the permissions needed
by the application and migration operator. Use `utf8mb4`, strict SQL mode, InnoDB,
and a supported MySQL/MariaDB release. Confirm the target engine separately from the
SQLite test suite.

For an additive release, deploy in this order:

```bash
php artisan down --render="errors::503" --retry=60
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Take and verify a backup before migrations. The payment idempotency migration adds
a nullable key for existing rows and a workspace-scoped unique index; test it against
the production MySQL/MariaDB version before rollout. Do not use `migrate:fresh` on a
database containing valuable data.

## Queue workers

Install [`supervisor/idt-worker.conf.example`](supervisor/idt-worker.conf.example)
with the actual release path and service user. The application dispatches its jobs
to the default queue. The chosen worker settings are:

- two worker processes;
- database queue, default queue name;
- three attempts with ten-second backoff;
- 120-second job timeout;
- 256 MB worker memory limit;
- 180-second database `retry_after` so a live job is not reclaimed early.

After installation:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status idt-worker:*
php artisan queue:failed
```

Use `php artisan queue:retry <id>` only after inspecting the failure. Do not blindly
retry all production jobs. During a controlled staging test, use:

```bash
php artisan queue:work database --tries=3 --timeout=120 --stop-when-empty
php artisan queue:failed
```

Do not run this against an unknown production queue from a development workstation.

## Scheduler

Install [`cron/idt-scheduler.example`](cron/idt-scheduler.example). The registered
tasks are:

- `reminders:process` daily;
- `workspaces:lifecycle` daily at 01:30;
- `reports:cleanup-exports` daily at 02:00.

All three use `withoutOverlapping()` and `onOneServer()`. The latter requires a
shared cache store when more than one application node runs the scheduler. Verify
actual effects in staging, not only registration:

```bash
php artisan schedule:list
php artisan schedule:run
```

## Private storage and exports

The local disk maps to `storage/app/private` and is not served directly. Report
downloads pass through an authorized controller and are workspace scoped. For a
multi-node deployment, use private S3-compatible storage or shared encrypted storage;
local disk on one node is not sufficient.

Export records and files older than `REPORT_EXPORT_RETENTION_DAYS` (default seven
days) are removed by the scheduled `reports:cleanup-exports` command. Pending
exports are retained. Verify this cleanup against a staging storage bucket before
enabling lifecycle policies.

## Backup architecture and restore drill

No backup provider or backup package is configured in this repository. Before launch,
select an approved encrypted off-server destination, such as a managed database
backup service plus versioned S3-compatible private storage, or an approved `restic`
repository. The backup must cover:

- the application database;
- `storage/app/private` and any private object storage;
- workspace logos, invoice files, and report exports whose retention requires them;
- non-secret deployment metadata needed to reconstruct the environment.

Recommended starting retention is daily for 14 days, weekly for 8 weeks, and monthly
for 12 months; the owner must approve the final policy. The owner must also approve
the Recovery Point Objective and Recovery Time Objective.

A safe staging backup procedure is:

```bash
mysqldump --single-transaction --routines --events --hex-blob \
  --defaults-extra-file=/secure/mysql-backup.cnf idt > /secure/idt.sql
tar --create --gzip --file=/secure/idt-private-storage.tgz -C /var/www/idt/current storage/app/private
sha256sum /secure/idt.sql /secure/idt-private-storage.tgz > /secure/idt.sha256
```

Encrypt the archive using the approved key-management process and upload it to the
off-server destination with restricted write/delete permissions. Do not place a
database password in a shell command or in this repository.

Restore only into a disposable environment:

```bash
sha256sum --check /secure/idt.sha256
mysql --defaults-extra-file=/secure/mysql-restore.cnf idt_restore < /secure/idt.sql
tar --extract --gzip --file=/secure/idt-private-storage.tgz -C /var/www/idt-restore
php artisan optimize:clear
php artisan config:cache
```

Compare record counts, selected invoice/payment balances, private-file hashes,
workspace switching, reports, public invoice lifecycle behavior, and cross-workspace
access with the source staging environment. Record duration, backup age, data-loss
window, and failed steps. A backup is not considered verified until this drill passes.

## Health, logs, and monitoring

- `/up` is Laravel's minimal liveness endpoint.
- `/ready` checks database readiness and returns only `{"status":"ready"}` or a
  generic 503 response; it does not expose diagnostics.
- Configure daily Laravel log rotation and worker rotation using
  [`logrotate/idt.conf.example`](logrotate/idt.conf.example).
- Monitor uptime, `/ready`, HTTP 5xx, failed jobs, queue age, worker processes,
  scheduler age, mail/reminder/export failures, disk usage, backup age, database
  health, response time, and certificate expiry.

No monitoring vendor, SMTP provider, DNS, TLS certificate, or backup destination is
claimed to be configured by this repository. Connect these to the approved incident
notification channel before launch.

## SMTP staging test

Use a provider sandbox or mail-capture service and a non-customer recipient. Verify
email verification, password reset, invoice mail, reminder mail, lifecycle mail, and
export completion notifications. Check HTTPS links, workspace hostnames, sender
identity, TLS, attachments, retries, and failure logging. Configure SPF, DKIM, and
DMARC for the production sender domain; inbox placement is provider-dependent.

## Atomic deployment and rollback

Prefer immutable release directories with a shared `.env` and private storage:

1. Upload a new release and build assets off to the side.
2. Run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
3. Run `npm ci && npm run build` in the build environment.
4. Put the application in maintenance mode.
5. Run additive migrations with `php artisan migrate --force`.
6. Rebuild config, route, and view caches.
7. Point `current` to the new release atomically.
8. Run `/up` and `/ready`, then restart workers with `php artisan queue:restart`.
9. Bring the application up and run the staging/launch smoke checklist.

Rollback criteria include failed readiness, migration failure, elevated 500s, payment
reconciliation failure, queue failure, or any cross-workspace access. Keep maintenance
mode enabled, stop or drain workers, point `current` to the previous known-good release,
rebuild caches, restart workers, and verify health. Additive migrations may require the
previous code to tolerate the newer schema; destructive migrations require a tested
database restore rather than a guessed rollback. Restore private files with the same
backup point if the application data was restored.

## Staging launch checklist

Use `APP_ENV=production` and `APP_DEBUG=false` in staging. Verify authentication,
workspace creation/switching, inactive memberships, clients, invoices, invoice PDFs,
public invoice links, partial/final/overpayment payment behavior, reminders and
idempotency, dashboard periods, every report/export format, private downloads,
workspace deletion/recovery/cleanup, audit records, and cross-workspace denial.

Build a known financial fixture and reconcile invoice totals, paid amounts, balances,
revenue, debt, aging, dashboard, report, CSV, XLSX, and PDF values before launch.

## Operations cadence

Daily: availability, failed jobs, workers, scheduler, mail/reminder/export failures,
backup age, and disk usage.

Weekly: restore-point age, dependency advisories, authorization anomalies, queue/log
growth, and certificate status.

Monthly: restore drill, access review, retention cleanup, database growth, performance,
and dependency update review.

Incidents requiring escalation include queue/SMTP/database outage, disk full, failed
deployment, accidental deletion, suspected tenant leakage, backup failure, and
compromised credentials. Preserve logs and do not retry unknown jobs during an incident.
