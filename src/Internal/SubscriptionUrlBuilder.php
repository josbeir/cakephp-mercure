<?php
declare(strict_types=1);

namespace Mercure\Internal;

use InvalidArgumentException;

/**
 * Subscription URL Builder
 *
 * Builds Mercure subscription URLs with matcher query parameters.
 * This utility is used by both MercureHelper and MercureComponent
 * to generate consistent subscription URLs.
 *
 * @internal
 */
class SubscriptionUrlBuilder
{
    /**
     * Build a subscription URL with topics and optional query parameters
     *
     * Generates a complete Mercure hub subscription URL with matcher query parameters.
     * Topics can be specified multiple times in the query string as per Mercure spec.
     *
     * Example:
     * ```
     * $url = SubscriptionUrlBuilder::build(
     *     'https://hub.example.com/.well-known/mercure',
     *     ['/books/123', '/notifications']
     * );
     * // Result: https://hub.example.com/.well-known/mercure?match=%2Fbooks%2F123&match=%2Fnotifications
     * ```
     *
     * @param string $hubUrl Base hub URL
     * @param array<string|array{match: string, match_type?: string}> $topics Topics to subscribe to
     * @param array<string, mixed> $options Additional query parameters
     * @return string Complete subscription URL
     */
    public static function build(
        string $hubUrl,
        array $topics,
        array $options = [],
    ): string {
        foreach (['authorization', 'access_token', 'topic', 'lastEventID', 'Last-Event-ID'] as $parameter) {
            if (array_key_exists($parameter, $options)) {
                throw new InvalidArgumentException(sprintf('Unsupported Mercure query parameter: %s.', $parameter));
            }
        }

        if ($topics === [] && $options === []) {
            return $hubUrl;
        }

        $params = [];

        // Add topic parameters (can be specified multiple times)
        foreach ($topics as $topic) {
            $matcher = TopicMatcher::normalize($topic);
            $parameter = ($matcher['match_type'] ?? 'exact') === 'exact' ? 'match' : 'match_urlpattern';
            $params[] = $parameter . '=' . urlencode($matcher['match']);
        }

        // Add additional options
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $params[] = urlencode($key) . '=' . urlencode((string)$item);
                }
            } else {
                $params[] = urlencode($key) . '=' . urlencode((string)$value);
            }
        }

        if ($params === []) {
            return $hubUrl;
        }

        $separator = str_contains($hubUrl, '?') ? '&' : '?';

        return $hubUrl . $separator . implode('&', $params);
    }
}
