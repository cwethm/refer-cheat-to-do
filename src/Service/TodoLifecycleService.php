<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Todo;
use App\Model\Table\TodosTable;
use Cake\I18n\DateTime;
use DomainException;

/**
 * Single authority for ToDo lifecycle transitions.
 *
 * Controllers must not re-implement transition rules; they translate the exceptions raised here
 * into API responses.
 */
class TodoLifecycleService
{
    public const STATUS_INBOX = 'inbox';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DONE = 'done';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_TRASHED = 'trashed';

    /**
     * Statuses hidden from default listings.
     *
     * @var list<string>
     */
    public const HIDDEN_BY_DEFAULT = [self::STATUS_ARCHIVED, self::STATUS_TRASHED];

    /**
     * Allowed target statuses for each current status.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_INBOX => [
            self::STATUS_ACTIVE,
            self::STATUS_DONE,
            self::STATUS_ARCHIVED,
            self::STATUS_TRASHED,
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_DONE,
            self::STATUS_ARCHIVED,
            self::STATUS_TRASHED,
        ],
        self::STATUS_DONE => [
            self::STATUS_ACTIVE,
            self::STATUS_ARCHIVED,
            self::STATUS_TRASHED,
        ],
        self::STATUS_ARCHIVED => [
            self::STATUS_ACTIVE,
            self::STATUS_TRASHED,
        ],
        self::STATUS_TRASHED => [],
    ];

    /**
     * @param \App\Model\Table\TodosTable $todos ToDo table used for persistence.
     */
    public function __construct(private TodosTable $todos)
    {
    }

    /**
     * Whether a direct transition between two statuses is allowed.
     */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Apply an explicit lifecycle transition and persist it.
     *
     * @throws \DomainException When the transition is not allowed from the current status.
     */
    public function transition(Todo $todo, string $to): Todo
    {
        $from = (string)$todo->status;
        if ($from === $to) {
            throw new DomainException(sprintf('ToDo is already `%s`.', $to));
        }
        if (!$this->canTransition($from, $to)) {
            throw new DomainException(sprintf('Cannot move a `%s` ToDo to `%s`.', $from, $to));
        }

        $todo->set('previous_status', $to === self::STATUS_TRASHED ? $from : null);
        $todo->set('status', $to);
        $todo->set('archived_at', $to === self::STATUS_ARCHIVED ? DateTime::now() : null);
        $todo->set('trashed_at', $to === self::STATUS_TRASHED ? DateTime::now() : null);

        return $this->todos->saveOrFail($todo);
    }

    /**
     * Restore a trashed ToDo to the status it held before it was trashed.
     *
     * @throws \DomainException When the ToDo is not currently trashed.
     */
    public function restore(Todo $todo): Todo
    {
        if ((string)$todo->status !== self::STATUS_TRASHED) {
            throw new DomainException('Only trashed ToDos can be restored.');
        }

        $previous = (string)$todo->previous_status;
        if (!array_key_exists($previous, self::TRANSITIONS) || $previous === self::STATUS_TRASHED) {
            $previous = self::STATUS_INBOX;
        }

        $todo->set('status', $previous);
        $todo->set('previous_status', null);
        $todo->set('trashed_at', null);
        $todo->set('archived_at', $previous === self::STATUS_ARCHIVED ? DateTime::now() : null);

        return $this->todos->saveOrFail($todo);
    }

    /**
     * Permanently delete a trashed ToDo.
     *
     * @throws \DomainException When the ToDo has not been trashed first.
     */
    public function permanentlyDelete(Todo $todo): void
    {
        if ((string)$todo->status !== self::STATUS_TRASHED) {
            throw new DomainException('Only trashed ToDos can be permanently deleted.');
        }

        $this->todos->deleteOrFail($todo);
    }
}
