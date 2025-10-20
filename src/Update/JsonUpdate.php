<?php
declare(strict_types=1);

namespace Mercure\Update;

use JsonException;

/**
 * JSON Update Builder
 *
 * Fluent builder class for creating Update instances with JSON-encoded data.
 * Extends AbstractUpdateBuilder to inherit common builder functionality.
 *
 * This class provides a clean, chainable API for configuring and encoding JSON updates.
 *
 * Example usage:
 * ```
 * // Simple array
 * $update = (new JsonUpdate('/books/1'))
 *     ->data(['status' => 'OutOfStock', 'quantity' => 0])
 *     ->build();
 *
 * // With JSON encoding options
 * $update = (new JsonUpdate('/books/1'))
 *     ->data(['title' => 'Book & Title'])
 *     ->jsonOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
 *     ->build();
 *
 * // Private update
 * $update = (new JsonUpdate('/users/123/notifications'))
 *     ->data(['message' => 'New notification'])
 *     ->private()
 *     ->build();
 *
 * // Multiple topics with metadata
 * $update = (new JsonUpdate(['/books/1', '/notifications']))
 *     ->data(['book' => $book])
 *     ->private()
 *     ->id('book-123')
 *     ->type('book.updated')
 *     ->retry(3000)
 *     ->build();
 *
 * // Static create() method also supported
 * $update = JsonUpdate::create(
 *     topics: '/books/1',
 *     data: ['status' => 'OutOfStock', 'quantity' => 0]
 * );
 * ```
 */
final class JsonUpdate extends AbstractUpdateBuilder
{
    protected mixed $data = null;

    protected int $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Set the data to be JSON-encoded
     *
     * @param mixed $data Data to encode as JSON (array, object, etc.)
     * @return $this
     */
    public function data(mixed $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set JSON encoding options
     *
     * @param int $options JSON encoding options (e.g., JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
     * @return $this
     */
    public function jsonOptions(int $options)
    {
        $this->jsonOptions = $options;

        return $this;
    }

    /**
     * Build and return the Update instance
     *
     * @throws \InvalidArgumentException
     * @throws \JsonException If JSON encoding fails
     */
    public function build(): Update
    {
        $this->validate();

        $encodedData = $this->encodeJson($this->data, $this->jsonOptions);

        return $this->createUpdate($encodedData);
    }

    /**
     * Encode data to JSON
     *
     * @param mixed $data Data to encode
     * @param int $options JSON encoding options
     * @return string JSON-encoded string
     * @throws \JsonException If encoding fails
     */
    protected function encodeJson(mixed $data, int $options): string
    {
        try {
            $encoded = json_encode($data, $options);
            assert(is_string($encoded)); // JSON_THROW_ON_ERROR ensures string or exception

            return $encoded;
        } catch (JsonException $jsonException) {
            throw new JsonException(
                sprintf('Failed to encode data to JSON: %s', $jsonException->getMessage()),
                $jsonException->getCode(),
                $jsonException,
            );
        }
    }

    /**
     * Static create method for creating updates with a single call
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param mixed $data Data to encode as JSON (array, object, etc.)
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @param int $jsonOptions JSON encoding options (default: JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
     * @throws \InvalidArgumentException
     * @throws \JsonException If JSON encoding fails
     */
    public static function create(
        string|array $topics,
        mixed $data,
        bool $private = false,
        ?string $id = null,
        ?string $type = null,
        ?int $retry = null,
        int $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ): Update {
        return (new self($topics))
            ->data($data)
            ->jsonOptions($jsonOptions)
            ->private($private)
            ->id($id)
            ->type($type)
            ->retry($retry)
            ->build();
    }
}
