<?php
declare(strict_types=1);

namespace Mercure\Update;

use InvalidArgumentException;

/**
 * Abstract Update Builder
 *
 * Base class for fluent builder classes that create Update instances.
 * Provides common functionality for managing topics, update metadata, and building updates.
 *
 * Subclasses should:
 * - Implement the build() method to create Update instances
 * - Optionally override validate() to add specialized validation
 * - Define their own static create() methods with appropriate signatures
 */
abstract class AbstractUpdateBuilder
{
    /**
     * Constructor
     *
     * @param array<string>|string $topics Optional topic(s) to publish to
     * @param bool $private Whether this is a private update
     * @param string|null $id Event ID
     * @param string|null $type Event type
     * @param int|null $retry Retry count
     */
    public function __construct(
        protected array|string $topics = [],
        protected bool $private = false,
        protected ?string $id = null,
        protected ?string $type = null,
        protected ?int $retry = null,
    ) {
    }

    /**
     * Set the topic(s) to publish to
     *
     * @param array<string>|string $topics Topic(s) to publish to
     */
    public function topics(array|string $topics): static
    {
        $this->topics = is_array($topics) ? $topics : [$topics];

        return $this;
    }

    /**
     * Mark this update as private
     *
     * @param bool $private Whether this is a private update
     */
    public function private(bool $private = true): static
    {
        $this->private = $private;

        return $this;
    }

    /**
     * Set the event ID
     *
     * @param string|null $id Event ID
     */
    public function id(?string $id): static
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
     */
    public function type(?string $type): static
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
     */
    public function retry(?int $retry): static
    {
        if ($retry !== null) {
            $this->retry = $retry;
        }

        return $this;
    }

    /**
     * Build and return the Update instance
     *
     * Subclasses must implement this method to define how the update is built.
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
