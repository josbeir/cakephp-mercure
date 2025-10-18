<?php
declare(strict_types=1);

namespace Mercure\Test\Fixture;

/**
 * Invalid Token Factory
 *
 * Does not implement TokenFactoryInterface - used for testing error handling
 */
class InvalidTokenFactory
{
    /**
     * @param array<string>|null $subscribe
     * @param array<string>|null $publish
     * @param array<string, mixed> $additionalClaims
     */
    public function create(
        ?array $subscribe = [],
        ?array $publish = [],
        array $additionalClaims = [],
    ): string {
        return 'invalid.token.value';
    }
}
