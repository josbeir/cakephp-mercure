<?php
declare(strict_types=1);

namespace Mercure\Controller\Component;

use Cake\Controller\Component;
use Cake\Event\EventInterface;
use Mercure\Authorization;
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
 *
 *     // Add topics for this view (available in MercureHelper)
 *     $this->Mercure->addTopic("/books/{$id}");
 *
 *     // Authorize subscriber
 *     $this->Mercure->authorize(["/books/{$id}"]);
 *
 *     // Or chain methods
 *     $this->Mercure
 *         ->addTopic("/books/{$id}")
 *         ->addTopic("/user/{$userId}/updates")
 *         ->authorize(["/books/{$id}"])
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
 *     'autoDiscover' => true,     // Automatically add discovery headers
 *     'defaultTopics' => [        // Topics automatically available in all views
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
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'autoDiscover' => false,
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
     * Authorize subscriber for private topics
     *
     * Sets an authorization cookie that allows the subscriber to access
     * private Mercure topics. The cookie must be set before establishing
     * the EventSource connection.
     *
     * Example:
     * ```
     * $this->Mercure->authorize(['/feeds/123', '/notifications/*']);
     *
     * // With additional JWT claims
     * $this->Mercure->authorize(
     *     subscribe: ['/feeds/123'],
     *     additionalClaims: ['sub' => $userId, 'aud' => 'my-app']
     * );
     * ```
     *
     * @param array<string> $subscribe Topics the subscriber can access
     * @param array<string, mixed> $additionalClaims Additional JWT claims to include
     * @return $this For method chaining
     * @throws \Mercure\Exception\MercureException
     */
    public function authorize(array $subscribe = [], array $additionalClaims = []): static
    {
        $response = $this->getController()->getResponse();
        $response = $this->authorizationService->setCookie($response, $subscribe, $additionalClaims);
        $this->getController()->setResponse($response);

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
     * Add the Mercure discovery header to the response
     *
     * Adds a Link header with rel="mercure" to advertise the Mercure hub URL.
     * This allows clients to automatically discover the hub endpoint.
     *
     * Example:
     * ```
     * $this->Mercure->discover();
     *
     * // Or chained with authorize
     * $this->Mercure->authorize(['/feeds/123'])->discover();
     * ```
     *
     * @return $this For method chaining
     * @throws \Mercure\Exception\MercureException
     */
    public function discover(): static
    {
        $request = $this->getController()->getRequest();
        $response = $this->getController()->getResponse();
        $response = $this->authorizationService->addDiscoveryHeader($response, $request);
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
