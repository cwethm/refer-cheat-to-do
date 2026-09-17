<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

/**
 * Explains why CakePHP cannot reflect a PostgreSQL table.
 *
 * CakePHP reads columns from the privilege-filtered `information_schema` views while it reads
 * primary keys, indexes, and foreign keys from `pg_catalog`, which is not privilege-filtered.
 * When the two disagree the ORM throws "Columns used in constraints must be added to the Table
 * schema first", which surfaces as a 500 response on the first query of an endpoint.
 *
 * The service compares both sources and classifies the mismatch so the operator knows whether to
 * grant privileges, fix the connection database name, or run migrations.
 */
class DatabaseDiagnosticsService
{
    public const STATUS_OK = 'ok';
    public const STATUS_CATALOG_MISMATCH = 'catalog_mismatch';
    public const STATUS_MISSING_PRIVILEGES = 'missing_privileges';
    public const STATUS_MISSING_SCHEMA = 'missing_schema';

    /**
     * Tables the application cannot serve a request without.
     *
     * @var list<string>
     */
    public const REQUIRED_TABLES = ['users', 'todos'];

    /**
     * Collect the facts for a connection and classify them.
     *
     * @return array<string, mixed>
     */
    public function diagnose(string $connectionName = 'default'): array
    {
        return $this->evaluate($this->collectFacts($connectionName));
    }

    /**
     * Read the schema state from `pg_catalog` and `information_schema`.
     *
     * The `information_schema` queries repeat the filters CakePHP itself applies, so an empty
     * result here means the ORM sees an empty table as well.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException When the connection does not use the PostgreSQL driver.
     */
    public function collectFacts(string $connectionName = 'default'): array
    {
        $connection = ConnectionManager::get($connectionName);
        if (!$connection instanceof Connection || !$connection->getDriver() instanceof Postgres) {
            throw new RuntimeException(sprintf(
                'Connection `%s` does not use the PostgreSQL driver; nothing to diagnose.',
                $connectionName,
            ));
        }

        $config = $connection->config();
        $database = (string)($config['database'] ?? '');
        $schema = (string)($config['schema'] ?? 'public');

        /** @var array<string, mixed> $identity */
        $identity = $connection->execute(
            'SELECT current_database() AS "current_database",
                current_schema() AS "current_schema",
                current_user AS "current_user"',
        )->fetch('assoc') ?: [];

        $catalogTables = $connection->execute(
            'SELECT c.relname AS "name", pg_catalog.pg_get_userbyid(c.relowner) AS "owner"
            FROM pg_catalog.pg_class c
            INNER JOIN pg_catalog.pg_namespace n ON (n.oid = c.relnamespace)
            WHERE n.nspname = ? AND c.relkind IN (\'r\', \'p\')
            ORDER BY c.relname',
            [$schema],
        )->fetchAll('assoc');

        $catalogColumns = $connection->execute(
            'SELECT c.relname AS "table_name", a.attname AS "column_name"
            FROM pg_catalog.pg_class c
            INNER JOIN pg_catalog.pg_namespace n ON (n.oid = c.relnamespace)
            INNER JOIN pg_catalog.pg_attribute a ON (a.attrelid = c.oid)
            WHERE n.nspname = ? AND c.relkind IN (\'r\', \'p\') AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY c.relname, a.attnum',
            [$schema],
        )->fetchAll('assoc');

        // Mirrors PostgresSchemaDialect::listTablesSql(), which does not filter by catalog.
        $reflectedTables = $connection->execute(
            'SELECT table_name AS "name" FROM information_schema.tables WHERE table_schema = ?',
            [$schema],
        )->fetchAll('assoc');

        // Mirrors PostgresSchemaDialect::describeColumns(), which filters by the configured database.
        $reflectedColumns = $connection->execute(
            'SELECT table_name AS "table_name", column_name AS "column_name"
            FROM information_schema.columns
            WHERE table_schema = ? AND table_catalog = ?',
            [$schema, $database],
        )->fetchAll('assoc');

        return [
            'connection' => $connectionName,
            'configured_database' => $database,
            'configured_schema' => $schema,
            'current_database' => (string)($identity['current_database'] ?? ''),
            'current_schema' => (string)($identity['current_schema'] ?? ''),
            'current_user' => (string)($identity['current_user'] ?? ''),
            'catalog_tables' => $this->indexOwners($catalogTables),
            'catalog_columns' => $this->groupColumns($catalogColumns),
            'reflected_tables' => $this->columnValues($reflectedTables, 'name'),
            'reflected_columns' => $this->groupColumns($reflectedColumns),
        ];
    }

    /**
     * Classify collected facts and build the matching remediation.
     *
     * @param array<string, mixed> $facts Output of {@see self::collectFacts()}.
     * @return array<string, mixed>
     */
    public function evaluate(array $facts): array
    {
        $connectionName = (string)($facts['connection'] ?? 'default');
        $configuredDatabase = (string)($facts['configured_database'] ?? '');
        $currentDatabase = (string)($facts['current_database'] ?? '');
        $schema = (string)($facts['configured_schema'] ?? 'public');
        $currentUser = (string)($facts['current_user'] ?? '');

        /** @var array<string, string> $catalogTables */
        $catalogTables = is_array($facts['catalog_tables'] ?? null) ? $facts['catalog_tables'] : [];
        /** @var array<string, list<string>> $catalogColumns */
        $catalogColumns = is_array($facts['catalog_columns'] ?? null) ? $facts['catalog_columns'] : [];
        /** @var list<string> $reflectedTables */
        $reflectedTables = is_array($facts['reflected_tables'] ?? null) ? array_values($facts['reflected_tables']) : [];
        /** @var array<string, list<string>> $reflectedColumns */
        $reflectedColumns = is_array($facts['reflected_columns'] ?? null) ? $facts['reflected_columns'] : [];

        $tables = [];
        $invisibleTables = [];
        $tablesWithHiddenColumns = [];
        foreach ($catalogTables as $table => $owner) {
            $expected = $catalogColumns[$table] ?? [];
            $visible = $reflectedColumns[$table] ?? [];
            $missingColumns = array_values(array_diff($expected, $visible));
            $isReflected = in_array($table, $reflectedTables, true);

            if (!$isReflected) {
                $invisibleTables[] = $table;
            }
            if ($missingColumns !== []) {
                $tablesWithHiddenColumns[] = $table;
            }

            $tables[] = [
                'name' => $table,
                'owner' => $owner,
                'reflected' => $isReflected,
                'catalog_columns' => count($expected),
                'reflected_columns' => count($visible),
                'missing_columns' => $missingColumns,
            ];
        }

        $missingTables = array_values(array_diff(self::REQUIRED_TABLES, array_keys($catalogTables)));
        $owners = array_values(array_unique(array_values($catalogTables)));

        $status = self::STATUS_OK;
        $problems = [];
        $remedies = [];
        $repairSql = [];

        if ($configuredDatabase !== '' && $currentDatabase !== '' && $configuredDatabase !== $currentDatabase) {
            $status = self::STATUS_CATALOG_MISMATCH;
            $problems[] = sprintf(
                'The connection is configured for database `%s` but the server reports `%s`. '
                    . 'CakePHP filters `information_schema.columns` on the configured name, so every column '
                    . 'lookup returns nothing while indexes and constraints still resolve from `pg_catalog`.',
                $configuredDatabase,
                $currentDatabase,
            );
            $remedies[] = sprintf(
                'Set the database in `DATABASE_URL` (or `DB_DATABASE`) to `%s`. A connection pooler often '
                    . 'causes this: the pool name is used to connect while queries run against the pooled database. '
                    . 'Connect to the database itself, or name the pool exactly like the database.',
                $currentDatabase,
            );
        } elseif ($tablesWithHiddenColumns !== [] || $invisibleTables !== []) {
            $status = self::STATUS_MISSING_PRIVILEGES;
            if ($tablesWithHiddenColumns !== []) {
                $problems[] = sprintf(
                    'Role `%s` cannot read the columns of %s from `information_schema`, '
                        . 'while `pg_catalog` still reports them.',
                    $currentUser,
                    $this->formatTableList($tablesWithHiddenColumns),
                );
            }
            if ($invisibleTables !== []) {
                $problems[] = sprintf(
                    'Role `%s` cannot see %s in `information_schema.tables`.',
                    $currentUser,
                    $this->formatTableList($invisibleTables),
                );
            }
            $remedies[] = sprintf(
                'Grant `%s` privileges on the existing objects, or transfer ownership to it, '
                    . 'and run migrations as that role from now on.',
                $currentUser,
            );
            $repairSql = $this->buildGrantSql($schema, $currentUser, $owners);
        } elseif ($missingTables !== []) {
            $status = self::STATUS_MISSING_SCHEMA;
            $problems[] = sprintf(
                'Database `%s` is missing the required table(s) %s in schema `%s`.',
                $currentDatabase,
                $this->formatTableList($missingTables),
                $schema,
            );
            $remedies[] = sprintf(
                'Run `bin/cake migrations migrate -c %s` with the same role the application connects as.',
                $connectionName,
            );
        }

        if ($status !== self::STATUS_OK) {
            $remedies[] = 'Afterwards run `bin/cake cache clear_all`, otherwise the cached (broken) '
                . 'table metadata keeps being served.';
        }

        return [
            'connection' => $connectionName,
            'status' => $status,
            'healthy' => $status === self::STATUS_OK,
            'configured_database' => $configuredDatabase,
            'current_database' => $currentDatabase,
            'configured_schema' => $schema,
            'current_schema' => (string)($facts['current_schema'] ?? ''),
            'current_user' => $currentUser,
            'tables' => $tables,
            'problems' => $problems,
            'remedies' => $remedies,
            'repair_sql' => $repairSql,
        ];
    }

    /**
     * Render a readable, truncated table list.
     *
     * @param list<string> $tables
     */
    private function formatTableList(array $tables): string
    {
        $shown = array_slice($tables, 0, 5);
        $rendered = '`' . implode('`, `', $shown) . '`';
        $remaining = count($tables) - count($shown);
        if ($remaining > 0) {
            $rendered .= sprintf(' and %d more', $remaining);
        }

        return $rendered;
    }

    /**
     * Build the grant statements that make existing objects visible to the connecting role.
     *
     * @param list<string> $owners Roles that own the tables in the schema.
     * @return list<string>
     */
    private function buildGrantSql(string $schema, string $grantee, array $owners): array
    {
        if ($grantee === '') {
            return [];
        }

        $quotedSchema = $this->quoteIdentifier($schema);
        $quotedGrantee = $this->quoteIdentifier($grantee);

        $statements = [
            sprintf('GRANT USAGE ON SCHEMA %s TO %s;', $quotedSchema, $quotedGrantee),
            sprintf(
                'GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA %s TO %s;',
                $quotedSchema,
                $quotedGrantee,
            ),
            sprintf('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA %s TO %s;', $quotedSchema, $quotedGrantee),
        ];

        foreach ($owners as $owner) {
            if ($owner === '' || $owner === $grantee) {
                continue;
            }
            $quotedOwner = $this->quoteIdentifier($owner);
            $statements[] = sprintf(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA %s '
                    . 'GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %s;',
                $quotedOwner,
                $quotedSchema,
                $quotedGrantee,
            );
            $statements[] = sprintf(
                'ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA %s GRANT USAGE, SELECT ON SEQUENCES TO %s;',
                $quotedOwner,
                $quotedSchema,
                $quotedGrantee,
            );
        }

        return $statements;
    }

    /**
     * Quote an identifier for the generated repair statements.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Index table owners by table name.
     *
     * @param array<mixed> $rows
     * @return array<string, string>
     */
    private function indexOwners(array $rows): array
    {
        $owners = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $owners[(string)($row['name'] ?? '')] = (string)($row['owner'] ?? '');
        }

        return $owners;
    }

    /**
     * Group column names by table name.
     *
     * @param array<mixed> $rows
     * @return array<string, list<string>>
     */
    private function groupColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $columns[(string)($row['table_name'] ?? '')][] = (string)($row['column_name'] ?? '');
        }

        return $columns;
    }

    /**
     * Extract a single column from result rows.
     *
     * @param array<mixed> $rows
     * @return list<string>
     */
    private function columnValues(array $rows, string $column): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $values[] = (string)($row[$column] ?? '');
        }

        return $values;
    }
}
