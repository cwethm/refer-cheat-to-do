<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $user_id
 * @property string $card_type
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $situation
 * @property string|null $aim
 * @property string|null $approach
 * @property string|null $avoid
 * @property string|null $rationale
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property array<\App\Model\Entity\Tag>|null $tags
 */
class ReferenceCard extends Entity
{
    /**
     * Ownership is assigned from the session and never mass-assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => false,
        'card_type' => true,
        'title' => true,
        'summary' => true,
        'body' => true,
        'situation' => true,
        'aim' => true,
        'approach' => true,
        'avoid' => true,
        'rationale' => true,
        'created' => false,
        'modified' => false,
    ];

    /**
     * Plain API representation: text fields as stored, tags as id and name.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $tags = [];
        foreach ($this->tags ?? [] as $tag) {
            $tags[] = ['id' => (int)$tag->id, 'name' => (string)$tag->name];
        }

        return [
            'id' => (int)$this->id,
            'card_type' => (string)$this->card_type,
            'title' => (string)$this->title,
            'summary' => $this->summary,
            'body' => $this->body,
            'situation' => $this->situation,
            'aim' => $this->aim,
            'approach' => $this->approach,
            'avoid' => $this->avoid,
            'rationale' => $this->rationale,
            'tags' => $tags,
            'created' => $this->created?->format(DATE_ATOM),
            'modified' => $this->modified?->format(DATE_ATOM),
        ];
    }
}
