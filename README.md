# refer-cheat-to-do

API-first foundation for a web-first information continuity, ToDo, research, and knowledge-refinement application.

## Current MVP scope

This repository currently contains **MVP 1** work in progress:

- CakePHP 5 application skeleton with PostgreSQL-first configuration
- JSON API conventions and a health-check endpoint
- session-based authentication (`/api/auth/*`)
- ToDo capture and inbox endpoints (`/api/todos`)
- tags and todo/tag association endpoints (`/api/tags`, `/api/todos/{id}/tags/{tagId}`)
- database migrations for `users`, `todos`, `tags`, and `todos_tags`
- PHPUnit, coding standards, and static analysis setup
- devcontainer/Docker-compatible development environment
- GitHub Actions CI

There is no public self-registration endpoint yet; the first account is created
directly in the database (see [Step 9](#9-create-the-first-user-account)).

## Technology stack

- PHP 8.3+
- CakePHP 5
- PostgreSQL
- REST/JSON API
- PHPUnit
- Composer
- Docker/devcontainer
- GitHub Actions

## Repository structure

```text
.
├── .devcontainer/
├── .github/
│   └── workflows/
├── bin/
├── config/
│   └── Migrations/
├── docs/
│   ├── adr/
│   └── slices/
├── plugins/
├── src/
├── templates/
├── tests/
├── webroot/
├── composer.json
├── phpcs.xml
├── phpstan.neon
├── phpunit.xml.dist
└── README.md
```

## Authoritative design documents

- [docs/application-design.md](docs/application-design.md)
- [docs/roadmap.md](docs/roadmap.md)
- [docs/mvp-feature-list.md](docs/mvp-feature-list.md)
- [docs/adr/](docs/adr/)

## Installing an instance on a new server

The steps below install a production instance on a single Linux host running
nginx + PHP-FPM, with PostgreSQL either on the same host or as a managed
service. Commands use Ubuntu 24.04 LTS package names; adapt them for other
distributions. Replace `todo.example.com`, paths, and credentials with your own
values.

### 0. Requirements

| Component | Version | Notes |
| --- | --- | --- |
| PHP (CLI + FPM) | 8.3 or newer | extensions `intl`, `mbstring`, `pdo_pgsql` are required by `composer.json` |
| Composer | 2.x | used to install PHP dependencies |
| PostgreSQL | 14 or newer (16 is used in CI and the devcontainer) | local or managed |
| Web server | nginx, or Apache with `mod_rewrite` | document root must be the `webroot/` directory |
| Git | any recent version | used to deploy and update the code |
| Outbound HTTPS | — | required so Composer can reach packagist.org |

### 1. Install system packages

```bash
sudo apt update
sudo apt install -y git unzip curl nginx postgresql postgresql-client \
  php8.3-cli php8.3-fpm php8.3-intl php8.3-mbstring php8.3-pgsql php8.3-xml php8.3-curl
```

Omit the `postgresql` package when you use a managed database. Replace `nginx`
with `apache2 libapache2-mod-fcgid` if you plan to use Apache2 (see
[Step 8, Option B](#option-b-apache2--php-fpm)). Install
Composer 2 following the instructions on <https://getcomposer.org/download/>,
then confirm the toolchain:

```bash
php -v
php -m | grep -E 'intl|mbstring|pdo_pgsql'
composer --version
```

### 2. Deploy the code

```bash
sudo mkdir -p /var/www/refer-cheat-to-do
sudo chown "$USER":www-data /var/www/refer-cheat-to-do
git clone https://github.com/cwethm/refer-cheat-to-do.git /var/www/refer-cheat-to-do
cd /var/www/refer-cheat-to-do
```

### 3. Create the PostgreSQL role and database

For a PostgreSQL instance on the same host:

```bash
sudo -u postgres createuser --pwprompt refer_cheat_to_do
sudo -u postgres createdb --owner=refer_cheat_to_do refer_cheat_to_do
```

Making the application role the database owner matters on PostgreSQL 15 and
newer, where non-owners cannot create objects in the `public` schema. If the
role does not own the database, grant schema rights explicitly:

```sql
GRANT ALL ON SCHEMA public TO refer_cheat_to_do;
```

For a managed database, create the database through the provider console and
note the host, port, database name, credentials, and whether TLS is mandatory
(see `DATABASE_URL` in the [environment variable reference](#environment-variable-reference)).

### 4. Configure environment variables

All runtime configuration is read from environment variables by `config/app.php`
and `config/app_local.php`. There are two supported ways to supply them:

- **Process environment (recommended for servers).** Set the variables in the
  PHP-FPM pool for web requests and export them in the shell/systemd unit used
  for console commands.
- **`.env` file in the repository root.** `config/bootstrap.php` loads
  `.env` only when `APP_NAME` is not already present in the environment *and*
  the `josegonzalez/dotenv` package is installed. That package is a **dev**
  dependency, so a `.env` file combined with a `--no-dev` install (and no
  `APP_NAME` exported) fails with a missing-class error. Use the process
  environment for `--no-dev` installs, or install dev dependencies if you
  prefer `.env`.

Generate a unique application salt:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Add the variables to the PHP-FPM pool, for example in
`/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
env[APP_NAME] = refer-cheat-to-do
env[APP_ENV] = production
env[DEBUG] = false
env[SECURITY_SALT] = paste-the-generated-salt-here
env[APP_FULL_BASE_URL] = https://todo.example.com
env[DB_HOST] = 127.0.0.1
env[DB_PORT] = 5432
env[DB_DATABASE] = refer_cheat_to_do
env[DB_USERNAME] = refer_cheat_to_do
env[DB_PASSWORD] = the-database-password
```

`APP_FULL_BASE_URL` is mandatory whenever `DEBUG=false`: `HostHeaderMiddleware`
returns a 500 error when it is missing and rejects requests whose `Host` header
does not match it.

For console commands (migrations, maintenance), keep the same values in a
root-owned file such as `/etc/refer-cheat-to-do.env` (`chmod 600`) and load it
before running `bin/cake`:

```bash
set -a; . /etc/refer-cheat-to-do.env; set +a
```

### 5. Install PHP dependencies

```bash
composer install --no-dev --no-interaction --optimize-autoloader
```

The `post-install-cmd` hook (`App\Console\Installer`) copies
`config/app_local.example.php` to `config/app_local.php` when that file is
missing and creates the writable `logs/` and `tmp/` directories.
`config/app_local.php` only reads environment variables, so it needs no manual
editing. Drop `--no-dev` if you also want to run the test suite or use a `.env`
file on this server.

### 6. Run database migrations

```bash
bin/cake migrations migrate -c default
bin/cake migrations status -c default
```

This creates the `users`, `todos`, `tags`, and `todos_tags` tables.

### 7. Set ownership and permissions

```bash
sudo chown -R "$USER":www-data /var/www/refer-cheat-to-do
sudo chmod -R g+w /var/www/refer-cheat-to-do/logs /var/www/refer-cheat-to-do/tmp
```

Only `logs/` and `tmp/` need to be writable by the PHP-FPM user; application
code does not. Authentication state is kept in PHP sessions, so PHP's
`session.save_path` must also be writable by the PHP-FPM user.

### 8. Configure the web server

The document root must be the `webroot/` directory. Use either the nginx or the
Apache2 instructions below — not both on the same host, because they would
compete for ports 80 and 443.

#### Option A: nginx + PHP-FPM

Create `/etc/nginx/sites-available/refer-cheat-to-do`:

```nginx
server {
    listen 80;
    server_name todo.example.com;

    root /var/www/refer-cheat-to-do/webroot;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Enable it and reload the services:

```bash
sudo ln -s /etc/nginx/sites-available/refer-cheat-to-do /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

With Apache, point the virtual host at either `webroot/` or the repository root
(the committed `.htaccess` files rewrite requests into `webroot/`), enable
`mod_rewrite` with `sudo a2enmod rewrite`, and set `AllowOverride All` for the
directory.

#### Option B: Apache2 + PHP-FPM

Install Apache2 and the FastCGI proxy module (skip `nginx` in
[Step 1](#1-install-system-packages) if you choose Apache):

```bash
sudo apt install -y apache2 libapache2-mod-fcgid
sudo a2enmod rewrite proxy_fcgi setenvif headers
sudo a2enconf php8.3-fpm
```

Create `/etc/apache2/sites-available/refer-cheat-to-do.conf`:

```apache
<VirtualHost *:80>
    ServerName todo.example.com

    DocumentRoot /var/www/refer-cheat-to-do/webroot

    <Directory /var/www/refer-cheat-to-do/webroot>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Deny dotfiles except ACME challenges.
    <DirectoryMatch "/\.(?!well-known)">
        Require all denied
    </DirectoryMatch>

    ErrorLog ${APACHE_LOG_DIR}/refer-cheat-to-do-error.log
    CustomLog ${APACHE_LOG_DIR}/refer-cheat-to-do-access.log combined
</VirtualHost>
```

`AllowOverride All` is required so the committed `webroot/.htaccess` rewrite
rules are applied; without it every route except `/` returns 404. Alternatively,
set `AllowOverride None` and copy the rewrite rules from `webroot/.htaccess`
into the `<Directory>` block.

You can also point `DocumentRoot` at the repository root
(`/var/www/refer-cheat-to-do`) instead; the top-level `.htaccess` rewrites
requests into `webroot/`. Serving `webroot/` directly is preferred because it
keeps `config/`, `src/`, and `vendor/` outside the document root.

Enable the site and reload the services:

```bash
sudo a2dissite 000-default
sudo a2ensite refer-cheat-to-do
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo systemctl restart php8.3-fpm
```

If you run PHP through `mod_php` rather than PHP-FPM, omit the `<FilesMatch>`
block and the `proxy_fcgi`/`php8.3-fpm` steps, and install
`libapache2-mod-php8.3` instead.

Terminate TLS in front of the application (for example
`sudo certbot --nginx -d todo.example.com`, `sudo certbot --apache -d
todo.example.com`, or at a load balancer) and make sure
`APP_FULL_BASE_URL` matches the public HTTPS URL.

### 9. Create the first user account

The API has no registration endpoint yet, so insert the first user directly.
Generate a hash with the same algorithm the `User` entity uses
(`password_hash()` with `PASSWORD_DEFAULT`):

```bash
php -r 'echo password_hash("replace-with-a-strong-password", PASSWORD_DEFAULT), PHP_EOL;'
```

```bash
psql -h 127.0.0.1 -U refer_cheat_to_do -d refer_cheat_to_do \
  -c "INSERT INTO users (email, password, created, modified) VALUES ('owner@example.com', 'paste-the-generated-hash-here', NOW(), NOW());"
```

Store the email in lowercase: the login endpoint trims and lowercases the
submitted email before looking the account up. Passwords must be at least eight
characters.

### 10. Verify the installation

```bash
curl -s https://todo.example.com/api/health
```

Expected response:

```json
{
  "data": {
    "status": "ok",
    "application": {
      "name": "refer-cheat-to-do",
      "environment": "production",
      "debug": false
    }
  }
}
```

Run an end-to-end smoke test of the session-based API:

```bash
curl -s -c /tmp/cookies.txt -H 'Content-Type: application/json' \
  -d '{"email":"owner@example.com","password":"replace-with-a-strong-password"}' \
  https://todo.example.com/api/auth/login

curl -s -b /tmp/cookies.txt https://todo.example.com/api/auth/me

curl -s -b /tmp/cookies.txt -H 'Content-Type: application/json' \
  -d '{"title":"First todo"}' https://todo.example.com/api/todos

rm -f /tmp/cookies.txt
```

### 11. Harden the instance

- keep `DEBUG=false` and `APP_ENV=production`
- use a unique `SECURITY_SALT` per environment and never commit it
- restrict environment files to `chmod 600` and keep credentials out of shell history
- serve the application over HTTPS only and set `session.cookie_secure=1`,
  `session.cookie_httponly=1`, and `session.cookie_samesite=Lax` in `php.ini`
- limit PostgreSQL network access to the application host, or require TLS for managed databases
- add log rotation for `logs/*.log` and schedule database backups
- apply updates as described below

## Updating an existing deployment

```bash
cd /var/www/refer-cheat-to-do
git pull --ff-only
composer install --no-dev --no-interaction --optimize-autoloader
bin/cake migrations migrate -c default
bin/cake cache clear_all
sudo systemctl reload php8.3-fpm
```

## Environment variable reference

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_NAME` | `refer-cheat-to-do` | application name, cache prefixes, health payload |
| `APP_ENV` | `production` | environment label reported by `/api/health` |
| `DEBUG` | `false` | enables debug output; must be `false` in production |
| `SECURITY_SALT` | placeholder value | secret used for hashing/signing; set a unique value |
| `APP_FULL_BASE_URL` | none | public base URL; required when `DEBUG=false` |
| `APP_ENCODING` / `APP_DEFAULT_LOCALE` / `APP_DEFAULT_TIMEZONE` | `UTF-8` / `en_US` / `UTC` | localization defaults |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `localhost` / `5432` / `refer_cheat_to_do` / `refer_cheat_to_do` / `refer_cheat_to_do` | primary PostgreSQL connection |
| `DATABASE_URL` | none | full DSN that overrides the individual `DB_*` values; query arguments become driver options, so a TLS-only managed database can use `...?ssl_mode=require` |
| `TEST_DB_HOST` / `TEST_DB_PORT` / `TEST_DB_DATABASE` / `TEST_DB_USERNAME` / `TEST_DB_PASSWORD` | fall back to the `DB_*` values, database defaults to `<DB_DATABASE>_test` | PHPUnit connection |
| `DATABASE_TEST_URL` | none | full DSN for the test connection |

`.env.example` contains a development-oriented starting point for these values.

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| 500 error mentioning `App.fullBaseUrl is not configured` | `APP_FULL_BASE_URL` is unset while `DEBUG=false`; set it and reload PHP-FPM |
| 400 `Invalid Host header` | the request `Host` does not match the host in `APP_FULL_BASE_URL`; fix the value or the proxy configuration |
| `Class "josegonzalez\Dotenv\Loader" not found` | a `.env` file exists but dev dependencies are not installed; remove `.env` and use process environment variables, or run `composer install` without `--no-dev` |
| `could not find driver` | `php8.3-pgsql` is missing, or PHP-FPM was not restarted after installing it |
| Migrations fail with permission errors | the database role lacks rights on the `public` schema; make it the database owner or grant them |
| `relation "cake_migrations" already exists`, or a 500 error saying the column `id` was not found in table `users` | the tables exist but the connecting role has no privileges on them, so the privilege-filtered `information_schema` views appear empty while the objects are still there; grant the role rights on the existing objects (see below) and run `bin/cake cache clear_all` |
| Writes fail with permission errors | `logs/` and `tmp/` are not writable by the PHP-FPM user |
| Every authenticated request returns 401 | session cookies are not being sent back, or PHP's session save path is not writable |

### Repairing database privileges

Both `relation "cake_migrations" already exists` and
`The column "id" was not found in table "users"` have the same cause: the schema was created by one role
(often the database owner or a restored dump) while the application connects as
another role that holds no privileges on those objects. PostgreSQL filters
`information_schema.tables` and `information_schema.columns` by privilege, so
CakePHP sees an empty schema and tries to create tables that already exist.

Confirm it by connecting with the exact credentials from `DATABASE_URL`:

```sql
SELECT current_database(), current_user, current_schema();
SELECT table_name FROM information_schema.tables WHERE table_schema = 'public';
SELECT relname FROM pg_class c
  JOIN pg_namespace n ON n.oid = c.relnamespace
  WHERE n.nspname = 'public' AND c.relkind = 'r';
```

If the first list is empty while the second is not, grant the missing rights as
the owner. `ALTER DEFAULT PRIVILEGES` below affects objects created by that same
role unless you add `FOR ROLE <owner_role>`:

```sql
GRANT USAGE ON SCHEMA public TO refer_cheat_to_do;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO refer_cheat_to_do;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO refer_cheat_to_do;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO refer_cheat_to_do;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO refer_cheat_to_do;
```

Then clear the cached table metadata, which otherwise keeps serving the broken
reflection:

```bash
bin/cake cache clear_all
```

Running `bin/cake migrations migrate` as the same role the application connects
with avoids the problem entirely.

## Local development

Open the repository in the devcontainer for PHP 8.3 and PostgreSQL that are
already configured, or install the same prerequisites natively. Then:

1. Copy the environment template and adjust the values:
   ```bash
   cp .env.example .env
   ```
2. Install dependencies (including dev dependencies, which provide the `.env`
   loader, PHPUnit, and the static analysis tools):
   ```bash
   composer install
   ```
3. Create the application and test databases if they do not exist:
   ```bash
   createdb refer_cheat_to_do
   createdb refer_cheat_to_do_test
   ```
4. Apply migrations to both connections:
   ```bash
   bin/cake migrations migrate -c default
   bin/cake migrations migrate -c test
   ```
5. Start the development server:
   ```bash
   bin/cake server -H 0.0.0.0 -p 8765
   ```
6. Verify the API:
   ```bash
   curl http://localhost:8765/api/health
   ```

## Test, lint, and static analysis commands

```bash
composer lint
composer cs-check
composer stan
composer test
composer check
```

## Development workflow

- keep controllers thin and JSON-oriented
- place business rules in services/entities/tables/policies as the domain is added
- enforce authorization and validation in backend code
- build MVP in small vertical slices following the roadmap

## Current project status

Authentication, ToDo capture, and tagging slices are implemented on top of the
application foundation, shared API conventions, and developer tooling. Account
self-registration, richer research and knowledge-refinement workflows, and the
remaining roadmap capabilities are still pending.
