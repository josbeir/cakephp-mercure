<?php
declare(strict_types=1);

namespace Mercure\View\Helper;

use Cake\View\Helper;
use Mercure\Authorization;
use Mercure\Internal\ConfigurationHelper;

/**
 * Mercure Helper
 *
 * Provides view-layer integration for Mercure, including:
 * - Generating hub discovery URLs
 * - Managing authorization cookies for subscribers
 * - Building EventSource connection URLs
 * - Adding Mercure discovery headers
 * - Default topics configuration
 *
 * Example usage in templates:
 * ```
 * // Set default topics (in View or controller)
 * $this->loadHelper('Mercure', [
 *     'defaultTopics' => ['/notifications', '/alerts']
 * ]);
 *

 * // Recommended: Get URL and authorize in one call
 * $hubUrl = $this->Mercure->url(
 *     topics: ['/books/123'],                    // Topics to subscribe to in EventSource
 *     subscribe: ['/books/123', '/notifications'] // Topics allowed in JWT (can be broader)
 * );
 *
 * // Default topics will be merged with provided topics
 * $hubUrl = $this->Mercure->url(['/books/123']); // Result: ['/books/123', '/notifications', '/alerts']
 *
 * // Add topics dynamically
 * $this->Mercure->addTopic('/user/123/messages');
 * $this->Mercure->addTopics(['/books/456', '/comments/789']);
 *
 * // Simple usage: Just get the hub URL with default topics
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
 * // Manual authorization (if needed separately):
 * $this->Mercure->authorize(['/books/123', '/notifications']);
 * $this->Mercure->clearAuthorization();
 * ```
 */
class MercureHelper extends Helper
{
    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'defaultTopics' => [],
    ];

    /**
     * Runtime topics (includes default topics + dynamically added topics)
     *
     * @var array<string>
     */
    protected array $topics = [];

    /**
     * Initialize callback
     *
     * @param array<string, mixed> $config Configuration
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Initialize topics from config
        $defaultTopics = $this->getConfig('defaultTopics', []);
        $this->topics = is_array($defaultTopics) ? $defaultTopics : [];
    }

    /**
     * Add a single topic to the helper's topics
     *
     * @param string $topic Topic to add
     * @return $this
     */
    public function addTopic(string $topic)
    {
        if (!in_array($topic, $this->topics, true)) {
            $this->topics[] = $topic;
        }

        return $this;
    }

    /**
     * Add multiple topics to the helper's topics
     *
     * @param array<string> $topics Topics to add
     * @return $this
     */
    public function addTopics(array $topics)
    {
        foreach ($topics as $topic) {
            $this->addTopic($topic);
        }

        return $this;
    }

    /**
     * Merge provided topics with helper's topics (removing duplicates)
     *
     * @param array<string> $topics Provided topics
     * @return array<string>
     */
    protected function mergeTopics(array $topics): array
    {
        if ($this->topics === []) {
            return $topics;
        }

        return array_values(array_unique(array_merge($this->topics, $topics)));
    }

    /**
     * Get the Mercure hub URL and optionally set authorization cookie
     *
     * This method combines authorization and URL generation in one call.
     * When subscribe topics are provided, it automatically sets the authorization cookie
     * and returns the hub URL with topic parameters.
     *
     * Default topics (if configured) will be automatically merged with provided topics.
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

        // Merge with default topics
        $topics = $this->mergeTopics($topics);

        // If subscribe topics provided, set authorization cookie
        if ($subscribe !== []) {
            $this->authorize($subscribe, $additionalClaims);
        }

        // Get hub URL and build subscription URL with topics
        $hubUrl = ConfigurationHelper::getPublicUrl();

        if ($topics === []) {
            return $hubUrl;
        }

        return $this->buildSubscriptionUrl($hubUrl, $topics, []);
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
