<?php
declare(strict_types=1);

namespace Mercure\Jwt;

/**
 * Factory Token Provider
 *
 * Provides JWT tokens generated dynamically by a TokenFactory.
 * This provider uses a factory to generate tokens with specific publish/subscribe claims,
 * allowing for flexible and dynamic token generation based on runtime requirements.
 */
class FactoryTokenProvider implements TokenProviderInterface
{
    /**
     * Constructor
     *
     * @param \Mercure\Jwt\TokenFactoryInterface $factory The token factory to use
     * @param array<string> $publish List of topic selectors to allow publishing to
     * @param array<string> $subscribe List of topic selectors to allow subscribing to
     * @param array<string, mixed> $additionalClaims Additional claims to include in the JWT
     */
    public function __construct(
        private TokenFactoryInterface $factory,
        private array $publish = [],
        private array $subscribe = [],
        private array $additionalClaims = [],
    ) {
    }

    /**
     * Get the JWT token
     *
     * Generates a new token using the factory with the configured claims.
     *
     * @return string The generated JWT token
     */
    public function getJwt(): string
    {
        return $this->factory->create(
            $this->subscribe,
            $this->publish,
            $this->additionalClaims,
        );
    }
}
