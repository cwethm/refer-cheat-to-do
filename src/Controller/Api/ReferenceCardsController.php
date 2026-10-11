<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\ReferenceCard;
use App\Model\Entity\Tag;
use App\Model\Table\ReferenceCardsTable;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Owner-only reference cards: concepts and dialogue patterns (Slice 26).
 */
class ReferenceCardsController extends AppController
{
    /**
     * Longest accepted search text, so a search stays a bounded, escaped substring match.
     */
    private const MAX_SEARCH_CHARS = 255;

    /**
     * Columns matched by `q`.
     *
     * @var list<string>
     */
    private const SEARCH_FIELDS = [
        'title',
        'summary',
        'situation',
        'aim',
        'approach',
        'avoid',
        'rationale',
        'body',
    ];

    /**
     * Search and filter the caller's cards.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $search = $this->readSearchFilter();
        $cardType = $this->readCardTypeFilter();
        $tagId = $this->readPositiveIdFilter('tag');
        $sheetId = $this->readPositiveIdFilter('cheat_sheet');
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        /** @var \App\Model\Table\ReferenceCardsTable $cards */
        $cards = $this->fetchTable('ReferenceCards');
        $query = $cards->find('withTags')->where(['ReferenceCards.user_id' => $userId]);
        if ($search !== null) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $conditions = [];
            foreach (self::SEARCH_FIELDS as $field) {
                $conditions[] = ['ReferenceCards.' . $field . ' ILIKE' => $like];
            }
            $query->where(['OR' => $conditions]);
        }
        if ($cardType !== null) {
            $query->where(['ReferenceCards.card_type' => $cardType]);
        }
        if ($tagId !== null) {
            $query->matching('Tags', function ($q) use ($tagId, $userId) {
                return $q->where(['Tags.id' => $tagId, 'Tags.user_id' => $userId]);
            });
        }
        if ($sheetId !== null) {
            $query->innerJoinWith('CheatSheetsReferenceCards.CheatSheets', function ($q) use ($sheetId, $userId) {
                return $q->where(['CheatSheets.id' => $sheetId, 'CheatSheets.user_id' => $userId]);
            });
        }

        $total = (clone $query)->count();
        $items = [];
        foreach (
            $query->orderBy(['ReferenceCards.title' => 'ASC', 'ReferenceCards.id' => 'ASC'])
                ->limit($limit)
                ->offset(($page - 1) * $limit)
                ->all() as $card
        ) {
            /** @var \App\Model\Entity\ReferenceCard $card */
            $items[] = $card->toApiArray();
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a card owned by the authenticated user.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->readBody() + ['card_type' => ReferenceCardsTable::TYPE_CONCEPT];
        /** @var \App\Model\Table\ReferenceCardsTable $cards */
        $cards = $this->fetchTable('ReferenceCards');
        /** @var \App\Model\Entity\ReferenceCard $card */
        $card = $cards->newEntity($data, ['fields' => ReferenceCardsTable::WRITABLE_FIELDS]);
        $card->set('user_id', $userId);
        if ($card->hasErrors() || !$cards->save($card)) {
            throw new ValidationException('Invalid reference card payload.');
        }

        $this->activity()->record($userId, 'reference_card.created', 'reference_card', (int)$card->id);

        $created = $this->fetchOwnedCardOrFail((string)$card->id, $userId);

        return $this->respond(['card' => $created->toApiArray()], [], 201);
    }

    /**
     * Read one of the caller's cards.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();

        return $this->respond(['card' => $this->fetchOwnedCardOrFail($id, $userId)->toApiArray()]);
    }

    /**
     * Update one of the caller's cards.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $card = $this->fetchOwnedCardOrFail($id, $userId);
        $data = $this->readBody();
        /** @var \App\Model\Table\ReferenceCardsTable $cards */
        $cards = $this->fetchTable('ReferenceCards');
        $card = $cards->patchEntity($card, $data, ['fields' => ReferenceCardsTable::WRITABLE_FIELDS]);
        if ($card->hasErrors() || !$cards->save($card)) {
            throw new ValidationException('Invalid reference card payload.');
        }

        $this->activity()->record(
            $userId,
            'reference_card.updated',
            'reference_card',
            (int)$card->id,
            ['fields' => array_values(array_intersect(array_keys($data), ReferenceCardsTable::WRITABLE_FIELDS))],
        );

        return $this->respond(['card' => $this->fetchOwnedCardOrFail($id, $userId)->toApiArray()]);
    }

    /**
     * Permanently delete a card, its tag joins and its sheet memberships, never a sheet or tag.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $card = $this->fetchOwnedCardOrFail($id, $userId);
        $cardId = (int)$card->id;

        /** @var \App\Model\Table\ReferenceCardsTable $cards */
        $cards = $this->fetchTable('ReferenceCards');
        /** @var \App\Model\Table\CheatSheetsReferenceCardsTable $memberships */
        $memberships = $this->fetchTable('CheatSheetsReferenceCards');
        $cards->getConnection()->transactional(function () use (
            $cards,
            $memberships,
            $card,
            $cardId,
        ): void {
            $cards->getConnection()->execute(
                'SELECT id FROM reference_cards WHERE id = ? FOR UPDATE',
                [$cardId],
            );
            $sheetIds = $memberships->find()
                ->select(['cheat_sheet_id'])
                ->where(['reference_card_id' => $cardId])
                ->all()
                ->extract('cheat_sheet_id')
                ->toList();
            if (!$cards->delete($card)) {
                throw new InternalErrorException('Unable to delete reference card.');
            }
            foreach ($sheetIds as $sheetId) {
                $memberships->renumberPositions((int)$sheetId);
            }
        });

        $this->activity()->record($userId, 'reference_card.deleted', 'reference_card', $cardId);

        return $this->respond(['message' => 'Reference card deleted.']);
    }

    /**
     * Attach one of the caller's tags to one of the caller's cards.
     */
    public function attachTag(string $id, string $tagId): Response
    {
        $userId = $this->requireUserId();
        $card = $this->fetchOwnedCardOrFail($id, $userId);
        $tag = $this->fetchOwnedTagOrFail($tagId, $userId);

        $inserted = $this->fetchTable('ReferenceCardsTags')->getConnection()->execute(
            'INSERT INTO reference_cards_tags (reference_card_id, tag_id) VALUES (?, ?) ON CONFLICT DO NOTHING',
            [(int)$card->id, (int)$tag->id],
        )->rowCount();
        if ($inserted < 1) {
            throw new ConflictException('Tag is already attached to this reference card.');
        }

        $this->activity()->record($userId, 'reference_card.tag_attached', 'reference_card', (int)$card->id, [
            'tag_id' => (int)$tag->id,
        ]);

        return $this->respond([
            'reference_card_id' => (int)$card->id,
            'tag_id' => (int)$tag->id,
            'message' => 'Tag attached.',
        ], [], 201);
    }

    /**
     * Detach one of the caller's tags from one of the caller's cards.
     */
    public function detachTag(string $id, string $tagId): Response
    {
        $userId = $this->requireUserId();
        $card = $this->fetchOwnedCardOrFail($id, $userId);
        $tag = $this->fetchOwnedTagOrFail($tagId, $userId);

        $deleted = $this->fetchTable('ReferenceCardsTags')->deleteAll([
            'reference_card_id' => (int)$card->id,
            'tag_id' => (int)$tag->id,
        ]);
        if ($deleted < 1) {
            throw new NotFoundException('Tag attachment not found.');
        }

        $this->activity()->record($userId, 'reference_card.tag_detached', 'reference_card', (int)$card->id, [
            'tag_id' => (int)$tag->id,
        ]);

        return $this->respond([
            'reference_card_id' => (int)$card->id,
            'tag_id' => (int)$tag->id,
            'message' => 'Tag detached.',
        ]);
    }

    /**
     * Resolve a card owned by the caller; another user's card and a missing one are both `404`.
     */
    private function fetchOwnedCardOrFail(string $id, int $userId): ReferenceCard
    {
        if (!ctype_digit($id) || (int)$id < 1 || (int)$id > 2147483647) {
            throw new NotFoundException('Reference card not found.');
        }
        /** @var \App\Model\Table\ReferenceCardsTable $cards */
        $cards = $this->fetchTable('ReferenceCards');
        /** @var \App\Model\Entity\ReferenceCard|null $card */
        $card = $cards->find('withTags')
            ->where(['ReferenceCards.id' => (int)$id, 'ReferenceCards.user_id' => $userId])
            ->first();
        if ($card === null) {
            throw new NotFoundException('Reference card not found.');
        }

        return $card;
    }

    /**
     * Resolve a tag owned by the caller.
     */
    private function fetchOwnedTagOrFail(string $id, int $userId): Tag
    {
        if (!ctype_digit($id) || (int)$id < 1 || (int)$id > 2147483647) {
            throw new NotFoundException('Tag not found.');
        }
        /** @var \App\Model\Entity\Tag|null $tag */
        $tag = $this->fetchTable('Tags')->find()
            ->where(['id' => (int)$id, 'user_id' => $userId])
            ->first();
        if ($tag === null) {
            throw new NotFoundException('Tag not found.');
        }

        return $tag;
    }

    /**
     * Resolve the optional search text: empty or whitespace applies no filter; longer than the
     * bound is refused rather than truncated.
     */
    private function readSearchFilter(): ?string
    {
        $query = $this->request->getQuery('q');
        if ($query === null || $query === '') {
            return null;
        }
        if (!is_string($query)) {
            throw new BadRequestException('Invalid search query.');
        }
        $query = trim($query);
        if ($query === '') {
            return null;
        }
        if (mb_strlen($query) > self::MAX_SEARCH_CHARS) {
            throw new BadRequestException('Search query is too long.');
        }

        return $query;
    }

    /**
     * Resolve the optional card type filter against the closed set.
     */
    private function readCardTypeFilter(): ?string
    {
        $type = $this->request->getQuery('card_type');
        if ($type === null || $type === '') {
            return null;
        }
        if (!is_string($type) || !in_array($type, ReferenceCardsTable::CARD_TYPES, true)) {
            throw new BadRequestException('Invalid card_type filter.');
        }

        return $type;
    }

    /**
     * Resolve an optional positive id filter.
     */
    private function readPositiveIdFilter(string $field): ?int
    {
        $value = $this->request->getQuery($field);
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0 && (int)$value <= 2147483647) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid %s filter.', $field));
    }

    /**
     * Parse a positive pagination query parameter.
     */
    private function readPositiveQueryInt(string $field, int $default): int
    {
        $max = intdiv(PHP_INT_MAX, 100);
        $value = $this->request->getQuery($field, $default);
        if (is_int($value) && $value > 0 && $value <= $max) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && strlen($value) <= strlen((string)$max)) {
            $parsed = (int)$value;
            if ($parsed > 0 && $parsed <= $max) {
                return $parsed;
            }
        }

        throw new BadRequestException(sprintf('Invalid query parameter `%s`.', $field));
    }

    /**
     * Read a JSON object request body.
     *
     * @return array<string, mixed>
     */
    private function readBody(): array
    {
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        return $data;
    }
}
