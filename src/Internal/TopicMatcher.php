<?php
declare(strict_types=1);

namespace Mercure\Internal;

use InvalidArgumentException;

/**
 * Normalize explicit Mercure 1.0 matchers without guessing a pattern type.
 *
 * @internal
 */
class TopicMatcher
{
    /**
     * Remove strictly identical matchers while preserving topic strings and order.
     *
     * @param array<string|array{match: string, match_type?: string}> $topics Topic matchers
     * @return array<string|array{match: string, match_type?: string}>
     */
    public static function unique(array $topics): array
    {
        $unique = [];
        foreach ($topics as $topic) {
            if (!in_array($topic, $unique, true)) {
                $unique[] = $topic;
            }
        }

        return $unique;
    }

    /**
     * @param array{match: string, match_type?: string}|string $topic Topic matcher
     * @return array{match: string, match_type?: string}
     */
    public static function normalize(string|array $topic): array
    {
        if (is_string($topic)) {
            $topic = ['match' => $topic];
        }

        if (!isset($topic['match']) || !is_string($topic['match']) || $topic['match'] === '') {
            throw new InvalidArgumentException('A Mercure topic matcher requires a non-empty match string.');
        }

        if (array_key_exists('match_type', $topic) && !in_array($topic['match_type'], ['exact', 'urlpattern'], true)) {
            throw new InvalidArgumentException('Mercure match_type must be exact or urlpattern.');
        }

        return array_intersect_key($topic, ['match' => true, 'match_type' => true]);
    }
}
