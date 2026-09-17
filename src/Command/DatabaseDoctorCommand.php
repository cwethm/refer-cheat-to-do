<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\DatabaseDiagnosticsService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use RuntimeException;

/**
 * Reports why the ORM cannot reflect the PostgreSQL schema of a connection.
 */
class DatabaseDoctorCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Diagnose PostgreSQL schema visibility problems behind API 500 errors.';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription([
                static::getDescription(),
                'Compares what `pg_catalog` contains with what CakePHP can read from '
                    . '`information_schema`, and prints the repair steps for the difference.',
            ])
            ->addOption('connection', [
                'short' => 'c',
                'default' => 'default',
                'help' => 'The datasource to inspect.',
            ]);

        return $parser;
    }

    /**
     * Print the diagnosis for the requested connection.
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $connectionName = (string)$args->getOption('connection');
        $diagnostics = new DatabaseDiagnosticsService();

        try {
            $report = $diagnostics->diagnose($connectionName);
        } catch (MissingDatasourceConfigException | RuntimeException $e) {
            $io->error($e->getMessage());

            return static::CODE_ERROR;
        }

        $this->printConnection($io, $report);
        $this->printTables($io, $report);

        /** @var list<string> $problems */
        $problems = $report['problems'];
        if ($problems === []) {
            $io->success('The schema is fully visible to the connecting role.');

            return static::CODE_SUCCESS;
        }

        $io->out('');
        $io->out(sprintf('<error>Diagnosis: %s</error>', (string)$report['status']));
        foreach ($problems as $problem) {
            $io->out('- ' . $problem);
        }

        /** @var list<string> $remedies */
        $remedies = $report['remedies'];
        if ($remedies !== []) {
            $io->out('');
            $io->info('How to fix it');
            foreach ($remedies as $remedy) {
                $io->out('- ' . $remedy);
            }
        }

        /** @var list<string> $repairSql */
        $repairSql = $report['repair_sql'];
        if ($repairSql !== []) {
            $io->out('');
            $io->info('Run as the owner of the objects, connected to the same database:');
            foreach ($repairSql as $statement) {
                $io->out('  ' . $statement);
            }
        }

        return static::CODE_ERROR;
    }

    /**
     * Print the connection identity that decides which rows the ORM can read.
     *
     * @param array<string, mixed> $report
     */
    private function printConnection(ConsoleIo $io, array $report): void
    {
        $io->info(sprintf('Connection: %s', (string)$report['connection']));
        $io->out(sprintf('  configured database : %s', (string)$report['configured_database']));
        $io->out(sprintf('  current_database()  : %s', (string)$report['current_database']));
        $io->out(sprintf('  configured schema   : %s', (string)$report['configured_schema']));
        $io->out(sprintf('  current_schema()    : %s', (string)$report['current_schema']));
        $io->out(sprintf('  current_user        : %s', (string)$report['current_user']));
        $io->out('');
    }

    /**
     * Print one row per table with the catalog and reflected column counts.
     *
     * @param array<string, mixed> $report
     */
    private function printTables(ConsoleIo $io, array $report): void
    {
        /** @var list<array<string, mixed>> $tables */
        $tables = $report['tables'];
        if ($tables === []) {
            $io->out('No tables found in the schema.');

            return;
        }

        $rows = [['Table', 'Owner', 'Listed', 'Catalog columns', 'Visible columns']];
        foreach ($tables as $table) {
            /** @var list<string> $missing */
            $missing = $table['missing_columns'];
            $visible = sprintf('%d', (int)$table['reflected_columns']);
            if ($missing !== []) {
                $visible .= sprintf(' (missing: %s)', implode(', ', array_slice($missing, 0, 5)));
            }
            $rows[] = [
                (string)$table['name'],
                (string)$table['owner'],
                $table['reflected'] === true ? 'yes' : 'no',
                sprintf('%d', (int)$table['catalog_columns']),
                $visible,
            ];
        }

        $io->helper('table')->output($rows);
    }
}
