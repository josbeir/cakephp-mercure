<?php
declare(strict_types=1);

namespace Mercure\Update;

use JsonException;

/**
 * JSON Update
 *
 * Specialized Update class that automatically encodes data to JSON.
 * This eliminates the need to manually json_encode arrays before publishing.
 *
 * Example usage:
 * ```
 * // Simple array
 * $update = JsonUpdate::create(
 *     topics: '/books/1',
 *     data: ['status' => 'OutOfStock', 'quantity' => 0]
 * );
 *
 * // With JSON encoding options
 * $update = JsonUpdate::create(
 *     topics: '/books/1',
 *     data: ['title' => 'Book & Title'],
 *     jsonOptions: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
 * );
 *
 * // Private update
 * $update = JsonUpdate::create(
 *     topics: '/users/123/notifications',
 *     data: ['message' => 'New notification'],
 *     private: true
 * );
 * ```
 */
class JsonUpdate extends Update
{
    /**
     * Create a new JsonUpdate instance
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
    ): self {
        $encodedData = static::encodeJson($data, $jsonOptions);

        return new self(
            topics: $topics,
            data: $encodedData,
            private: $private,
            id: $id,
            type: $type,
            retry: $retry,
        );
    }

    /**
     * Encode data to JSON
     *
     * @param mixed $data Data to encode
     * @param int $options JSON encoding options
     * @return string JSON-encoded string
     * @throws \JsonException If encoding fails
     */
    protected static function encodeJson(mixed $data, int $options): string
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
}
