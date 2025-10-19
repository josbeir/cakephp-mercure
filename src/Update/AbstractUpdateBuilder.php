<?php
declare(strict_types=1);

namespace Mercure\Update;

use InvalidArgumentException;

/**
 * Abstract Update Builder
 *
 * Base class for fluent builder classes that create Update instances.
 * Provides common functionality for managing topics and update metadata.
 *
 * @abstract
 */
abstract class AbstractUpdateBuilder
{
    /**
     * @var array<string>
     */
    protected array $topics = [];

    protected bool $private = false;

    protected ?string $id = null;

    protected ?string $type = null;

    protected ?int $retry = null;

    protected mixed $data = '';

    /**
     * Constructor
     *
     * @param array<string>|string|null $topics Optional topic(s) to publish to
     */
    public function __construct(array|string|null $topics = null)
    {
        if ($topics !== null) {
            $this->topics($topics);
        }
    }

    /**
     * Set the topic(s) to publish to
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @return $this
     */
    public function topics(array|string $topics)
    {
        $this->topics = is_array($topics) ? $topics : [$topics];

        return $this;
    }

    /**
     * Mark this update as private
     *
     * @param bool $private Whether this is a private update
     * @return $this
     */
    public function private(bool $private = true)
    {
        $this->private = $private;

        return $this;
    }

    /**
     * Set the event ID
     *
     * @param string|null $id Event ID
     * @return $this
     */
    public function id(?string $id)
    {
        if ($id !== null) {
            $this->id = $id;
        }

        return $this;
    }

    /**
     * Set the event type
     *
     * @param string|null $type Event type
     * @return $this
     */
    public function type(?string $type)
    {
        if ($type !== null) {
            $this->type = $type;
        }

        return $this;
    }

    /**
     * Set the retry delay
     *
     * @param int|null $retry Retry delay in milliseconds
     * @return $this
     */
    public function retry(?int $retry)
    {
        if ($retry !== null) {
            $this->retry = $retry;
        }

        return $this;
    }

    /**
     * Set the data for the update
     *
     * @param mixed $data Data for the update
     * @return $this
     */
    public function data(mixed $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Build and return the Update instance
     *
     * @throws \InvalidArgumentException
     */
    abstract public function build(): Update;

    /**
     * Validate the builder configuration
     *
     * This method validates common fields and can be extended by subclasses.
     *
     * @throws \InvalidArgumentException
     */
    protected function validate(): void
    {
        if ($this->topics === []) {
            throw new InvalidArgumentException('At least one topic must be specified');
        }
    }

    /**
     * Create the Update instance with common parameters
     *
     * Helper method for subclasses to create Update instances.
     *
     * @param string $data The rendered/encoded data
     */
    protected function createUpdate(string $data): Update
    {
        return new Update(
            topics: $this->topics,
            data: $data,
            private: $this->private,
            id: $this->id,
            type: $this->type,
            retry: $this->retry,
        );
    }
}
