<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\Auth\DefaultPasswordHasher;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class UsersTableTest extends TestCase
{
    protected array $fixtures = ['app.Users'];

    public function testPasswordIsHashedWhenSaving(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $entity = $users->newEntity([
            'email' => 'new@example.com',
            'password' => 'Password123!',
        ]);

        $this->assertEmpty($entity->getErrors());
        $users->saveOrFail($entity);

        $saved = $users->get((int)$entity->id);
        $this->assertNotSame('Password123!', $saved->password);
        $this->assertTrue((new DefaultPasswordHasher())->check('Password123!', (string)$saved->password));
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $entity = $users->newEntity([
            'email' => 'owner@example.com',
            'password' => 'Password123!',
        ]);

        $this->assertFalse((bool)$users->save($entity));
        $this->assertArrayHasKey('_isUnique', $entity->getErrors()['email'] ?? []);
    }
}
