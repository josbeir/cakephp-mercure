<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Jwt;

use Cake\TestSuite\TestCase;
use Mercure\Jwt\StaticTokenProvider;

/**
 * StaticTokenProvider Test Case
 */
class StaticTokenProviderTest extends TestCase
{
    /**
     * Test that the provider returns the token it was constructed with
     */
    public function testGetJwtReturnsStaticToken(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InB1Ymxpc2giOlsiKiJdfX0.test';
        $provider = new StaticTokenProvider($token);

        $this->assertSame($token, $provider->getJwt());
    }

    /**
     * Test that provider instances keep their own tokens
     */
    public function testProvidersKeepTheirOwnTokens(): void
    {
        $first = new StaticTokenProvider('first.token.value');
        $second = new StaticTokenProvider('second.token.value');

        $this->assertSame('first.token.value', $first->getJwt());
        $this->assertSame('second.token.value', $second->getJwt());
    }

    /**
     * Test that empty token is allowed
     */
    public function testEmptyTokenIsAllowed(): void
    {
        $provider = new StaticTokenProvider('');

        $this->assertSame('', $provider->getJwt());
    }
}
