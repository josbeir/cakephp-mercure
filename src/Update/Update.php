<?php
declare(strict_types=1);

namespace Mercure\Update;

use InvalidArgumentException;

/**
 * Represents a Mercure update to be published
 *
 * This value object encapsulates all the data needed to publish
 * an update to a Mercure hub.
 */
class Update
{
    /**
     * @var array<string>
     */
    private array $topics;

    /**
     * Constructor
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param string $data Data to publish (typically JSON)
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string|array $topics,
        private string $data,
        private bool $private = false,
        private ?string $id = null,
        private ?string $type = null,
        private ?int $retry = null,
    ) {
        $this->topics = is_array($topics) ? $topics : [$topics];
        $this->validate();
    }

    /**
     * Validate the update data
     *
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->topics === []) {
            throw new InvalidArgumentException('At least one topic must be provided');
        }

        foreach ($this->topics as $topic) {
            if (empty($topic) || !is_string($topic)) {
                throw new InvalidArgumentException('All topics must be non-empty strings');
            }
        }

        if ($this->retry !== null && $this->retry < 0) {
            throw new InvalidArgumentException('Retry value must be a positive integer');
        }
    }

    /**
     * Get topics
     *
     * @return array<string>
     */
    public function getTopics(): array
    {
        return $this->topics;
    }

    /**
     * Get data
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Check if update is private
     */
    public function isPrivate(): bool
    {
        return $this->private;
    }

    /**
     * Get event ID
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Get event type
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Get retry delay
     */
    public function getRetry(): ?int
    {
        return $this->retry;
    }

    /**
     * Convert to array suitable for HTTP form data
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'topic' => $this->topics,
            'data' => $this->data,
        ];

        if ($this->private) {
            $data['private'] = 'on';
        }

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }

        if ($this->retry !== null) {
            $data['retry'] = (string)$this->retry;
        }

        return $data;
    }
}
