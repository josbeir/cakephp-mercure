<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Jwt;

use Cake\TestSuite\TestCase;
use Mercure\Jwt\FactoryTokenProvider;
use Mercure\Jwt\TokenFactoryInterface;

/**
 * FactoryTokenProvider Test Case
 */
class FactoryTokenProviderTest extends TestCase
{
    /**
     * Test that the provider calls the factory and returns its result
     */
    public function testGetJwtCallsFactory(): void
    {
        $expectedToken = 'generated.jwt.token';
        $factory = $this->createMock(TokenFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with([], [], [])
            ->willReturn($expectedToken);

        $provider = new FactoryTokenProvider($factory);
        $token = $provider->getJwt();

        $this->assertSame($expectedToken, $token);
    }

    /**
     * Test that the provider passes publish claims to the factory
     */
    public function testGetJwtPassesPublishClaims(): void
    {
        $publish = ['https://example.com/feeds/{id}'];
        $expectedToken = 'token.with.publish';

        $factory = $this->createMock(TokenFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with([], $publish, [])
            ->willReturn($expectedToken);

        $provider = new FactoryTokenProvider($factory, $publish);
        $token = $provider->getJwt();

        $this->assertSame($expectedToken, $token);
    }

    /**
     * Test that the provider passes subscribe claims to the factory
     */
    public function testGetJwtPassesSubscribeClaims(): void
    {
        $subscribe = ['https://example.com/books/{id}'];
        $expectedToken = 'token.with.subscribe';

        $factory = $this->createMock(TokenFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($subscribe, [], [])
            ->willReturn($expectedToken);

        $provider = new FactoryTokenProvider($factory, [], $subscribe);
        $token = $provider->getJwt();

        $this->assertSame($expectedToken, $token);
    }

    /**
     * Test that the provider passes both publish and subscribe claims
     */
    public function testGetJwtPassesPublishAndSubscribeClaims(): void
    {
        $publish = ['https://example.com/feeds/{id}'];
        $subscribe = ['https://example.com/books/{id}'];
        $expectedToken = 'token.with.both';

        $factory = $this->createMock(TokenFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($subscribe, $publish, [])
            ->willReturn($expectedToken);

        $provider = new FactoryTokenProvider($factory, $publish, $subscribe);
        $token = $provider->getJwt();

        $this->assertSame($expectedToken, $token);
    }

    /**
     * Test that the provider passes additional claims to the factory
     */
    public function testGetJwtPassesAdditionalClaims(): void
    {
        $additionalClaims = ['exp' => 1234567890, 'iss' => 'test'];
        $expectedToken = 'token.with.claims';

        $factory = $this->createMock(TokenFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with([], [], $additionalClaims)
            ->willReturn($expectedToken);

        $provider = new FactoryTokenProvider($factory, [], [], $additionalClaims);
        $token = $provider->getJwt();

        $this->assertSame($expectedToken, $token);
    }

    /**
     * Test that multiple calls generate new tokens each time
     */
    public function testGetJwtCallsFactoryOnEachInvocation(): void
    {
        $factory = $this->createMock(TokenFactoryInterface::class);
        $factory->expects($this->exactly(3))
            ->method('create')
            ->willReturnOnConsecutiveCalls('token1', 'token2', 'token3');

        $provider = new FactoryTokenProvider($factory);

        $this->assertSame('token1', $provider->getJwt());
        $this->assertSame('token2', $provider->getJwt());
        $this->assertSame('token3', $provider->getJwt());
    }

    /**
     * Test that all parameters are passed correctly in a complex scenario
     */
    public function testGetJwtWithAllParameters(): void
    {
        $publish = ['topic1', 'topic2'];
        $subscribe = ['topic3', 'topic4'];
        $additionalClaims = ['custom' => 'value', 'exp' => 9999];
        $expectedToken = 'complex.token.value';

        $factory = $this->createMock(TokenFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($subscribe, $publish, $additionalClaims)
            ->willReturn($expectedToken);

        $provider = new FactoryTokenProvider($factory, $publish, $subscribe, $additionalClaims);
        $token = $provider->getJwt();

        $this->assertSame($expectedToken, $token);
    }
}
