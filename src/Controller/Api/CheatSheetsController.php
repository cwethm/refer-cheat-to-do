<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\CheatSheet;
use App\Model\Entity\CheatSheetsReferenceCard;
use App\Model\Entity\ReferenceCard;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;

/**
 * Owner-only cheat sheets: ordered selections of the caller's reference cards (Slice 26, ADR 0004).
 */
class CheatSheetsController extends AppController
{
    /**
     * List the caller's cheat sheets.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        $query = $this->fetchTable('CheatSheets')->find()->where(['CheatSheets.user_id' => $userId]);
        $total = (clone $query)->count();
        $counts = [];
        $items = [];
        $sheets = $query->orderBy(['CheatSheets.name' => 'ASC', 'CheatSheets.id' => 'ASC'])
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->all()
            ->toList();
        $sheetIds = array_map(static fn($sheet) => (int)$sheet->get('id'), $sheets);
        if ($sheetIds !== []) {
            $rows = $this->fetchTable('CheatSheetsReferenceCards')->find()
                ->select(['cheat_sheet_id', 'card_count' => 'COUNT(*)'])
                ->where(['cheat_sheet_id IN' => $sheetIds])
                ->groupBy(['cheat_sheet_id'])
                ->disableHydration()
                ->all();
            foreach ($rows as $row) {
                $counts[(int)$row['cheat_sheet_id']] = (int)$row['card_count'];
            }
        }
        foreach ($sheets as $sheet) {
            /** @var \App\Model\Entity\CheatSheet $sheet */
            $items[] = $this->serializeSheet($sheet, $counts[(int)$sheet->id] ?? 0);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a cheat sheet owned by the authenticated user.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $sheets = $this->fetchTable('CheatSheets');
        /** @var \App\Model\Entity\CheatSheet $sheet */
        $sheet = $sheets->newEntity($this->readBody(), ['fields' => ['name', 'description']]);
        $sheet->set('user_id', $userId);
        if ($sheet->hasErrors() || !$sheets->save($sheet)) {
            throw new ValidationException('Invalid cheat sheet payload.');
        }

        $this->activity()->record($userId, 'cheat_sheet.created', 'cheat_sheet', (int)$sheet->id);

        return $this->respond(['cheat_sheet' => $this->serializeSheet($sheet, 0)], [], 201);
    }

    /**
     * Read one of the caller's cheat sheets.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();
        $sheet = $this->fetchOwnedSheetOrFail($id, $userId);

        return $this->respond(['cheat_sheet' => $this->serializeSheet($sheet, $this->cardCount((int)$sheet->id))]);
    }

    /**
     * Rename or redescribe one of the caller's cheat sheets.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $sheet = $this->fetchOwnedSheetOrFail($id, $userId);
        $data = $this->readBody();
        $sheets = $this->fetchTable('CheatSheets');
        $sheet = $sheets->patchEntity($sheet, $data, ['fields' => ['name', 'description']]);
        if ($sheet->hasErrors() || !$sheets->save($sheet)) {
            throw new ValidationException('Invalid cheat sheet payload.');
        }

        $this->activity()->record(
            $userId,
            'cheat_sheet.updated',
            'cheat_sheet',
            (int)$sheet->id,
            ['fields' => array_values(array_intersect(array_keys($data), ['name', 'description']))],
        );

        return $this->respond(['cheat_sheet' => $this->serializeSheet($sheet, $this->cardCount((int)$sheet->id))]);
    }

    /**
     * Delete a cheat sheet and its memberships; the cards themselves are untouched.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $sheet = $this->fetchOwnedSheetOrFail($id, $userId);
        if (!$this->fetchTable('CheatSheets')->delete($sheet)) {
            throw new InternalErrorException('Unable to delete cheat sheet.');
        }

        $this->activity()->record($userId, 'cheat_sheet.deleted', 'cheat_sheet', (int)$sheet->id);

        return $this->respond(['message' => 'Cheat sheet deleted.']);
    }

    /**
     * List a sheet's cards in sheet order.
     */
    public function cards(string $id): Response
    {
        $userId = $this->requireUserId();
        $sheet = $this->fetchOwnedSheetOrFail($id, $userId);
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        $query = $this->fetchTable('CheatSheetsReferenceCards')->find()
            ->contain(['ReferenceCards' => ['Tags' => ['sort' => ['Tags.name' => 'ASC', 'Tags.id' => 'ASC']]]])
            ->where(['CheatSheetsReferenceCards.cheat_sheet_id' => (int)$sheet->id]);
        $total = (clone $query)->count();
        $items = [];
        foreach (
            $query->orderBy([
                'CheatSheetsReferenceCards.position' => 'ASC',
                'CheatSheetsReferenceCards.reference_card_id' => 'ASC',
            ])
                ->limit($limit)
                ->offset(($page - 1) * $limit)
                ->all() as $membership
        ) {
            /** @var \App\Model\Entity\CheatSheetsReferenceCard $membership */
            $items[] = $this->serializeMembership($membership);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Add one of the caller's cards to one of the caller's sheets, appended or at a position.
     */
    public function addCard(string $id): Response
    {
        $userId = $this->requireUserId();
        $sheet = $this->fetchOwnedSheetOrFail($id, $userId);
        $data = $this->readBody();
        $cardId = $this->readOwnedCardId($data['reference_card_id'] ?? null, $userId);
        $position = array_key_exists('position', $data) ? $this->readPosition($data['position']) : null;
        $sheetId = (int)$sheet->id;

        /** @var \App\Model\Table\CheatSheetsReferenceCardsTable $memberships */
        $memberships = $this->fetchTable('CheatSheetsReferenceCards');
        $memberships->getConnection()->transactional(
            function () use ($memberships, $sheetId, $cardId, $position): void {
                $next = $memberships->nextPosition($sheetId);
                $inserted = $memberships->getConnection()->execute(
                    'INSERT INTO cheat_sheets_reference_cards (cheat_sheet_id, reference_card_id, position, created) '
                    . 'VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON CONFLICT DO NOTHING',
                    [$sheetId, $cardId, $next],
                )->rowCount();
                if ($inserted < 1) {
                    throw new ConflictException('Card is already on this cheat sheet.');
                }
                if ($position !== null && $position !== $next) {
                    try {
                        $memberships->moveToPosition($this->fetchMembershipOrFail($sheetId, $cardId), $position);
                    } catch (InvalidArgumentException $exception) {
                        throw new BadRequestException($exception->getMessage(), null, $exception);
                    }
                }
            },
        );

        $this->activity()->record($userId, 'cheat_sheet.card_added', 'cheat_sheet', $sheetId, [
            'reference_card_id' => $cardId,
        ]);

        return $this->respond(
            ['membership' => $this->serializeMembership($this->fetchMembershipOrFail($sheetId, $cardId, true))],
            [],
            201,
        );
    }

    /**
     * Move a card to an explicit 1-based position on the sheet.
     */
    public function moveCard(string $id, string $cardId): Response
    {
        $userId = $this->requireUserId();
        $sheet = $this->fetchOwnedSheetOrFail($id, $userId);
        $sheetId = (int)$sheet->id;
        $membership = $this->fetchMembershipOrFail($sheetId, $this->parseRouteId($cardId, 'Card'));
        $data = $this->readBody();
        if (!array_key_exists('position', $data)) {
            throw new BadRequestException('A position is required.');
        }
        $position = $this->readPosition($data['position']);

        /** @var \App\Model\Table\CheatSheetsReferenceCardsTable $memberships */
        $memberships = $this->fetchTable('CheatSheetsReferenceCards');
        try {
            $memberships->moveToPosition($membership, $position);
        } catch (InvalidArgumentException $exception) {
            throw new BadRequestException($exception->getMessage(), null, $exception);
        }
        $resolvedCardId = (int)$membership->reference_card_id;

        $this->activity()->record($userId, 'cheat_sheet.card_moved', 'cheat_sheet', $sheetId, [
            'reference_card_id' => $resolvedCardId,
        ]);

        return $this->respond([
            'membership' => $this->serializeMembership($this->fetchMembershipOrFail($sheetId, $resolvedCardId, true)),
        ]);
    }

    /**
     * Remove a card from the sheet; the card itself is untouched.
     */
    public function removeCard(string $id, string $cardId): Response
    {
        $userId = $this->requireUserId();
        $sheet = $this->fetchOwnedSheetOrFail($id, $userId);
        $sheetId = (int)$sheet->id;
        $membership = $this->fetchMembershipOrFail($sheetId, $this->parseRouteId($cardId, 'Card'));
        $resolvedCardId = (int)$membership->reference_card_id;

        /** @var \App\Model\Table\CheatSheetsReferenceCardsTable $memberships */
        $memberships = $this->fetchTable('CheatSheetsReferenceCards');
        $memberships->getConnection()->transactional(function () use ($memberships, $membership, $sheetId): void {
            if (!$memberships->delete($membership)) {
                throw new InternalErrorException('Unable to remove card from cheat sheet.');
            }
            $memberships->renumberPositions($sheetId);
        });

        $this->activity()->record($userId, 'cheat_sheet.card_removed', 'cheat_sheet', $sheetId, [
            'reference_card_id' => $resolvedCardId,
        ]);

        return $this->respond(['message' => 'Card removed from cheat sheet.']);
    }

    /**
     * Resolve a cheat sheet owned by the caller; another user's sheet and a missing one are both `404`.
     */
    private function fetchOwnedSheetOrFail(string $id, int $userId): CheatSheet
    {
        $sheetId = $this->parseRouteId($id, 'Cheat sheet');
        /** @var \App\Model\Entity\CheatSheet|null $sheet */
        $sheet = $this->fetchTable('CheatSheets')->find()
            ->where(['CheatSheets.id' => $sheetId, 'CheatSheets.user_id' => $userId])
            ->first();
        if ($sheet === null) {
            throw new NotFoundException('Cheat sheet not found.');
        }

        return $sheet;
    }

    /**
     * Resolve a membership of an already-authorized sheet.
     */
    private function fetchMembershipOrFail(int $sheetId, int $cardId, bool $withCard = false): CheatSheetsReferenceCard
    {
        $query = $this->fetchTable('CheatSheetsReferenceCards')->find()
            ->where([
                'CheatSheetsReferenceCards.cheat_sheet_id' => $sheetId,
                'CheatSheetsReferenceCards.reference_card_id' => $cardId,
            ]);
        if ($withCard) {
            $query->contain(['ReferenceCards' => ['Tags' => ['sort' => ['Tags.name' => 'ASC', 'Tags.id' => 'ASC']]]]);
        }
        /** @var \App\Model\Entity\CheatSheetsReferenceCard|null $membership */
        $membership = $query->first();
        if ($membership === null) {
            throw new NotFoundException('Card is not on this cheat sheet.');
        }

        return $membership;
    }

    /**
     * Resolve a requested card id that must belong to the caller; anything else is `404`.
     */
    private function readOwnedCardId(mixed $value, int $userId): int
    {
        if (is_int($value)) {
            $value = (string)$value;
        }
        if (!is_string($value)) {
            throw new ValidationException('A reference_card_id is required.');
        }
        $cardId = $this->parseRouteId($value, 'Reference card');
        if (!$this->fetchTable('ReferenceCards')->exists(['id' => $cardId, 'user_id' => $userId])) {
            throw new NotFoundException('Reference card not found.');
        }

        return $cardId;
    }

    /**
     * Parse a requested 1-based position.
     */
    private function readPosition(mixed $position): int
    {
        if (is_string($position) && ctype_digit($position) && strlen($position) <= 9) {
            $position = (int)$position;
        }
        if (!is_int($position)) {
            throw new BadRequestException('Invalid card position.');
        }

        return $position;
    }

    /**
     * Parse a database id, treating anything that cannot be one as not found.
     */
    private function parseRouteId(string $id, string $label): int
    {
        if (!ctype_digit($id) || strlen($id) > 10 || (int)$id < 1 || (int)$id > 2147483647) {
            throw new NotFoundException(sprintf('%s not found.', $label));
        }

        return (int)$id;
    }

    /**
     * Count the cards on a sheet.
     */
    private function cardCount(int $sheetId): int
    {
        return $this->fetchTable('CheatSheetsReferenceCards')->find()
            ->where(['cheat_sheet_id' => $sheetId])
            ->count();
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

    /**
     * Serialize a cheat sheet.
     *
     * @return array<string, mixed>
     */
    private function serializeSheet(CheatSheet $sheet, int $cardCount): array
    {
        return [
            'id' => (int)$sheet->id,
            'name' => (string)$sheet->name,
            'description' => $sheet->description === null ? null : (string)$sheet->description,
            'card_count' => $cardCount,
            'created' => $sheet->created?->format(DATE_ATOM),
            'modified' => $sheet->modified?->format(DATE_ATOM),
        ];
    }

    /**
     * Serialize a membership with its card.
     *
     * @return array<string, mixed>
     */
    private function serializeMembership(CheatSheetsReferenceCard $membership): array
    {
        $card = $membership->get('reference_card');

        return [
            'cheat_sheet_id' => (int)$membership->cheat_sheet_id,
            'reference_card_id' => (int)$membership->reference_card_id,
            'position' => (int)$membership->position,
            'card' => $card instanceof ReferenceCard ? $card->toApiArray() : null,
        ];
    }
}
