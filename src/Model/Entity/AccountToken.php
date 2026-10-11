<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * A single-use, expiring invitation or password-reset token. Only the SHA-256 hash is stored.
 *
 * @property int $id
 * @property string $purpose
 * @property string $email
 * @property int|null $user_id
 * @property int|null $issued_by_user_id
 * @property string $token_hash
 * @property \Cake\I18n\DateTime $expires
 * @property \Cake\I18n\DateTime|null $used
 * @property \Cake\I18n\DateTime|null $created
 */
class AccountToken extends Entity
{
    /**
     * Tokens are only created by {@see \App\Service\AccountTokenService}; nothing is mass assignable.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [];

    /**
     * The hash is never serialized.
     *
     * @var list<string>
     */
    protected array $_hidden = [
        'token_hash',
    ];
}
