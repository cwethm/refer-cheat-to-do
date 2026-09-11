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

        $schema = $this->schemaName();
        $this->execute(sprintf(
            'CREATE UNIQUE INDEX users_email_lower_unique ON %s.users (LOWER(email));',
            $schema,
        ));
    }

    public function down(): void
    {
        $schema = $this->schemaName();
        $this->execute(sprintf('DROP INDEX IF EXISTS %s.users_email_lower_unique;', $schema));
        $this->table('users')->drop()->save();
    }

    protected function schemaName(): string
    {
        $row = $this->fetchRow('SELECT current_schema() AS name');
        if (is_array($row) && isset($row['name']) && is_string($row['name'])) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $row['name']) ?: 'public';
        }

        return 'public';
    }
}
