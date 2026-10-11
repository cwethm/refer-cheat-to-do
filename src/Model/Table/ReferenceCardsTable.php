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

class ReferenceCardsTable extends Table
{
    public const TYPE_CONCEPT = 'concept';
    public const TYPE_DIALOGUE_PATTERN = 'dialogue_pattern';

    /**
     * Closed card type set, mirrored by `reference_cards_card_type_chk`.
     *
     * @var list<string>
     */
    public const CARD_TYPES = [self::TYPE_CONCEPT, self::TYPE_DIALOGUE_PATTERN];

    /**
     * Optional structured fields valid only on dialogue-pattern cards.
     *
     * @var list<string>
     */
    public const DIALOGUE_FIELDS = ['situation', 'aim', 'approach', 'avoid', 'rationale'];

    /**
     * Every client-writable card field.
     *
     * @var list<string>
     */
    public const WRITABLE_FIELDS = [
        'card_type',
        'title',
        'summary',
        'body',
        'situation',
        'aim',
        'approach',
        'avoid',
        'rationale',
    ];

    public const MAX_BODY_BYTES = 65536;
    public const MAX_SUMMARY_CHARS = 500;
    public const MAX_DIALOGUE_FIELD_CHARS = 2000;

    /**
     * Authoritative containment used whenever a card is serialized with its Tags.
     *
     * @var array<string, mixed>
     */
    private const TAG_CONTAIN = [
        'Tags' => ['sort' => ['Tags.name' => 'ASC', 'Tags.id' => 'ASC']],
    ];

    /**
     * Configure ownership, tags and cheat-sheet memberships.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('reference_cards');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Tags', [
            'foreignKey' => 'reference_card_id',
            'targetForeignKey' => 'tag_id',
            'joinTable' => 'reference_cards_tags',
            'dependent' => false,
        ]);
        $this->hasMany('CheatSheetsReferenceCards', [
            'foreignKey' => 'reference_card_id',
            'dependent' => false,
        ]);
    }

    /**
     * Finder applying the authoritative Tag containment for serialized cards.
     *
     * @param \Cake\ORM\Query\SelectQuery<\App\Model\Entity\ReferenceCard> $query Query to decorate.
     * @return \Cake\ORM\Query\SelectQuery<\App\Model\Entity\ReferenceCard>
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
        foreach (array_merge(['summary', 'body'], self::DIALOGUE_FIELDS) as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && trim($data[$field]) === '') {
                $data[$field] = null;
            }
        }
        if (isset($data['title']) && is_string($data['title'])) {
            $data['title'] = trim($data['title']);
        }
    }

    /**
     * Validate card text bounds and the closed type set.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('card_type')
            ->inList('card_type', self::CARD_TYPES)
            ->notEmptyString('card_type');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->scalar('summary')
            ->maxLength('summary', self::MAX_SUMMARY_CHARS)
            ->allowEmptyString('summary');

        $validator
            ->scalar('body')
            ->add('body', 'maxBytes', [
                'rule' => static fn(mixed $value): bool => is_string($value)
                    && strlen($value) <= self::MAX_BODY_BYTES,
                'message' => 'The body exceeds the 65536-byte limit.',
            ])
            ->allowEmptyString('body');

        foreach (self::DIALOGUE_FIELDS as $field) {
            $validator
                ->scalar($field)
                ->maxLength($field, self::MAX_DIALOGUE_FIELD_CHARS)
                ->allowEmptyString($field);
        }

        return $validator;
    }

    /**
     * Keep dialogue-pattern fields meaningful: they are valid only on dialogue-pattern cards.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add(
            static function (EntityInterface $card): bool {
                if ($card->get('card_type') === self::TYPE_DIALOGUE_PATTERN) {
                    return true;
                }
                foreach (self::DIALOGUE_FIELDS as $field) {
                    if ($card->get($field) !== null) {
                        return false;
                    }
                }

                return true;
            },
            'dialogueFieldsOnlyOnDialoguePatterns',
            [
                'errorField' => 'card_type',
                'message' => 'Dialogue-pattern fields are only valid on dialogue_pattern cards.',
            ],
        );

        return $rules;
    }
}
