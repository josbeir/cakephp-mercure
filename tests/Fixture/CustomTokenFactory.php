<?php
declare(strict_types=1);

namespace Mercure\Test\Fixture;

use Mercure\Jwt\TokenFactoryInterface;

/**
 * Custom Token Factory for Testing
 *
 * A test fixture that implements TokenFactoryInterface
 * for testing custom factory configuration.
 */
class CustomTokenFactory implements TokenFactoryInterface
{
    /**
     * Constructor
     *
     * @param string $secret JWT secret
     * @param string $algorithm JWT algorithm
     */
    public function __construct(
        private string $secret,
        private string $algorithm,
    ) {
    }

    /**
     * Create a JWT token with Mercure claims
     *
     * @param array<string>|null $subscribe List of topic selectors to allow subscribing to
     * @param array<string>|null $publish List of topic selectors to allow publishing to
     * @param array<string, mixed> $additionalClaims Additional claims to include in the JWT payload
     * @return string JWT token
     */
    public function create(
        ?array $subscribe = [],
        ?array $publish = [],
        array $additionalClaims = [],
    ): string {
        // For testing, just return a mock token with identifiable pattern
        $claims = [
            'subscribe' => $subscribe,
            'publish' => $publish,
            'additional' => $additionalClaims,
        ];

        return 'custom.factory.' . base64_encode(json_encode($claims) ?: '');
    }

    /**
     * Get the secret
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Get the algorithm
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }
}
