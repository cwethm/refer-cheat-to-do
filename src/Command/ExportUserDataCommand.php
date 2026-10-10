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

class ExportUserDataCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Export an existing account to a new private JSONL file.';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription(static::getDescription())
            ->addArgument('email', ['required' => true, 'help' => 'Existing account email.'])
            ->addArgument('path', ['required' => true, 'help' => 'New export file; never overwritten.']);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $path = (string)$args->getArgument('path');
        $stream = false;
        try {
            if (file_exists($path) || is_link($path)) {
                throw new DomainException('Export file already exists.');
            }
            $user = $this->fetchTable('Users')->find()->select(['id'])
                ->where(['email' => strtolower(trim((string)$args->getArgument('email')))])->firstOrFail();
            $mask = umask(0077);
            try {
                $stream = fopen($path, 'xb');
            } finally {
                umask($mask);
            }
            if ($stream === false) {
                throw new DomainException('Cannot create export file; it may already exist.');
            }
            if (!chmod($path, 0600)) {
                throw new DomainException('Cannot secure export file.');
            }
            (new DataPortabilityService($this->getTableLocator()))->writeExport((int)$user->get('id'), $stream);
            if (!fflush($stream)) {
                throw new DomainException('Cannot flush export file.');
            }
            fclose($stream);
            $io->success('Export complete.');

            return static::CODE_SUCCESS;
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
                unlink($path);
            }
            $io->error($exception->getMessage());

            return static::CODE_ERROR;
        }
    }
}
