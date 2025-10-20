<?php
declare(strict_types=1);

namespace Mercure\Update;

/**
 * Simple Update Builder
 *
 * Fluent builder class for creating Update instances with simple string data.
 * Extends AbstractUpdateBuilder to inherit common builder functionality.
 *
 * This class can be used directly for simple string data, pre-encoded JSON,
 * HTML fragments, or any other string content that doesn't need additional processing.
 *
 * Example usage:
 * ```
 * // Simple string data
 * $update = (new SimpleUpdate('/books/1'))
 *     ->data('Book is out of stock')
 *     ->build();
 *
 * // With pre-encoded JSON
 * $json = json_encode(['status' => 'OutOfStock', 'quantity' => 0]);
 * $update = (new SimpleUpdate('/books/1'))
 *     ->data($json)
 *     ->build();
 *
 * // Private update with metadata
 * $update = (new SimpleUpdate('/users/123/notifications'))
 *     ->data('New message received')
 *     ->private()
 *     ->id('notif-123')
 *     ->type('notification.new')
 *     ->retry(5000)
 *     ->build();
 *
 * // Multiple topics
 * $update = (new SimpleUpdate(['/books/1', '/notifications']))
 *     ->data('Update message')
 *     ->build();
 *
 * // Static create() method also supported
 * $update = SimpleUpdate::create(
 *     topics: '/books/1',
 *     data: 'Simple message'
 * );
 * ```
 */
final class SimpleUpdate extends AbstractUpdateBuilder
{
    protected mixed $data = '';

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
    public function build(): Update
    {
        $this->validate();

        return $this->createUpdate($this->data);
    }

    /**
     * Static factory method for creating SimpleUpdate instances with a single call
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param string $data Data string
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @throws \InvalidArgumentException
     */
    public static function create(
        string|array $topics,
        string $data,
        bool $private = false,
        ?string $id = null,
        ?string $type = null,
        ?int $retry = null,
    ): Update {
        return (new self($topics))
            ->data($data)
            ->private($private)
            ->id($id)
            ->type($type)
            ->retry($retry)
            ->build();
    }
}
