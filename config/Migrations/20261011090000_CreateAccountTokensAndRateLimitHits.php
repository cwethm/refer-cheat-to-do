<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateAccountTokensAndRateLimitHits extends BaseMigration
{
    /**
     * Create invitation/reset token storage, rate-limit storage and the session credential version (Slice 19).
     */
    public function up(): void
    {
        $this->table('users')
            ->addColumn('credential_version', 'integer', ['null' => false, 'default' => 1])
            ->update();
        $this->execute(
            'ALTER TABLE users ADD CONSTRAINT users_credential_version_chk CHECK (credential_version > 0)',
        );

        $this->table('account_tokens')
            ->addColumn('purpose', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('issued_by_user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('expires', 'datetime', ['null' => false])
            ->addColumn('used', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'account_tokens_token_hash_unique'])
            ->addIndex(['purpose', 'email'], ['name' => 'account_tokens_purpose_email_idx'])
            ->addIndex(['user_id'], ['name' => 'account_tokens_user_idx'])
            ->addIndex(['issued_by_user_id'], ['name' => 'account_tokens_issuer_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('issued_by_user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
        $this->execute(
            'ALTER TABLE account_tokens ADD CONSTRAINT account_tokens_purpose_chk '
            . "CHECK ((purpose = 'invitation' AND user_id IS NULL AND issued_by_user_id IS NOT NULL) "
            . "OR (purpose = 'password_reset' AND user_id IS NOT NULL))",
        );
        $this->execute(
            'ALTER TABLE account_tokens ADD CONSTRAINT account_tokens_token_hash_chk '
            . "CHECK (token_hash ~ '^[0-9a-f]{64}$')",
        );

        $this->table('rate_limit_hits')
            ->addColumn('scope', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('bucket', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['scope', 'bucket', 'created'], ['name' => 'rate_limit_hits_bucket_idx'])
            ->create();
        $this->execute(
            'ALTER TABLE rate_limit_hits ADD CONSTRAINT rate_limit_hits_bucket_chk '
            . "CHECK (bucket ~ '^[0-9a-f]{64}$')",
        );
    }

    /**
     * Drop the Slice 19 tables and the credential version column.
     */
    public function down(): void
    {
        $this->table('rate_limit_hits')->drop()->update();
        $this->table('account_tokens')->drop()->update();
        $this->execute('ALTER TABLE users DROP CONSTRAINT users_credential_version_chk');
        $this->table('users')->removeColumn('credential_version')->update();
    }
}
