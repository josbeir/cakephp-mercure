<?php
declare(strict_types=1);

namespace Mercure\View\Helper;

use Cake\View\Helper;
use Mercure\Authorization;

/**
 * Mercure Helper
 *
 * Provides view-layer integration for Mercure, including:
 * - Generating hub discovery URLs
 * - Managing authorization cookies for subscribers
 * - Building EventSource connection URLs
 * - Adding Mercure discovery headers
 *
 * Example usage in templates:
 * ```
 * // Recommended: Get URL and authorize in one call
 * $hubUrl = $this->Mercure->url(
 *     topics: ['/books/123'],                    // Topics to subscribe to in EventSource
 *     subscribe: ['/books/123', '/notifications'] // Topics allowed in JWT (can be broader)
 * );
 *
 * // Simple usage: Just get the hub URL
 * $hubUrl = $this->Mercure->url();
 *
 * // Subscribe to multiple topics
 * $hubUrl = $this->Mercure->url(['/books/123', '/notifications']);
 *
 * // Authorize for private updates with wildcard
 * $hubUrl = $this->Mercure->url('/books/123', ['/books/*']);
 *
 * // With additional JWT claims (e.g., user info)
 * $hubUrl = $this->Mercure->url(
 *     topics: ['/users/{userId}/notifications'],
 *     subscribe: ['/users/{userId}/notifications'],
 *     additionalClaims: ['sub' => $userId, 'aud' => 'my-app']
 * );
 *
 * // Add Mercure discovery header
 * $this->Mercure->discover();
 *
 * // Legacy API (still supported):
 * $this->Mercure->authorize(['/books/123', '/notifications']);
 * $hubUrl = $this->Mercure->getHubUrl(['/books/123']);
 * $this->Mercure->clearAuthorization();
 * ```
 */
class MercureHelper extends Helper
{
    /**
     * Get the Mercure hub URL and optionally set authorization cookie
     *
     * This method combines authorization and URL generation in one call.
     * When subscribe topics are provided, it automatically sets the authorization cookie
     * and returns the hub URL with topic parameters.
     *
     * @param array<string>|string|null $topics Topics to subscribe to (can be array of topics or single topic)
     * @param array<string> $subscribe Topics the subscriber can access (for authorization)
     * @param array<string, mixed> $additionalClaims Additional JWT claims for authorization
     * @return string Hub URL with optional topic query parameters
     * @throws \Mercure\Exception\MercureException
     */
    public function url(array|string|null $topics = null, array $subscribe = [], array $additionalClaims = []): string
    {
        // Normalize topics to array
        if (is_string($topics)) {
            $topics = [$topics];
        } elseif ($topics === null) {
            $topics = [];
        }

        // If subscribe topics provided, set authorization cookie
        if ($subscribe !== []) {
            $this->authorize($subscribe, $additionalClaims);
        }

        // Get hub URL and build subscription URL with topics
        $hubUrl = Authorization::getPublicUrl();

        if ($topics === []) {
            return $hubUrl;
        }

        return $this->buildSubscriptionUrl($hubUrl, $topics, []);
    }

    /**
     * Get the Mercure hub URL
     *
     * Optionally builds a URL with topic query parameters for EventSource connections.
     *
     * @param array<string> $topics Optional topics to subscribe to
     * @param array<string, mixed> $options Additional query parameters
     * @return string Hub URL
     * @throws \Mercure\Exception\MercureException
     */
    public function getHubUrl(array $topics = [], array $options = []): string
    {
        $hubUrl = Authorization::getPublicUrl();

        if ($topics === [] && $options === []) {
            return $hubUrl;
        }

        return $this->buildSubscriptionUrl($hubUrl, $topics, $options);
    }

    /**
     * Set authorization cookie for subscriber access to private topics
     *
     * This modifies the response object to include the authorization cookie,
     * allowing the client's EventSource connection to authenticate.
     *
     * @param array<string> $subscribe Topics the subscriber can access
     * @param array<string, mixed> $additionalClaims Additional JWT claims
     * @throws \Mercure\Exception\MercureException
     */
    public function authorize(array $subscribe = [], array $additionalClaims = []): void
    {
        $response = $this->getView()->getResponse();
        $response = Authorization::setCookie($response, $subscribe, $additionalClaims);
        $this->getView()->setResponse($response);
    }

    /**
     * Clear the authorization cookie
     *
     * Removes the subscriber authorization cookie from the response.
     *
     * @throws \Mercure\Exception\MercureException
     */
    public function clearAuthorization(): void
    {
        $response = $this->getView()->getResponse();
        $response = Authorization::clearCookie($response);
        $this->getView()->setResponse($response);
    }

    /**
     * Build a subscription URL with topics and options
     *
     * @param string $hubUrl Base hub URL
     * @param array<string> $topics Topics to subscribe to
     * @param array<string, mixed> $options Additional query parameters
     * @return string Complete subscription URL
     */
    private function buildSubscriptionUrl(string $hubUrl, array $topics, array $options): string
    {
        $params = [];

        // Add topic parameters (can be specified multiple times)
        foreach ($topics as $topic) {
            $params[] = 'topic=' . urlencode($topic);
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

    /**
     * Get the cookie name used for authorization
     *
     * @return string Cookie name
     */
    public function getCookieName(): string
    {
        return Authorization::getCookieName();
    }

    /**
     * Add the Mercure discovery header to the response
     *
     * Adds a Link header with rel="mercure" to advertise the Mercure hub URL.
     * This allows clients to automatically discover the hub endpoint for
     * establishing EventSource connections.
     *
     * Skips CORS preflight requests to prevent conflicts with CORS middleware.
     *
     * Example usage:
     * ```
     * // In a template or layout
     * $this->Mercure->discover();
     * ```
     *
     * @throws \Mercure\Exception\MercureException
     */
    public function discover(): void
    {
        $request = $this->getView()->getRequest();
        $response = $this->getView()->getResponse();
        $response = Authorization::addDiscoveryHeader($response, $request);
        $this->getView()->setResponse($response);
    }
}
