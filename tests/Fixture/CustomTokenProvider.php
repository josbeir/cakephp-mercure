<?php
declare(strict_types=1);

namespace Mercure\Test\Fixture;

use Mercure\Jwt\TokenProviderInterface;

/**
 * Custom Token Provider Fixture
 *
 * Used for testing custom token provider functionality.
 */
class CustomTokenProvider implements TokenProviderInterface
{
    private string $token;

    /**
     * Constructor
     *
     * @param string $token Token to return
     */
    public function __construct(string $token = 'custom.test.token')
    {
        $this->token = $token;
    }

    /**
     * Get JWT token
     */
    public function getJwt(): string
    {
        return $this->token;
    }
}
