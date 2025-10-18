<?php
declare(strict_types=1);

namespace Mercure\Jwt;

/**
 * Token Provider Interface
 *
 * Defines the contract for providing JWT tokens to authenticate with the Mercure hub.
 * Implementations can provide tokens from various sources (static, factory-generated, etc.).
 */
interface TokenProviderInterface
{
    /**
     * Get the JWT token for publishing/subscribing
     *
     * @return string The JWT token
     */
    public function getJwt(): string;
}
