<?php
declare(strict_types=1);

namespace Mercure\Service;

use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Mercure\AuthorizationInterface;
use Mercure\Exception\MercureException;
use Mercure\Jwt\TokenFactoryInterface;

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
            'lifetime' => 3600,
            'domain' => null,
            'path' => '/',
            'secure' => false,
            'sameSite' => 'lax',
            'httpOnly' => true,
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
        $cookie = Cookie::create($this->cookieConfig['name'], '')
            ->withPath($this->cookieConfig['path'])
            ->withExpired();

        // Set domain if configured
        if (!empty($this->cookieConfig['domain'])) {
            $cookie = $cookie->withDomain($this->cookieConfig['domain']);
        }

        return $response->withCookie($cookie);
    }

    /**
     * Create a JWT token for subscriber authorization
     *
     * Generates a JWT token that only contains subscribe permissions (no publish).
     * This is specifically for client-side EventSource connections.
     *
     * @param array<string> $subscribe Topics the subscriber can access
     * @param array<string, mixed> $additionalClaims Additional JWT claims
     * @return string JWT token
     * @throws \Mercure\Exception\MercureException
     */
    private function createSubscriberJwt(array $subscribe, array $additionalClaims = []): string
    {
        // For subscriber tokens, we don't need publish permissions
        $jwt = $this->tokenFactory->create($subscribe, [], $additionalClaims);

        if (empty($jwt)) {
            throw new MercureException('Failed to generate subscriber JWT token');
        }

        return $jwt;
    }

    /**
     * Create a cookie with the JWT token
     *
     * @param string $jwt The JWT token to include in the cookie
     * @return \Cake\Http\Cookie\Cookie Cookie instance
     */
    private function buildCookie(string $jwt): Cookie
    {
        $cookie = Cookie::create($this->cookieConfig['name'], $jwt)
            ->withPath($this->cookieConfig['path'])
            ->withSecure($this->cookieConfig['secure'])
            ->withHttpOnly($this->cookieConfig['httpOnly']);

        // Set domain if configured
        if (!empty($this->cookieConfig['domain'])) {
            $cookie = $cookie->withDomain($this->cookieConfig['domain']);
        }

        // Set expiration if lifetime is specified
        if ($this->cookieConfig['lifetime'] > 0) {
            $expiry = DateTime::now()->addSeconds($this->cookieConfig['lifetime']);
            $cookie = $cookie->withExpiry($expiry);
        }

        // Set SameSite attribute if configured
        if (!empty($this->cookieConfig['sameSite'])) {
            $sameSite = ucfirst(strtolower($this->cookieConfig['sameSite']));
            if (in_array(strtolower($sameSite), ['strict', 'lax', 'none'], true)) {
                $cookie = $cookie->withSameSite($sameSite);
            }
        }

        return $cookie;
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
}
