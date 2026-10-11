<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\User;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    /**
     * Initialize users table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('email');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('Todos', [
            'foreignKey' => 'user_id',
            'dependent' => false,
        ]);
        $this->hasMany('Tags', [
            'foreignKey' => 'user_id',
            'dependent' => false,
        ]);
    }

    /**
     * Define default validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('email')
            ->maxLength('email', 191)
            ->requirePresence('email', 'create')
            ->notEmptyString('email')
            ->email('email');

        $validator
            ->scalar('password')
            ->maxLength('password', 255)
            ->requirePresence('password', 'create')
            ->notEmptyString('password')
            ->minLength('password', 8);

        return $validator;
    }

    /**
     * Add users table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email']), ['errorField' => 'email']);

        return $rules;
    }

    /**
     * Advance the credential version and retire outstanding reset links when a password changes.
     *
     * Every session records the version it was issued under, so this one write signs out every
     * other session, whichever path changed the password (sign-in, reset or the operator command).
     * The increment is done in SQL inside the save transaction so a partially loaded entity
     * cannot move the version backwards.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event afterSave event.
     * @param \Cake\Datasource\EntityInterface $entity User that was saved.
     * @param \ArrayObject<string, mixed> $options Save options.
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$entity instanceof User || $entity->isNew() || !$entity->isDirty('password')) {
            return;
        }

        $userId = (int)$entity->id;
        $advance = $this->updateQuery();
        $advance
            ->set(['credential_version' => $advance->expr('credential_version + 1')])
            ->where(['id' => $userId])
            ->execute();
        $this->getConnection()->updateQuery()
            ->update('account_tokens')
            ->set(['used' => DateTime::now()], ['used' => 'datetime'])
            ->where(['user_id' => $userId, 'purpose' => 'password_reset', 'used IS' => null])
            ->execute();
    }

    /**
     * Current credential version of an account, or `null` when the account no longer exists.
     */
    public function credentialVersion(int $userId): ?int
    {
        $version = $this->find()
            ->select(['credential_version'])
            ->where(['id' => $userId])
            ->disableHydration()
            ->first();

        return is_array($version) ? (int)$version['credential_version'] : null;
    }
}
