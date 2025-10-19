<?php
declare(strict_types=1);

namespace Mercure\Service;

use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use DateTimeImmutable;
use Mercure\Exception\MercureException;
use Mercure\Internal\ConfigurationHelper;
use Mercure\Jwt\TokenFactoryInterface;
use function ini_get;

/**
 * Authorization Service
 *
 * Handles JWT cookie management for authenticating subscribers to private Mercure topics.
 * This service creates and manages authorization cookies that allow client-side EventSource
 * connections to subscribe to private topics.
 */
class AuthorizationService implements AuthorizationInterface
{
    private TokenFactoryInterface $tokenFactory;

    /**
     * @var array<string, mixed>
     */
    private array $cookieConfig;

    /**
     * Constructor
     *
     * @param \Mercure\Jwt\TokenFactoryInterface $tokenFactory Token factory for JWT generation
     * @param array<string, mixed> $cookieConfig Cookie configuration options
     */
    public function __construct(TokenFactoryInterface $tokenFactory, array $cookieConfig = [])
    {
        $this->tokenFactory = $tokenFactory;
        $this->cookieConfig = $cookieConfig + [
            'name' => 'mercureAuthorization',
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'httponly' => true,
            'samesite' => 'strict',
            'expires' => null,
            'lifetime' => null,
        ];
    }

    /**
     * Create and set the authorization cookie on the response
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @param array<string> $subscribe Array of topics the subscriber can access
     * @param array<string, mixed> $additionalClaims Additional JWT claims to include
     * @return \Cake\Http\Response Modified response with cookie set
     * @throws \Mercure\Exception\MercureException
     */
    public function setCookie(
        Response $response,
        array $subscribe = [],
        array $additionalClaims = [],
    ): Response {
        $jwt = $this->createSubscriberJwt($subscribe, $additionalClaims);
        $cookie = $this->buildCookie($jwt);

        return $response->withCookie($cookie);
    }

    /**
     * Clear the authorization cookie from the response
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @return \Cake\Http\Response Modified response with cookie cleared
     */
    public function clearCookie(Response $response): Response
    {
        $options = [
            'path' => $this->cookieConfig['path'],
            'domain' => $this->cookieConfig['domain'],
        ];

        $cookie = Cookie::create(name: $this->cookieConfig['name'], value: '', options: $options)
            ->withExpired();

        return $response->withCookie($cookie);
    }

    /**
     * Create a JWT token for subscriber authorization
     *
     * Generates a JWT token that only contains subscribe permissions (no publish).
     * This is specifically for client-side EventSource connections.
     *
     * Automatically sets the 'exp' (expiry) claim based on cookie lifetime unless
     * explicitly provided in additionalClaims.
     *
     * @param array<string> $subscribe Topics the subscriber can access
     * @param array<string, mixed> $additionalClaims Additional JWT claims
     * @return string JWT token
     * @throws \Mercure\Exception\MercureException
     */
    private function createSubscriberJwt(array $subscribe, array $additionalClaims = []): string
    {
        // Auto-set JWT expiry claim if not provided
        if (!isset($additionalClaims['exp'])) {
            $additionalClaims['exp'] = $this->calculateExpiry();
        }

        // For subscriber tokens, we don't need publish permissions
        $jwt = $this->tokenFactory->create($subscribe, [], $additionalClaims);

        if (empty($jwt)) {
            throw new MercureException('Failed to generate subscriber JWT token');
        }

        return $jwt;
    }

    /**
     * Calculate JWT expiry time based on cookie configuration
     *
     * Priority order:
     * 1. cookie.expires - Explicit datetime in config
     * 2. cookie.lifetime - Seconds in config
     * 3. session.cookie_lifetime - PHP ini setting
     * 4. Default: +1 hour
     *
     * If lifetime is 0 (session cookie), defaults to +1 hour for security.
     *
     * @return int JWT expiry time as Unix timestamp
     */
    private function calculateExpiry(): int
    {
        // Check for explicit 'expires' in cookie config
        if (isset($this->cookieConfig['expires'])) {
            $expires = $this->cookieConfig['expires'];
            // @phpstan-ignore-next-line notIdentical.alwaysTrue
            if ($expires !== null) {
                $dateTime = new DateTimeImmutable($expires);

                return $dateTime->getTimestamp();
            }
        }

        // Check for 'lifetime' in cookie config
        if (isset($this->cookieConfig['lifetime'])) {
            $lifetime = $this->cookieConfig['lifetime'];
            // @phpstan-ignore-next-line notIdentical.alwaysTrue
            if ($lifetime !== null) {
                $lifetime = (int)$lifetime;
            } else {
                // Fall back to PHP session.cookie_lifetime ini setting
                $lifetime = (int)ini_get('session.cookie_lifetime');
            }
        } else {
            // Fall back to PHP session.cookie_lifetime ini setting
            $lifetime = (int)ini_get('session.cookie_lifetime');
        }

        // If lifetime is 0 (session cookie), default JWT to 1 hour for security
        if ($lifetime === 0) {
            return time() + 3600;
        }

        return time() + $lifetime;
    }

    /**
     * Create a cookie with the JWT token
     *
     * @param string $jwt The JWT token to include in the cookie
     * @return \Cake\Http\Cookie\Cookie Cookie instance
     */
    private function buildCookie(string $jwt): Cookie
    {
        $options = $this->cookieConfig;
        $name = $options['name'];
        unset($options['name']);

        return Cookie::create(
            name: $name,
            value: $jwt,
            options: $options,
        );
    }

    /**
     * Get cookie configuration
     *
     * @return array<string, mixed>
     */
    public function getCookieConfig(): array
    {
        return $this->cookieConfig;
    }

    /**
     * Get the cookie name
     */
    public function getCookieName(): string
    {
        return $this->cookieConfig['name'];
    }

    /**
     * Add the Mercure discovery header to the response
     *
     * Adds a Link header with rel="mercure" to advertise the Mercure hub URL.
     * This allows clients to discover the hub endpoint automatically.
     *
     * Skips CORS preflight requests to prevent conflicts with CORS middleware.
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @param \Cake\Http\ServerRequest|null $request Optional request to check for preflight
     * @return \Cake\Http\Response Modified response with discovery header
     * @throws \Mercure\Exception\MercureException
     */
    public function addDiscoveryHeader(Response $response, ?ServerRequest $request = null): Response
    {
        // Skip preflight requests to prevent CORS issues
        if ($request instanceof ServerRequest && $this->isPreflightRequest($request)) {
            return $response;
        }

        $hubUrl = $this->getPublicUrl();
        $linkHeader = sprintf('<%s>; rel="mercure"', $hubUrl);

        return $response->withAddedHeader('Link', $linkHeader);
    }

    /**
     * Check if the request is a CORS preflight request
     *
     * Preflight requests are OPTIONS requests with the Access-Control-Request-Method header.
     * These requests should not receive application-specific headers like Mercure discovery
     * to prevent conflicts with CORS middleware.
     *
     * @param \Cake\Http\ServerRequest $request The request to check
     * @return bool True if this is a CORS preflight request
     */
    private function isPreflightRequest(ServerRequest $request): bool
    {
        return $request->is('options')
            && $request->hasHeader('Access-Control-Request-Method');
    }

    /**
     * Get the Mercure public URL from configuration
     *
     * This is the client-facing URL for EventSource connections.
     * Falls back to url if not configured.
     *
     * @throws \Mercure\Exception\MercureException
     */
    private function getPublicUrl(): string
    {
        return ConfigurationHelper::getPublicUrl();
    }
}
