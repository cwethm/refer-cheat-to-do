<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\DateTime;
use Cake\ORM\Entity;
use DateTimeZone;

/**
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property string $title
 * @property \Cake\I18n\DateTime $occurred_at
 * @property string|null $participants
 * @property string|null $location
 * @property string|null $summary
 * @property string|null $details
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property array<\App\Model\Entity\Tag>|null $tags
 */
class Record extends Entity
{
    /**
     * Ownership is assigned from the session and never mass-assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => false,
        'kind' => true,
        'title' => true,
        'occurred_at' => true,
        'participants' => true,
        'location' => true,
        'summary' => true,
        'details' => true,
        'created' => false,
        'modified' => false,
    ];

    /**
     * Plain API representation: text fields as stored, times in UTC, tags as id and name.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $tags = [];
        foreach ($this->tags ?? [] as $tag) {
            $tags[] = ['id' => (int)$tag->id, 'name' => (string)$tag->name];
        }
        $utc = new DateTimeZone('UTC');
        $occurredAt = $this->occurred_at instanceof DateTime
            ? $this->occurred_at->setTimezone($utc)->format(DATE_ATOM)
            : null;

        return [
            'id' => (int)$this->id,
            'kind' => (string)$this->kind,
            'title' => (string)$this->title,
            'occurred_at' => $occurredAt,
            'participants' => $this->participants,
            'location' => $this->location,
            'summary' => $this->summary,
            'details' => $this->details,
            'tags' => $tags,
            'created' => $this->created?->format(DATE_ATOM),
            'modified' => $this->modified?->format(DATE_ATOM),
        ];
    }
}
