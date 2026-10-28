# Helpdesk Ticketing System: Backend API

A Laravel REST API for a helpdesk. Customers report problems, support agents work a ticket queue, and administrators manage tickets and accounts. The API keeps every ticket, its owner, its assigned agent, its status and its full reply and status history. It sends queued emails and runs a daily reminder task. It does **not** diagnose or solve problems automatically.

The Next.js frontend lives in its own repository and talks to this API.

## Contents

1. [Tech stack](#tech-stack)
2. [Requirements](#requirements)
3. [Quick start](#quick-start)
4. [Environment variables](#environment-variables)
5. [Services and URLs](#services-and-urls)
6. [Daily commands](#daily-commands)
7. [Database](#database)
8. [Queue worker and emails](#queue-worker-and-emails)
9. [Scheduler and the daily reminder](#scheduler-and-the-daily-reminder)
10. [Tests](#tests)
11. [Code quality](#code-quality)
12. [Test accounts](#test-accounts)
13. [API overview](#api-overview)
14. [Business rules](#business-rules)
15. [Architecture](#architecture)
16. [Postman and documentation](#postman-and-documentation)
17. [Git workflow](#git-workflow)
18. [Troubleshooting](#troubleshooting)

## Tech stack

| Part | Technology |
|---|---|
| Framework | Laravel 13 on PHP (Laravel Sail image) |
| Database | MySQL 8.4 |
| Queue | Laravel queue (`database` driver by default, Redis is available) |
| Cache, sessions | Database |
| Mail (local) | Mailpit (catches every email, nothing leaves your computer) |
| Authentication | Laravel Sanctum (Bearer tokens) |
| Roles | `spatie/laravel-permission` v8 (`admin`, `agent`, `customer`) |
| Tests | PHPUnit through `sail artisan test` |
| Formatting | Laravel Pint |
| Containers | Docker Compose, through Laravel Sail |

## Requirements

- Docker Desktop (running)
- Git
- Windows users: WSL 2 with Ubuntu. Run **every command inside the Ubuntu terminal**, and keep the project in the Linux file system (for example `~/helpdesk/backend`), not under `/mnt/c`.
- Nothing else. PHP, Composer and MySQL run inside Docker.

## Quick start

```bash
# 1. Get the code
git clone https://github.com/<your-account>/backend.git
cd backend

# 2. Create your local environment file
cp .env.example .env

# 3. Install PHP packages (first time only, before Sail exists)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
  laravelsail/php84-composer:latest composer install --ignore-platform-reqs

# 4. Start the containers (the first run downloads images and can take a while)
./vendor/bin/sail up -d

# 5. Generate the application key
./vendor/bin/sail artisan key:generate

# 6. Create the tables and the demo data
./vendor/bin/sail artisan migrate --seed

# 7. Check it
curl http://localhost/api/health
```

Expected answer from the last command:

```json
{ "success": true, "data": { "status": "ok" }, "message": "API is running." }
```

Make the commands shorter with an alias (add the second line to `~/.bashrc` to keep it):

```bash
alias sail='./vendor/bin/sail'
echo "alias sail='./vendor/bin/sail'" >> ~/.bashrc
```

The rest of this file writes `sail ...`. In the project folder you can always use `./vendor/bin/sail ...` instead.

## Environment variables

`.env` is **never committed**. `.env.example` lists every variable with safe local defaults.

| Variable | Meaning | Local value |
|---|---|---|
| `APP_NAME` | Application name, used in email signatures | `Helpdesk` |
| `APP_ENV` | `local` here, `production` on a server | `local` |
| `APP_DEBUG` | Show detailed errors. **Always `false` on a real server** | `true` |
| `APP_URL` | Public address of the API | `http://localhost` |
| `DB_HOST` | MySQL container name | `mysql` |
| `DB_PORT` | MySQL port **inside** Docker | `3306` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database credentials | `laravel`, `sail`, `password` |
| `QUEUE_CONNECTION` | Where queued jobs are stored | `database` (or `redis`) |
| `SESSION_DRIVER`, `CACHE_STORE` | Session and cache storage | `database` |
| `REDIS_HOST` | Redis container name | `redis` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT` | Mail server inside Docker | `smtp`, `mailpit`, `1025` |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender of every email | `helpdesk@helpdesk.test`, `${APP_NAME}` |
| `HELPDESK_SUPPORT_EMAIL` | Mailbox notified when a ticket is created | `support@helpdesk.test` |
| `FRONTEND_URL` | Address of the Next.js app (links in emails) | `http://localhost:3000` |
| `HELPDESK_REMINDER_AFTER_HOURS` | Hours without an update before a ticket is stale | `24` |
| `HELPDESK_REMINDER_TIME` | Time of day the reminder runs (24 h) | `09:00` |
| `HELPDESK_REMINDER_TIMEZONE` | Timezone of that time | `Asia/Colombo` |
| `FORWARD_DB_PORT` | MySQL port published on **your computer** | `13306` |
| `FORWARD_REDIS_PORT` | Redis port published on your computer | `16379` |
| `FORWARD_MAILPIT_DASHBOARD_PORT` | Mailpit web page port | `18025` |
| `FORWARD_MAILPIT_PORT` | Mailpit SMTP port on your computer | `11025` |

Notes:

- The `FORWARD_*` values only affect tools on your computer (DBeaver, the browser). Laravel itself always uses the Docker names and the standard ports above.
- On Windows, some ports (for example 3306, 6379, 8025) can be reserved by the system and Docker refuses to start. High ports such as the ones above avoid that. If `sail up -d` names a blocked port, move it to a high number in `.env`, then run `sail down` and `sail up -d`.
- Never use `env()` in application code. Read values with `config('...')`.
- After changing `.env`, run `sail artisan config:clear` and **restart the queue worker**.

## Services and URLs

| Service | Address |
|---|---|
| API | `http://localhost/api` |
| Health check | `http://localhost/api/health` |
| Mailpit (read the emails) | `http://localhost:18025` (default `8025`) |
| MySQL from DBeaver or another client | host `127.0.0.1`, port `13306`, user `sail`, password `password`, database `laravel` |
| Redis from a client | host `127.0.0.1`, port `16379` |

## Daily commands

```bash
sail up -d               # start every container in the background
sail down                # stop and remove the containers (the data is kept)
sail stop                # stop without removing
sail start               # start stopped containers again
sail ps                  # which containers are running
sail logs -f             # follow the logs
sail shell               # open a shell inside the PHP container
sail artisan <command>   # run an Artisan command
sail artisan tinker      # PHP shell with your app loaded (type exit to leave)
sail artisan route:list --path=api     # list the API routes
sail artisan optimize:clear            # clear config, route and view caches
sail composer <command>  # run Composer inside the container
sail composer dump-autoload            # rebuild the class map after adding a file
```

Create files with the generators so names and namespaces are correct:

```bash
sail artisan make:model Name -m
sail artisan make:migration add_x_column_to_y_table --table=y
sail artisan make:controller NameController
sail artisan make:request Folder/NameRequest
sail artisan make:resource NameResource
sail artisan make:policy NamePolicy --model=Name
sail artisan make:mail NameMail --markdown=mail.folder.name
sail artisan make:class Folder/ClassName     # handlers, services, support classes
sail artisan make:test NameTest              # feature test (add --unit for a unit test)
```

## Database

```bash
sail artisan migrate                 # run new migrations
sail artisan migrate:status          # which migrations have run
sail artisan migrate:rollback        # undo the last batch
sail artisan migrate:fresh --seed    # drop EVERYTHING, rebuild, add demo data (development only)
sail artisan db:seed                 # run the seeders on the current data
sail artisan db:show                 # database overview
sail artisan db:table tickets        # one table: columns, indexes, foreign keys
```

Rules for migrations:

- Never edit a migration that is already merged into `main`. Add a **new** migration.
- Every migration needs a working `down()`.
- Check the result with `sail artisan db:table <name>`.

### Tables

| Table | Purpose |
|---|---|
| `users` | Accounts (`is_active` switches an account off without deleting it) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Spatie tables. A user's role is a row in `model_has_roles` (there is **no** `role` column in `users`) |
| `tickets` | The tickets (`status`, `priority`, `customer_id`, `last_activity_at`, `last_reminded_at`) |
| `ticket_replies` | The conversation |
| `ticket_status_histories` | Every status change, with who made it |
| `ticket_user` | **Who holds a ticket and when.** A row with `unassigned_at = NULL` is the current agent, and closed rows are the history |
| `personal_access_tokens` | Sanctum login tokens |
| `jobs`, `failed_jobs` | The queue |
| `cache`, `sessions` | Laravel defaults |

### Demo data

`sail artisan migrate:fresh --seed` creates 1 admin, 3 agents, 5 customers and 30 tickets (10 open and unassigned, 8 in progress, 5 waiting for customer, 7 resolved), with replies, status history and some reassigned tickets.

## Queue worker and emails

Emails are **queued**. Nothing is sent until a worker runs:

```bash
sail artisan queue:work --tries=3        # keep this running while you test
```

```bash
sail artisan queue:failed                # jobs that failed 3 times
sail artisan queue:retry all             # try the failed jobs again
sail artisan queue:flush                 # delete the failed jobs
sail artisan queue:restart               # ask running workers to restart (after a deploy)
```

A worker keeps your code and `.env` **in memory**. After changing a Mailable, a handler, a service or `.env`, stop the worker (Ctrl+C) and start it again.

Open Mailpit (`http://localhost:18025`) to read the emails.

| Event | Email | Recipient |
|---|---|---|
| Registration | Welcome | The new user |
| Ticket created | Acknowledgement, and a support notification | The customer, and `HELPDESK_SUPPORT_EMAIL` |
| Ticket assigned by an admin | "Ticket #n was assigned to you" | The assigned agent |
| Customer replies | "<customer> replied on ticket #n" | The assigned agent (nobody if the ticket is unassigned) |
| Agent or admin replies | "New reply on your ticket #n" | The customer |
| Ticket resolved | "Your ticket #n was resolved" | The customer |
| Daily reminder | "Reminder: ticket #n needs your attention" / "We are waiting for your reply" | The agent, or the customer |

Rules: an agent **claiming** a ticket sends no email (it is their own action), and nobody is ever emailed about their own action. Every email is queued **after** the database transaction commits, so a rolled-back action never sends mail.

## Scheduler and the daily reminder

Once per day (default 09:00, `Asia/Colombo`) a task queues a reminder for every ticket with **no update for 24 hours**:

| Ticket status | Reminder goes to |
|---|---|
| In Progress | The assigned agent |
| Waiting for Customer | The customer |
| Open, Resolved | Nobody |

A ticket is reminded **at most once per calendar day**, and a reminder does not count as activity. Deactivated users are not emailed, and the ticket is retried once they are active.

```bash
sail artisan tickets:send-reminders      # run it by hand
sail artisan schedule:list               # show scheduled tasks and when they run
sail artisan schedule:list --timezone=Asia/Colombo
sail artisan schedule:work               # run the scheduler in the foreground (development)
```

On a real server a single cron line replaces `schedule:work`:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

For a full local run keep three terminals open: the containers, `queue:work`, and `schedule:work`.

## Tests

The tests use a separate MySQL database named `testing`, pinned in `phpunit.xml`, so **your development data is never touched**.

```bash
sail artisan test                                  # everything
sail artisan test --testsuite=Unit                 # only unit tests
sail artisan test --testsuite=Feature              # only feature tests
sail artisan test --filter=TicketStatusTest        # one class
sail artisan test --filter=test_login_is_throttled # one test
sail artisan test --stop-on-failure                # stop at the first red test
```

| Test file | Covers |
|---|---|
| `AuthTest` | Login, logout, `me`, register (a requested role is ignored), deactivated users, throttling |
| `UserManagementTest` | Admin-only user API, one role per account, update only name and email, self-protection |
| `TicketCreateTest` | Customer-only creation, validation, status forced to Open, history row |
| `TicketVisibilityTest` | Who sees which tickets, filters, pagination, identical 404 for forbidden and missing tickets |
| `TicketAssignmentTest` | Claim, assign, reassign, conflicts (409), one current agent |
| `TicketStatusTest`, `TicketWorkflowServiceTest` | Every valid and invalid status transition |
| `TicketReplyTest` | Reply rules, automatic return to In Progress after a customer reply |
| `NotificationTest` | Which emails are queued, and when none are |
| `ReminderCommandTest` | The daily reminder, with time travel (`$this->travel(25)->hours()`) |
| `DashboardTest` | Role-scoped counts and the activity feed |
| `ErrorResponseTest` | The standard error shape for 401, 403, 404, 405, 409, 422, 429 and 500 |
| `ArchitectureTest`, `RouteSecurityTest` | Company conventions and route protection |

## Code quality

```bash
sail bin pint            # format the whole project
sail bin pint --test     # only report what would change (use before a pull request)
sail bin pint -v         # format and list the changed files
```

Before every pull request run `sail bin pint` and `sail artisan test`. Both must be clean.

## Test accounts

Created by the seeder. The password for every account is **`password`**.

| Role | Email | Sees |
|---|---|---|
| Admin | `admin@helpdesk.test` | 30 tickets (10 unassigned) |
| Agent | `agent1@helpdesk.test`, `agent2@helpdesk.test`, `agent3@helpdesk.test` | The unassigned queue plus their own (16, 17 and 17 tickets) |
| Customer | `customer1@helpdesk.test` to `customer5@helpdesk.test` | Their own 6 tickets each |

Login is limited to 5 attempts per minute per address.

## API overview

All routes are under `/api`. Send `Accept: application/json`. Authenticated routes need `Authorization: Bearer <token>`.

### Responses

Success:

```json
{ "success": true, "data": {}, "message": "OK" }
```

Paginated lists add `"meta": { "current_page": 1, "per_page": 15, "total": 30, "last_page": 2 }`.

Errors always have the same shape, with the matching HTTP status:

```json
{ "success": false, "error": { "code": 422, "message": "Validation failed.", "details": { "email": ["..."] } } }
```

| Status | Meaning |
|---|---|
| 401 | Missing or invalid token |
| 403 | The role or a rule forbids it |
| 404 | Not found (also returned for a ticket you may not see, so ids cannot be probed) |
| 405 | Wrong HTTP method |
| 409 | The ticket's current state does not allow the action |
| 422 | Validation failed (field errors in `details`) |
| 429 | Too many requests |
| 500 | Unexpected error (details only when `APP_DEBUG=true`) |

### Endpoints

| Method and URL | Who | What it does |
|---|---|---|
| `GET /health` | Public | Health check |
| `POST /auth/register` | Public | Create a customer account and return a token |
| `POST /auth/login` | Public (5 per minute) | Return a token |
| `POST /auth/logout` | Signed in | Delete the current token |
| `GET /auth/me` | Signed in | The current user |
| `GET /dashboard` | Signed in | Status counts for your role, and the 10 latest activities |
| `GET /tickets` | Signed in | Tickets you may see. Filters: `search`, `status`, `priority`, `assignment` (`unassigned` or `mine`), `per_page`, `page` |
| `POST /tickets` | Customer | Create a ticket (`subject`, `description`, `priority`) |
| `GET /tickets/{id}` | Visible tickets | Detail with replies (oldest first), status history and current agent. Admins also get the assignment history |
| `POST /tickets/{id}/claim` | Agent | Claim an unassigned open ticket |
| `PATCH /tickets/{id}/assign` | Admin | Assign or reassign (`agent_id`) |
| `PATCH /tickets/{id}/status` | Holder agent or admin | `waiting_for_customer` or `resolved` |
| `POST /tickets/{id}/replies` | Customer (own), holder agent, admin | Add a reply |
| `GET /users` | Admin | List with `search`, `role`, `is_active`, `per_page`, `page` |
| `POST /users` | Admin | Create a user with one role |
| `GET /users/{id}` | Admin | One user |
| `PATCH /users/{id}` | Admin | Change name and/or email |
| `PATCH /users/{id}/role` | Admin | Set the role (not your own) |
| `PATCH /users/{id}/activate` | Admin | Activate an account |
| `PATCH /users/{id}/deactivate` | Admin | Deactivate an account and delete its tokens (not your own) |

## Business rules

### Roles

| Role | Can |
|---|---|
| Customer | Create tickets, see only their own, reply until resolved |
| Agent | See the unassigned queue and the tickets they hold, claim, and reply or change status on tickets they **hold** |
| Admin | See everything, assign and reassign, act on any ticket, manage users |

### Ticket status

```
Open ──(claim or assign)──► In Progress ──► Waiting for Customer ──► Resolved
                                 ▲                   │
                                 └─ customer replies ┘   (automatic)
```

| From | May move to |
|---|---|
| Open | In Progress |
| In Progress | Waiting for Customer, Resolved |
| Waiting for Customer | In Progress (automatic on a customer reply), Resolved |
| Resolved | nothing (closed: no replies, no assignment) |

Every change writes a row in `ticket_status_histories` inside the same transaction. A ticket has **at most one current agent**, and claiming uses a row lock, so two agents cannot take the same ticket.

## Architecture

Request flow, with one job per layer:

```
Route + middleware → Form Request → Controller → Handler → Service → Model
```

| Layer | Job |
|---|---|
| Route and middleware | Map the URL and method, require a token and a role |
| Form Request | Validate input and authorize the request before anything runs |
| Controller | Read the validated request, call **one** handler, return JSON |
| Handler | One use case: coordinate the steps, own the database **transaction**, queue emails |
| Service | Focused domain logic: status rules, who gets which email, data access |
| Model | Fields, casts, relationships and query scopes. No workflows |

Project layout:

```
app/
├── Console/Commands/         SendTicketReminders (tickets:send-reminders)
├── Enums/                    UserRole, TicketStatus, TicketPriority
├── Handlers/                 Auth, User, Ticket, Dashboard, Reminder, Concerns (shared traits)
├── Http/
│   ├── Controllers/          Thin controllers
│   ├── Requests/             Form Requests (validation + authorization)
│   └── Resources/            JSON shapes (UserResource, TicketResource, ...)
├── Mail/                     Mailables
├── Models/                   User, Ticket, TicketReply, TicketStatusHistory
├── Policies/                 TicketPolicy
├── Services/                 TicketWorkflowService, notification services, ...
└── Support/                  ApiResponse, ApiExceptionHandler, PendingMail
bootstrap/app.php             Middleware aliases and the exception handler
config/helpdesk.php           Support email, frontend URL, reminder settings
routes/api.php  routes/console.php
database/                     migrations, factories, seeders
docs/                         ERD, Postman collection, SQL practice
tests/                        Feature and Unit tests
```

Conventions (enforced by `ArchitectureTest`): controllers contain no database or mail code, `env()` is only read in `config/`, methods are in alphabetical order (public first, then private), every method has parameter and return types, lines stay under 120 characters, and routes never use closures.

## Postman and documentation

- Postman collection: `docs/postman/helpdesk-api.postman_collection.json`. Import it, then create an environment with `base_url = http://localhost` and an empty `token`. Run a login request first, because its script stores the token for the other requests.
- ERD: `docs/` (draw.io source and PNG).

## Git workflow

- Create every branch from an up-to-date `main`.
- Name branches `do-01`, `do-02`, `do-03` and so on.
- Make small commits with clear messages.
- Never commit `.env` or any secret.

```bash
git checkout main
git pull origin main
git checkout -b do-XX
# work, then:
sail bin pint
sail artisan test
git push -u origin do-XX
# open a pull request into main
```

## Troubleshooting

| Problem | Fix |
|---|---|
| `sail up -d` says a port is not available | Windows reserved it. Set a high port in `.env` (see the `FORWARD_*` variables), then `sail down` and `sail up -d` |
| `sail: command not found` | Use `./vendor/bin/sail`, or create the alias from the quick start |
| A page or API call returns HTML instead of JSON | Send `Accept: application/json`, and check the URL contains `/api` |
| Emails are not arriving in Mailpit | Start `sail artisan queue:work`, and check the Mailpit port (`FORWARD_MAILPIT_DASHBOARD_PORT`) |
| Emails still show old text after a code change | Restart the queue worker |
| `Class ... does not exist` | A typo in a file name or namespace, or a missing `use`. Run `sail composer dump-autoload` |
| `SQLSTATE ... table not found` | Run `sail artisan migrate` |
| A change in `.env` is ignored | `sail artisan config:clear`, then restart the worker |
| Tests wiped my development data | `phpunit.xml` must set `DB_DATABASE` to `testing` with `force="true"`. Rebuild with `sail artisan migrate:fresh --seed` |
| Login returns 429 | More than 5 attempts in a minute. Wait a minute |
| Docker is very slow on Windows | Keep the project inside the WSL file system, not under `/mnt/c` |

## Security notes

- `APP_DEBUG=false` on any real server, and never commit `.env`.
- Sanctum tokens do not expire by default. Set `'expiration'` in `config/sanctum.php` for a real deployment.
- Behind a reverse proxy, configure trusted proxies, otherwise the login throttle sees every user as one address.