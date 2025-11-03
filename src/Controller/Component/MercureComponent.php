<?php
declare(strict_types=1);

namespace Mercure\Controller\Component;

use Cake\Controller\Component;
use Cake\Event\EventInterface;
use Mercure\Authorization;
use Mercure\Internal\ConfigurationHelper;
use Mercure\Internal\SubscriptionUrlBuilder;
use Mercure\Publisher;
use Mercure\Service\AuthorizationInterface;
use Mercure\Service\PublisherInterface;
use Mercure\TopicManagementTrait;
use Mercure\Update\JsonUpdate;
use Mercure\Update\SimpleUpdate;
use Mercure\Update\Update;
use Mercure\Update\ViewUpdate;

/**
 * Mercure Component
 *
 * Simplifies Mercure authorization and publishing in controllers with automatic
 * dependency injection and fluent interface.
 *
 * Example usage:
 * ```
 * // In your controller
 * public function initialize(): void
 * {
 *     parent::initialize();
 *     $this->loadComponent('Mercure.Mercure');
 * }
 *
 * public function view($id)
 * {
 *     $book = $this->Books->get($id);
 *     $userId = $this->Authentication->getIdentity()->id;
 *
 *     // Add topics for this view (available in MercureHelper)
 *     $this->Mercure->addTopic("/books/{$id}");
 *
 *     // Fluent authorization with builder pattern
 *     $this->Mercure
 *         ->addSubscribe("/books/{$id}", ['sub' => $userId])
 *         ->addSubscribe("/notifications/*", ['role' => 'user'])
 *         ->authorize()
 *         ->discover();
 *
 *     // Or direct authorization (backward compatible)
 *     $this->Mercure->authorize(["/books/{$id}"], ['sub' => $userId]);
 *
 *     // Chain topics and authorization together
 *     $this->Mercure
 *         ->addTopic("/books/{$id}")
 *         ->addTopic("/user/{$userId}/updates")
 *         ->addSubscribe("/books/{$id}", ['sub' => $userId])
 *         ->authorize()
 *         ->discover();
 *
 *     $this->set('book', $book);
 * }
 *
 * public function update($id)
 * {
 *     $book = $this->Books->get($id);
 *     $book = $this->Books->patchEntity($book, $this->request->getData());
 *     $this->Books->save($book);
 *
 *     // Publish JSON update
 *     $this->Mercure->publishJson(
 *         topics: "/books/{$id}",
 *         data: ['status' => $book->status, 'title' => $book->title]
 *     );
 *
 *     // Or publish simple string data
 *     $this->Mercure->publishSimple(
 *         topics: "/books/{$id}",
 *         data: 'Book updated'
 *     );
 * }
 *
 * public function logout()
 * {
 *     $this->Mercure->clearAuthorization();
 *     return $this->redirect(['action' => 'login']);
 * }
 * ```
 *
 * Configuration:
 * ```
 * $this->loadComponent('Mercure.Mercure', [
 *     'autoDiscover' => true,        // Automatically add discovery headers
 *     'discoverWithTopics' => true,  // Include subscribe topics in discovery rel="self" link
 *     'defaultTopics' => [           // Topics automatically available in all views
 *         '/notifications',
 *         '/global/alerts'
 *     ]
 * ]);
 * ```
 */
class MercureComponent extends Component
{
    use TopicManagementTrait;

    protected AuthorizationInterface $authorizationService;

    protected PublisherInterface $publisherService;

    /**
     * Subscribe topics accumulated via addSubscribe()
     *
     * @var array<string>
     */
    protected array $subscribe = [];

    /**
     * Last authorized subscribe topics (preserved for discovery)
     *
     * @var array<string>
     */
    protected array $lastAuthorizedTopics = [];

    /**
     * Additional JWT claims accumulated via addSubscribe()
     *
     * @var array<string, mixed>
     */
    protected array $additionalClaims = [];

    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'autoDiscover' => false,
        'discoverWithTopics' => false,
        'defaultTopics' => [],
    ];

    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        $this->authorizationService = Authorization::create();
        $this->publisherService = Publisher::create();

        // Initialize with default topics from config
        $defaultTopics = $this->getConfig('defaultTopics', []);
        if (is_array($defaultTopics)) {
            $this->topics = $defaultTopics;
        }
    }

    /**
     * Startup callback
     *
     * Automatically adds discovery header if autoDiscover is enabled.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The startup event
     */
    public function startup(EventInterface $event): void
    {
        if ($this->getConfig('autoDiscover')) {
            $this->discover();
        }
    }

    /**
     * beforeRender callback
     *
     * Passes topics to view for MercureHelper to use.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The beforeRender event
     */
    public function beforeRender(EventInterface $event): void
    {
        if ($this->topics !== []) {
            $this->getController()->set('_mercureTopics', $this->topics);
        }
    }

    /**
     * Add a single topic to authorize for subscription
     *
     * Accumulates topics and claims that will be used when authorize() is called.
     * Claims are merged across multiple calls.
     *
     * Example:
     * ```
     * // Topic only
     * $this->Mercure->addSubscribe('/books/123');
     *
     * // Topic with claims
     * $this->Mercure->addSubscribe('/books/123', ['sub' => $userId, 'role' => 'admin']);
     *
     * // Build up gradually
     * $this->Mercure
     *     ->addSubscribe('/books/123', ['sub' => $userId])
     *     ->addSubscribe('/notifications/*', ['role' => 'admin'])
     *     ->authorize();
     * ```
     *
     * @param string $topic Topic pattern (e.g., '/books/123', '/notifications/*')
     * @param array<string, mixed> $additionalClaims JWT claims to merge
     * @return $this For method chaining
     */
    public function addSubscribe(string $topic, array $additionalClaims = []): static
    {
        $this->subscribe[] = $topic;
        $this->additionalClaims = array_merge($this->additionalClaims, $additionalClaims);

        return $this;
    }

    /**
     * Add multiple topics to authorize for subscription
     *
     * Accumulates topics and claims that will be used when authorize() is called.
     * Claims are merged with any existing accumulated claims.
     *
     * Example:
     * ```
     * // Topics only
     * $this->Mercure->addSubscribes(['/books/123', '/notifications/*']);
     *
     * // Topics with claims
     * $this->Mercure->addSubscribes(
     *     ['/books/123', '/notifications/*'],
     *     ['sub' => $userId, 'role' => 'admin']
     * );
     * ```
     *
     * @param array<string> $topics Array of topic patterns
     * @param array<string, mixed> $additionalClaims JWT claims to merge
     * @return $this For method chaining
     */
    public function addSubscribes(array $topics, array $additionalClaims = []): static
    {
        $this->subscribe = array_merge($this->subscribe, $topics);
        $this->additionalClaims = array_merge($this->additionalClaims, $additionalClaims);

        return $this;
    }

    /**
     * Get accumulated subscribe topics
     *
     * @return array<string>
     */
    public function getSubscribe(): array
    {
        return $this->subscribe;
    }

    /**
     * Get accumulated additional claims
     *
     * @return array<string, mixed>
     */
    public function getAdditionalClaims(): array
    {
        return $this->additionalClaims;
    }

    /**
     * Reset accumulated subscribe topics
     *
     * @return $this For method chaining
     */
    public function resetSubscribe(): static
    {
        $this->subscribe = [];

        return $this;
    }

    /**
     * Reset accumulated additional claims
     *
     * @return $this For method chaining
     */
    public function resetAdditionalClaims(): static
    {
        $this->additionalClaims = [];

        return $this;
    }

    /**
     * Authorize subscriber for private topics
     *
     * Sets an authorization cookie that allows the subscriber to access
     * private Mercure topics. The cookie must be set before establishing
     * the EventSource connection.
     *
     * Supports both direct parameters and accumulated state via builder methods.
     * Parameters are merged with accumulated state. Accumulated state is automatically
     * reset after authorization.
     *
     * Example:
     * ```
     * // Direct parameters (backward compatible)
     * $this->Mercure->authorize(['/feeds/123', '/notifications/*']);
     *
     * // With additional JWT claims
     * $this->Mercure->authorize(
     *     subscribe: ['/feeds/123'],
     *     additionalClaims: ['sub' => $userId, 'aud' => 'my-app']
     * );
     *
     * // Using accumulated state
     * $this->Mercure
     *     ->addSubscribe('/books/123', ['sub' => $userId])
     *     ->authorize();
     *
     * // Mixed (parameters merged with accumulated)
     * $this->Mercure
     *     ->addSubscribe('/books/123')
     *     ->authorize(['/notifications/*'], ['sub' => $userId]);
     *
     * // Chain with topics and discovery
     * $this->Mercure
     *     ->addTopic('/books/123')
     *     ->addSubscribe('/books/123', ['sub' => $userId])
     *     ->authorize()
     *     ->discover();
     * ```
     *
     * @param array<string> $subscribe Topics the subscriber can access (merged with addSubscribe())
     * @param array<string, mixed> $additionalClaims Additional JWT claims to include (merged with accumulated claims)
     * @return $this For method chaining
     * @throws \Mercure\Exception\MercureException
     */
    public function authorize(array $subscribe = [], array $additionalClaims = []): static
    {
        // Merge parameter topics with accumulated topics
        $allSubscribe = array_unique(array_merge($this->subscribe, $subscribe));

        // Merge parameter claims with accumulated claims (parameters take precedence)
        $allClaims = array_merge($this->additionalClaims, $additionalClaims);

        $response = $this->getController()->getResponse();
        $response = $this->authorizationService->setCookie($response, $allSubscribe, $allClaims);
        $this->getController()->setResponse($response);

        // Store topics for potential use in discovery
        $this->lastAuthorizedTopics = $allSubscribe;

        // Reset accumulated state after authorization
        $this->resetSubscribe();
        $this->resetAdditionalClaims();

        return $this;
    }

    /**
     * Clear the authorization cookie
     *
     * Removes the authorization cookie from the response, effectively
     * logging out the subscriber from private topics.
     *
     * Example:
     * ```
     * $this->Mercure->clearAuthorization();
     * ```
     *
     * @return $this For method chaining
     * @throws \Mercure\Exception\MercureException
     */
    public function clearAuthorization(): static
    {
        $response = $this->getController()->getResponse();
        $response = $this->authorizationService->clearCookie($response);
        $this->getController()->setResponse($response);

        return $this;
    }

    /**
     * Add Mercure discovery headers to the response
     *
     * Adds Link headers for Mercure hub discovery. Optionally includes
     * a rel="self" link with subscription topics from the subscribe array.
     *
     * Priority for includeTopics parameter:
     * 1. Explicit method parameter (true/false)
     * 2. Component config 'discoverWithTopics'
     * 3. Default: false
     *
     * Examples:
     * ```
     * // Basic discovery
     * $this->Mercure->discover();
     *
     * // With topics from subscribe array
     * $this->Mercure
     *     ->addSubscribe('/books/123')
     *     ->authorize()
     *     ->discover(includeTopics: true);
     *
     * // With all discovery parameters
     * $this->Mercure->discover(
     *     includeTopics: true,
     *     lastEventId: 'urn:uuid:abc-123',
     *     contentType: 'application/ld+json',
     *     keySet: 'https://example.com/.well-known/jwks.json'
     * );
     *
     * // Use config value (discoverWithTopics)
     * $this->Mercure->discover(); // Uses config setting
     * ```
     *
     * @param bool|null $includeTopics Include subscribe topics in rel="self" link (null uses config)
     * @param string|null $lastEventId Last event ID for state reconciliation
     * @param string|null $contentType Content type hint for updates
     * @param string|null $keySet URL to JWK key set for encryption
     * @return $this For method chaining
     * @throws \Mercure\Exception\MercureException
     */
    public function discover(
        ?bool $includeTopics = null,
        ?string $lastEventId = null,
        ?string $contentType = null,
        ?string $keySet = null,
    ): static {
        $request = $this->getController()->getRequest();
        $response = $this->getController()->getResponse();

        // Determine if we should include topics
        // Priority: method param > config > default (false)
        $shouldIncludeTopics = $includeTopics ?? $this->getConfig('discoverWithTopics', false);

        // Build selfUrl if topics should be included and we have authorized topics
        $selfUrl = null;
        if ($shouldIncludeTopics && $this->lastAuthorizedTopics !== []) {
            $hubUrl = ConfigurationHelper::getPublicUrl();
            $selfUrl = SubscriptionUrlBuilder::build($hubUrl, $this->lastAuthorizedTopics);
        }

        $response = $this->authorizationService->addDiscoveryHeader(
            $response,
            $request,
            $selfUrl,
            $lastEventId,
            $contentType,
            $keySet,
        );

        $this->getController()->setResponse($response);

        return $this;
    }

    /**
     * Get the cookie name used for authorization
     *
     * @return string Cookie name
     */
    public function getCookieName(): string
    {
        return $this->authorizationService->getCookieName();
    }

    /**
     * Publish an update to the Mercure hub
     *
     * Example:
     * ```
     * $update = new Update('/books/123', json_encode(['status' => 'updated']));
     * $this->Mercure->publish($update);
     * ```
     *
     * @param \Mercure\Update\Update $update The update to publish
     * @return bool True if successful
     * @throws \Mercure\Exception\MercureException
     */
    public function publish(Update $update): bool
    {
        return $this->publisherService->publish($update);
    }

    /**
     * Publish JSON data to the Mercure hub
     *
     * Automatically encodes the data as JSON before publishing.
     *
     * Example:
     * ```
     * // Simple publish
     * $this->Mercure->publishJson('/books/123', ['status' => 'updated']);
     *
     * // With multiple topics
     * $this->Mercure->publishJson(
     *     topics: ['/books/123', '/notifications'],
     *     data: ['message' => 'Book updated']
     * );
     *
     * // Private update
     * $this->Mercure->publishJson(
     *     topics: '/users/123/notifications',
     *     data: ['message' => 'Private notification'],
     *     private: true
     * );
     * ```
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param mixed $data Data to encode as JSON
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @return bool True if successful
     * @throws \Mercure\Exception\MercureException
     * @throws \JsonException If JSON encoding fails
     */
    public function publishJson(
        array|string $topics,
        mixed $data,
        bool $private = false,
        ?string $id = null,
        ?string $type = null,
        ?int $retry = null,
    ): bool {
        $update = JsonUpdate::create(
            topics: $topics,
            data: $data,
            private: $private,
            id: $id,
            type: $type,
            retry: $retry,
        );

        return $this->publisherService->publish($update);
    }

    /**
     * Publish simple string data to the Mercure hub
     *
     * Publishes raw string data without any encoding or transformation.
     * Useful for pre-encoded JSON, plain text, HTML fragments, or any string data.
     *
     * Example:
     * ```
     * // Plain text message
     * $this->Mercure->publishSimple('/notifications', 'Server maintenance in 5 minutes');
     *
     * // Pre-encoded JSON
     * $json = json_encode(['status' => 'updated']);
     * $this->Mercure->publishSimple('/books/123', $json);
     *
     * // HTML fragment
     * $html = '<div class="alert">Alert message</div>';
     * $this->Mercure->publishSimple('/alerts', $html);
     *
     * // Private update with metadata
     * $this->Mercure->publishSimple(
     *     topics: '/users/123/messages',
     *     data: 'New private message',
     *     private: true,
     *     id: 'msg-456',
     *     type: 'message.new'
     * );
     * ```
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param string $data Raw string data to publish
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @return bool True if successful
     * @throws \Mercure\Exception\MercureException
     */
    public function publishSimple(
        array|string $topics,
        string $data,
        bool $private = false,
        ?string $id = null,
        ?string $type = null,
        ?int $retry = null,
    ): bool {
        $update = SimpleUpdate::create(
            topics: $topics,
            data: $data,
            private: $private,
            id: $id,
            type: $type,
            retry: $retry,
        );

        return $this->publisherService->publish($update);
    }

    /**
     * Publish rendered view/element to the Mercure hub
     *
     * Automatically renders a template or element and publishes the output.
     *
     * Example:
     * ```
     * // Render and publish an element
     * $this->Mercure->publishView(
     *     topics: '/books/123',
     *     element: 'Books/item',
     *     data: ['book' => $book]
     * );
     *
     * // Render and publish a template
     * $this->Mercure->publishView(
     *     topics: '/notifications',
     *     template: 'Notifications/item',
     *     data: ['notification' => $notification]
     * );
     *
     * // With layout
     * $this->Mercure->publishView(
     *     topics: '/dashboard',
     *     template: 'Dashboard/stats',
     *     layout: 'ajax',
     *     data: ['stats' => $stats]
     * );
     * ```
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param string|null $template Template to render
     * @param string|null $element Element to render
     * @param array<string, mixed> $data View variables
     * @param string|null $layout Layout to use
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @return bool True if successful
     * @throws \Mercure\Exception\MercureException
     */
    public function publishView(
        array|string $topics,
        ?string $template = null,
        ?string $element = null,
        array $data = [],
        ?string $layout = null,
        bool $private = false,
        ?string $id = null,
        ?string $type = null,
        ?int $retry = null,
    ): bool {
        $update = ViewUpdate::create(
            topics: $topics,
            template: $template,
            element: $element,
            viewVars: $data,
            layout: $layout,
            private: $private,
            id: $id,
            type: $type,
            retry: $retry,
        );

        return $this->publisherService->publish($update);
    }
}
