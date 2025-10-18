<?php
declare(strict_types=1);

namespace Mercure\Test\Fixture;

/**
 * Invalid Token Provider
 *
 * Does not implement TokenProviderInterface - used for testing error handling.
 */
class InvalidTokenProvider
{
    /**
     * Get JWT token
     */
    public function getJwt(): string
    {
        return 'invalid-token';
    }
}
