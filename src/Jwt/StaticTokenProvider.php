<?php
declare(strict_types=1);

namespace Mercure\Jwt;

/**
 * Static Token Provider
 *
 * Provides a static JWT token for Mercure authentication.
 * This is the simplest token provider, useful when you have a pre-generated
 * JWT token that doesn't need to be dynamically created.
 */
class StaticTokenProvider implements TokenProviderInterface
{
    /**
     * Constructor
     *
     * @param string $token The static JWT token to provide
     */
    public function __construct(private string $token)
    {
    }

    /**
     * Get the JWT token
     *
     * @return string The static JWT token
     */
    public function getJwt(): string
    {
        return $this->token;
    }
}
