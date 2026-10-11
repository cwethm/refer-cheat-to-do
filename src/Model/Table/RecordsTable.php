<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Owner-only records of discussions and events (Slice 27).
 */
class RecordsTable extends Table
{
    public const KIND_DISCUSSION = 'discussion';
    public const KIND_EVENT = 'event';

    /**
     * Closed kind set, mirrored by `records_kind_chk`.
     *
     * @var list<string>
     */
    public const KINDS = [self::KIND_DISCUSSION, self::KIND_EVENT];

    /**
     * Every client-writable record field. `occurred_at` is parsed by the controller first.
     *
     * @var list<string>
     */
    public const WRITABLE_FIELDS = [
        'kind',
        'title',
        'occurred_at',
        'participants',
        'location',
        'summary',
        'details',
    ];

    /**
     * Optional plain-text fields, stored as `NULL` when empty.
     *
     * @var list<string>
     */
    public const OPTIONAL_TEXT_FIELDS = ['participants', 'location', 'summary', 'details'];

    public const MAX_TITLE_CHARS = 255;
    public const MAX_PARTICIPANTS_CHARS = 2000;
    public const MAX_LOCATION_CHARS = 255;
    public const MAX_SUMMARY_CHARS = 500;
    public const MAX_DETAILS_BYTES = 65536;

    /**
     * Authoritative containment used whenever a record is serialized with its Tags.
     *
     * @var array<string, mixed>
     */
    private const TAG_CONTAIN = [
        'Tags' => ['sort' => ['Tags.name' => 'ASC', 'Tags.id' => 'ASC']],
    ];

    /**
     * Configure ownership and tags.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('records');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Tags', [
            'foreignKey' => 'record_id',
            'targetForeignKey' => 'tag_id',
            'joinTable' => 'records_tags',
            'dependent' => false,
        ]);
    }

    /**
     * Finder applying the authoritative Tag containment for serialized records.
     *
     * @param \Cake\ORM\Query\SelectQuery<\App\Model\Entity\Record> $query Query to decorate.
     * @return \Cake\ORM\Query\SelectQuery<\App\Model\Entity\Record>
     */
    public function findWithTags(SelectQuery $query): SelectQuery
    {
        return $query->contain(self::TAG_CONTAIN);
    }

    /**
     * Store an empty optional field as `NULL`, so "not provided" has one representation.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event beforeMarshal event.
     * @param \ArrayObject<string, mixed> $data Incoming request data.
     * @param \ArrayObject<string, mixed> $options Marshal options.
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        foreach (self::OPTIONAL_TEXT_FIELDS as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && trim($data[$field]) === '') {
                $data[$field] = null;
            }
        }
        foreach (['title', 'location'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
    }

    /**
     * Validate record text bounds and the closed kind set.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('kind')
            ->inList('kind', self::KINDS)
            ->notEmptyString('kind');

        $validator
            ->scalar('title')
            ->maxLength('title', self::MAX_TITLE_CHARS)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->requirePresence('occurred_at', 'create')
            ->dateTime('occurred_at')
            ->notEmptyDateTime('occurred_at');

        $validator
            ->scalar('participants')
            ->maxLength('participants', self::MAX_PARTICIPANTS_CHARS)
            ->allowEmptyString('participants');

        $validator
            ->scalar('location')
            ->maxLength('location', self::MAX_LOCATION_CHARS)
            ->allowEmptyString('location');

        $validator
            ->scalar('summary')
            ->maxLength('summary', self::MAX_SUMMARY_CHARS)
            ->allowEmptyString('summary');

        $validator
            ->scalar('details')
            ->add('details', 'maxBytes', [
                'rule' => static fn(mixed $value): bool => is_string($value)
                    && strlen($value) <= self::MAX_DETAILS_BYTES,
                'message' => 'The details exceed the 65536-byte limit.',
            ])
            ->allowEmptyString('details');

        return $validator;
    }

    /**
     * A location belongs only to an event.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add(
            static fn(EntityInterface $record): bool => $record->get('kind') === self::KIND_EVENT
                || $record->get('location') === null,
            'locationOnlyOnEvents',
            [
                'errorField' => 'location',
                'message' => 'A location is only valid on an event.',
            ],
        );

        return $rules;
    }
}
