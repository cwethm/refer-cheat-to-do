<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddTodoReviewFields extends BaseMigration
{
    /**
     * Add review scheduling bookkeeping to todos.
     */
    public function up(): void
    {
        $this->table('todos')
            ->addColumn('last_reviewed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('next_review_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('review_interval_days', 'integer', ['null' => false, 'default' => 7])
            ->addIndex(['user_id', 'next_review_at'], ['name' => 'todos_user_next_review_idx'])
            ->update();

        $this->execute(
            'ALTER TABLE todos ADD CONSTRAINT todos_review_interval_chk '
            . 'CHECK (review_interval_days BETWEEN 1 AND 365)',
        );
    }

    /**
     * Remove review scheduling bookkeeping from todos.
     */
    public function down(): void
    {
        $this->execute('ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_review_interval_chk');

        $this->table('todos')
            ->removeIndexByName('todos_user_next_review_idx')
            ->removeColumn('last_reviewed_at')
            ->removeColumn('next_review_at')
            ->removeColumn('review_interval_days')
            ->update();
    }
}
