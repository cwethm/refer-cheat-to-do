# refer-cheat-to-do

API-first foundation for a web-first information continuity, ToDo, research, and knowledge-refinement application.

## Current MVP scope

This repository currently contains the development foundation for **MVP 1** only:

- CakePHP 5 application skeleton
- PostgreSQL-first configuration
- JSON API conventions
- initial health-check endpoint
- PHPUnit, coding standards, and static analysis setup
- devcontainer/Docker-compatible development environment
- GitHub Actions CI

No domain schema beyond the framework foundation is implemented yet.

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
├── docs/
│   └── adr/
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

## Local installation

1. Copy the environment template:
   ```bash
   cp .env.example .env
   ```
2. Install Composer dependencies:
   ```bash
   composer install
   ```
3. Create the application tmp/log directories if needed:
   ```bash
   mkdir -p logs tmp/cache/{models,persistent} tmp/sessions tmp/tests
   ```
4. Start the development server:
   ```bash
   bin/cake server -H 0.0.0.0 -p 8765
   ```
5. Verify the API:
   ```bash
   curl http://localhost:8765/api/health
   ```

## Database setup

Native/local PostgreSQL settings come from `.env`:

```dotenv
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=refer_cheat_to_do
DB_USERNAME=refer_cheat_to_do
DB_PASSWORD=refer_cheat_to_do
TEST_DB_HOST=127.0.0.1
TEST_DB_PORT=5432
TEST_DB_DATABASE=refer_cheat_to_do_test
TEST_DB_USERNAME=refer_cheat_to_do
TEST_DB_PASSWORD=refer_cheat_to_do
```

Create the databases before adding migrations or persistence-backed tests.

## Devcontainer / Docker

Open the repository in a devcontainer to start with PHP 8.3 and PostgreSQL preconfigured.

Inside the container, run:

```bash
composer install
bin/cake server -H 0.0.0.0 -p 8765
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

The repository is ready for the next MVP slice: authentication and the first ToDo capture flow. The current code intentionally stops at the application foundation, shared API conventions, and developer tooling.
