<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use ArrayObject;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('email');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('email')
            ->maxLength('email', 255)
            ->requirePresence('email', 'create')
            ->notEmptyString('email')
            ->email('email');

        $validator
            ->scalar('password')
            ->minLength('password', 8)
            ->maxLength('password', 255)
            ->requirePresence('password', 'create')
            ->notEmptyString('password');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            function ($entity): bool {
                $email = (string)$entity->get('email');
                if ($email === '') {
                    return true;
                }

                $query = $this->find()
                    ->select(['id'])
                    ->where(['LOWER(email)' => mb_strtolower($email)]);
                if (!$entity->isNew() && $entity->get('id') !== null) {
                    $query->where(['id !=' => (int)$entity->get('id')]);
                }

                return $query->first() === null;
            },
            'emailLowerUnique',
            ['errorField' => 'email', 'message' => 'This email is already in use.'],
        );

        return $rules;
    }

    /**
     * @param \ArrayObject<string, mixed> $data
     * @param \ArrayObject<string, mixed> $options
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }
    }
}
