<?php
declare(strict_types=1);

namespace Mercure\Internal;

/**
 * Publish Query Builder
 *
 * Builds URL-encoded query strings for Mercure hub publish requests.
 *
 * @internal
 */
class PublishQueryBuilder
{
    /**
     * Build a URL-encoded query string from data
     *
     * @param array<string, mixed> $data Data to encode
     * @return string URL-encoded query string
     */
    public static function build(array $data): string
    {
        $parts = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = self::encode($key, $v);
                }

                continue;
            }

            $parts[] = self::encode($key, $value);
        }

        return implode('&', $parts);
    }

    /**
     * Encode a key-value pair
     *
     * @param string $key The key
     * @param mixed $value The value
     * @return string Encoded key=value pair
     */
    private static function encode(string $key, mixed $value): string
    {
        return sprintf('%s=%s', $key, urlencode((string)$value));
    }
}
