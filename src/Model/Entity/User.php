<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\Auth\DefaultPasswordHasher;
use Cake\ORM\Entity;

class User extends Entity
{
    protected array $_accessible = [
        'email' => true,
        'password' => true,
    ];

    protected array $_hidden = [
        'password',
    ];

    protected function _setPassword(?string $password): ?string
    {
        if ($password === null || $password === '') {
            return $password;
        }

        return (new DefaultPasswordHasher())->hash($password);
    }
}
