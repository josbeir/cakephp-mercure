<?php
declare(strict_types=1);

namespace Mercure\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Event\EventInterface;
use Mercure\AuthorizationInterface;
use Mercure\Internal\ConfigurationHelper;

/**
 * Mercure Component
 *
 * Simplifies Mercure authorization in controllers by automatically handling
 * response modifications and providing a fluent interface.
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
 *     // Authorize subscriber
 *     $this->Mercure->authorize(["/books/{$id}"]);
 *
 *     // Or with discovery
 *     $this->Mercure->authorize(["/books/{$id}"])->discover();
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
 *     'autoDiscover' => true,  // Automatically add discovery headers
 * ]);
 * ```
 */
class MercureComponent extends Component
{
    /**
     * Default configuration
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'autoDiscover' => false,
    ];

    /**
     * Constructor
     *
     * @param \Cake\Controller\ComponentRegistry<\Cake\Controller\Controller> $registry Component registry
     * @param \Mercure\AuthorizationInterface $authorizationService Authorization service
     * @param array<string, mixed> $config Component configuration
     */
    public function __construct(
        ComponentRegistry $registry,
        protected AuthorizationInterface $authorizationService,
        array $config = [],
    ) {
        parent::__construct($registry, $config);
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
        $response = $this->getController()->getResponse();
        $response = $this->authorizationService->addDiscoveryHeader($response);
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
     * Get the Mercure hub URL
     *
     * This is the server-side URL used for publishing updates.
     *
     * @return string Hub URL
     * @throws \Mercure\Exception\MercureException
     */
    public function getHubUrl(): string
    {
        return ConfigurationHelper::getHubUrl();
    }

    /**
     * Get the Mercure public URL
     *
     * This is the client-facing URL for EventSource connections.
     * Falls back to hub URL if not configured.
     *
     * @return string Public URL
     * @throws \Mercure\Exception\MercureException
     */
    public function getPublicUrl(): string
    {
        return ConfigurationHelper::getPublicUrl();
    }
}
