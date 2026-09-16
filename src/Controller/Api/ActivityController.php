<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Entity\ActivityRecord;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use DomainException;

class ActivityController extends AppController
{
    /**
     * List the caller's own activity history, newest first.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        try {
            $query = $this->activity()->forActor($userId, [
                'subject_type' => $this->request->getQuery('subject_type'),
                'subject_id' => $this->request->getQuery('subject_id'),
                'action' => $this->request->getQuery('action'),
            ]);
        } catch (DomainException $exception) {
            throw new BadRequestException($exception->getMessage(), null, $exception);
        }

        $total = $query->count();
        $items = [];
        foreach ($query->limit($limit)->offset(($page - 1) * $limit) as $record) {
            $items[] = $this->serialize($record);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Read a positive integer query parameter, rejecting malformed values.
     */
    private function readPositiveQueryInt(string $field, int $default): int
    {
        $value = $this->request->getQuery($field);
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid query parameter `%s`.', $field));
    }

    /**
     * Serialize an activity record for API responses.
     *
     * @return array<string, mixed>
     */
    private function serialize(ActivityRecord $record): array
    {
        return [
            'id' => (int)$record->id,
            'action' => (string)$record->action,
            'subject_type' => (string)$record->subject_type,
            'subject_id' => (int)$record->subject_id,
            'context_type' => $record->context_type,
            'context_id' => $record->context_id === null ? null : (int)$record->context_id,
            'metadata' => $record->metadataArray(),
            'created' => $record->created?->format(DATE_ATOM),
        ];
    }
}
