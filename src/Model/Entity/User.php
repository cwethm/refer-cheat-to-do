<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class User extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'email' => true,
        'password' => true,
        'created' => true,
        'modified' => true,
    ];

    /**
     * Hidden fields for JSON serialization.
     *
     * @var list<string>
     */
    protected array $_hidden = [
        'password',
    ];

    /**
     * Hash and set the password value.
     */
    protected function _setPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
