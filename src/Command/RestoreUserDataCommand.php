<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\DataPortabilityService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use DomainException;
use Throwable;

class RestoreUserDataCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Restore a JSONL export into an existing empty account.';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription(static::getDescription())
            ->addArgument('email', ['required' => true, 'help' => 'Existing empty target account email.'])
            ->addArgument('path', ['required' => true, 'help' => 'Versioned JSONL export file.']);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $stream = false;
        try {
            $path = (string)$args->getArgument('path');
            if (!is_file($path) || !is_readable($path)) {
                throw new DomainException('Cannot read export file.');
            }
            $user = $this->fetchTable('Users')->find()->select(['id'])
                ->where(['email' => strtolower(trim((string)$args->getArgument('email')))])->firstOrFail();
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                throw new DomainException('Cannot read export file.');
            }
            (new DataPortabilityService($this->getTableLocator()))->restore((int)$user->get('id'), $stream);
            $io->success('Restore complete.');

            return static::CODE_SUCCESS;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return static::CODE_ERROR;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
