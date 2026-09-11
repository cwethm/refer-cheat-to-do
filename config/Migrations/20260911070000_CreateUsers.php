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

        $this->execute('CREATE UNIQUE INDEX users_email_lower_unique ON public.users (LOWER(email));');
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS public.users_email_lower_unique;');
        $this->table('users')->drop()->save();
    }
}
