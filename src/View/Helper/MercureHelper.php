<?php
declare(strict_types=1);

namespace Mercure\View\Helper;

use Cake\View\Helper;
use Mercure\Internal\ConfigurationHelper;
use Mercure\TopicManagementTrait;

/**
 * Mercure Helper
 *
 * Provides view-layer integration for Mercure, including:
 * - Generating hub discovery URLs
 * - Building EventSource connection URLs
 * - Default topics configuration
 *
 * For authorization and discovery headers, use the Authorization facade directly in your controller:
 * ```
 * // Set authorization cookie
 * Authorization::setCookie($this->response, ['/books/123']);
 *
 * // Add discovery header
 * Authorization::addDiscoveryHeader($this->response, $this->request);
 * ```
 *
 * Example usage in templates:
 * ```
 * // Load helper with default topics (in View or controller)
 * $this->loadHelper('Mercure', [
 *     'defaultTopics' => ['/notifications', '/alerts']
 * ]);
 *
 * // Simple usage: Get the hub URL with default topics
 * $hubUrl = $this->Mercure->url();
 *
 * // Subscribe to specific topics
 * $hubUrl = $this->Mercure->url(['/books/123']);
 *
 * // Subscribe to multiple topics
 * $hubUrl = $this->Mercure->url(['/books/123', '/notifications']);
 *
 * // Default topics will be merged with provided topics
 * $hubUrl = $this->Mercure->url(['/books/123']); // Result: ['/books/123', '/notifications', '/alerts']
 *
 * // Add topics dynamically
 * $this->Mercure->addTopic('/user/123/messages');
 * $this->Mercure->addTopics(['/books/456', '/comments/789']);
 * ```
 */
class MercureHelper extends Helper
{
    use TopicManagementTrait;

    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'defaultTopics' => [],
    ];

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

        // Merge with topics from component (if set)
        $viewTopics = $this->getView()->get('_mercureTopics');
        if (is_array($viewTopics)) {
            $this->addTopics($viewTopics);
        }
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
     * Get the Mercure hub URL with optional topic query parameters
     *
     * Default topics (if configured) will be automatically merged with provided topics.
     *
     * @param array<string>|string|null $topics Topics to subscribe to (can be array of topics or single topic)
     * @return string Hub URL with optional topic query parameters
     * @throws \Mercure\Exception\MercureException
     */
    public function url(array|string|null $topics = null): string
    {
        // Normalize topics to array
        if (is_string($topics)) {
            $topics = [$topics];
        } elseif ($topics === null) {
            $topics = [];
        }

        // Merge with default topics
        $topics = $this->mergeTopics($topics);

        // Get hub URL and build subscription URL with topics
        $hubUrl = ConfigurationHelper::getPublicUrl();

        if ($topics === []) {
            return $hubUrl;
        }

        return $this->buildSubscriptionUrl($hubUrl, $topics, []);
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
}
