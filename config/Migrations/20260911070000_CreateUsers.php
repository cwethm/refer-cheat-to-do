<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateUsers extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('password', 'string', ['limit' => 255, 'null' => false])
            ->addTimestamps('created', 'modified')
            ->create();

        if ($this->isPostgresAdapter()) {
            $this->execute('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email));');
        }
    }

    public function down(): void
    {
        if ($this->isPostgresAdapter()) {
            $this->execute('DROP INDEX IF EXISTS users_email_lower_unique;');
        }
        $this->table('users')->drop()->save();
    }

    protected function isPostgresAdapter(): bool
    {
        return str_contains(strtolower($this->getAdapter()::class), 'postgres');
    }
}
