<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\User;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Creates an account or resets its password without a registration endpoint.
 *
 * The password is saved through the Users table, so it is validated and hashed by
 * {@see \App\Model\Entity\User::_setPassword()} exactly like the login endpoint expects.
 */
class SetUserPasswordCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Create a user or reset an existing password with a correctly hashed value.';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription([
                static::getDescription(),
                'Submit the plain password to `/api/auth/login`; the stored hash is never a valid credential.',
            ])
            ->addArgument('email', [
                'help' => 'Account email address. It is trimmed and lowercased like the login endpoint does.',
                'required' => true,
            ])
            ->addOption('password', [
                'help' => 'The new plain password. Omit it to be prompted and keep it out of the shell history.',
            ])
            ->addOption('create', [
                'boolean' => true,
                'help' => 'Create the account when the email is unknown.',
            ]);

        return $parser;
    }

    /**
     * Create or update the account password.
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $email = strtolower(trim((string)$args->getArgument('email')));
        if ($email === '') {
            $io->error('An email address is required.');

            return static::CODE_ERROR;
        }

        $password = $this->resolvePassword($args, $io);
        if ($password === null) {
            return static::CODE_ERROR;
        }

        $users = $this->fetchTable('Users');
        /** @var \App\Model\Entity\User|null $existing */
        $existing = $users->find()->where(['email' => $email])->first();

        if ($existing === null && !$args->getOption('create')) {
            $io->error(sprintf('No user found for `%s`. Pass --create to add the account.', $email));

            return static::CODE_ERROR;
        }

        if ($existing instanceof User) {
            $user = $existing;
            $users->patchEntity($user, ['password' => $password]);
        } else {
            /** @var \App\Model\Entity\User $user */
            $user = $users->newEntity(['email' => $email, 'password' => $password]);
        }

        if (!$users->save($user)) {
            $io->error(sprintf('Could not save `%s`.', $email));
            foreach ($this->flattenErrors($user->getErrors()) as $message) {
                $io->out('- ' . $message);
            }

            return static::CODE_ERROR;
        }

        $io->success(sprintf(
            '%s `%s` (id %d).',
            $existing === null ? 'Created' : 'Updated the password of',
            $email,
            (int)$user->id,
        ));

        return static::CODE_SUCCESS;
    }

    /**
     * Read the password from the option or prompt for it twice.
     */
    private function resolvePassword(Arguments $args, ConsoleIo $io): ?string
    {
        $password = $args->getOption('password');
        if ($password !== null) {
            return (string)$password;
        }

        $entered = $io->ask('New password');
        if ($entered !== $io->ask('Repeat the password')) {
            $io->error('The passwords do not match.');

            return null;
        }

        return $entered;
    }

    /**
     * Flatten nested validation errors into readable lines.
     *
     * @param array<string, mixed> $errors
     * @return list<string>
     */
    private function flattenErrors(array $errors, string $prefix = ''): array
    {
        $messages = [];
        foreach ($errors as $field => $error) {
            $path = $prefix === '' ? (string)$field : $prefix . '.' . $field;
            if (is_array($error)) {
                $messages = array_merge($messages, $this->flattenErrors($error, $path));
                continue;
            }
            $messages[] = sprintf('%s: %s', $path, (string)$error);
        }

        return $messages;
    }
}
