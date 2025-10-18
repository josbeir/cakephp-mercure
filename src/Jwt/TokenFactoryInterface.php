<?php
declare(strict_types=1);

namespace Mercure\Jwt;

/**
 * Token Factory Interface
 *
 * Defines the contract for creating JWT tokens with specific claims for Mercure authorization.
 * Implementations handle the actual JWT generation with the appropriate structure.
 */
interface TokenFactoryInterface
{
    /**
     * Create a JWT token with Mercure claims
     *
     * Creates a token that allows publishing to $publish topics and subscribing to $subscribe topics.
     * The token structure follows the Mercure protocol specification.
     *
     * @param array<string>|null $subscribe List of topic selectors to allow subscribing to
     * @param array<string>|null $publish List of topic selectors to allow publishing to
     * @param array<string, mixed> $additionalClaims Additional claims to include in the JWT payload
     * @return string The generated JWT token
     */
    public function create(
        ?array $subscribe = [],
        ?array $publish = [],
        array $additionalClaims = [],
    ): string;
}
