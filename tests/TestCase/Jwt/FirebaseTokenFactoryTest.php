<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Jwt;

use Cake\TestSuite\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Mercure\Jwt\FirebaseTokenFactory;

/**
 * FirebaseTokenFactory Test Case
 */
class FirebaseTokenFactoryTest extends TestCase
{
    private string $secret = 'test-secret-key-for-jwt-signing';

    /**
     * Test creating a token with publish claims
     */
    public function testCreateWithPublishClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $token = $factory->create([], ['*']);

        $this->assertNotEmpty($token);

        // Decode and verify the token
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('mercure', $decoded);
        $this->assertObjectHasProperty('publish', $decoded->mercure);
        $this->assertSame(['*'], $decoded->mercure->publish);
    }

    /**
     * Test creating a token with subscribe claims
     */
    public function testCreateWithSubscribeClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $subscribe = ['https://example.com/books/{id}'];
        $token = $factory->create($subscribe, []);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('mercure', $decoded);
        $this->assertObjectHasProperty('subscribe', $decoded->mercure);
        $this->assertSame($subscribe, $decoded->mercure->subscribe);
    }

    /**
     * Test creating a token with both publish and subscribe claims
     */
    public function testCreateWithPublishAndSubscribeClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $publish = ['https://example.com/feeds/{id}'];
        $subscribe = ['https://example.com/books/{id}'];
        $token = $factory->create($subscribe, $publish);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('mercure', $decoded);
        $this->assertObjectHasProperty('publish', $decoded->mercure);
        $this->assertObjectHasProperty('subscribe', $decoded->mercure);
        $this->assertSame($publish, $decoded->mercure->publish);
        $this->assertSame($subscribe, $decoded->mercure->subscribe);
    }

    /**
     * Test creating a token with additional claims
     */
    public function testCreateWithAdditionalClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $additionalClaims = [
            'iss' => 'test-issuer',
            'sub' => 'test-subject',
            'exp' => time() + 3600,
        ];
        $token = $factory->create([], ['*'], $additionalClaims);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('mercure', $decoded);
        $this->assertObjectHasProperty('iss', $decoded);
        $this->assertObjectHasProperty('sub', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);
        $this->assertSame('test-issuer', $decoded->iss);
        $this->assertSame('test-subject', $decoded->sub);
    }

    /**
     * Test creating a token with empty claims
     */
    public function testCreateWithEmptyClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $token = $factory->create([], []);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('mercure', $decoded);
        $this->assertIsArray($decoded->mercure);
        $this->assertArrayNotHasKey('publish', $decoded->mercure);
        $this->assertArrayNotHasKey('subscribe', $decoded->mercure);
    }

    /**
     * Test creating a token with null claims
     */
    public function testCreateWithNullClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $token = $factory->create(null, null);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('mercure', $decoded);
        $this->assertIsArray($decoded->mercure);
        $this->assertArrayNotHasKey('publish', $decoded->mercure);
        $this->assertArrayNotHasKey('subscribe', $decoded->mercure);
    }

    /**
     * Test using a different algorithm
     */
    public function testCreateWithDifferentAlgorithm(): void
    {
        $secret = 'a-longer-secret-key-for-hs384-algorithm-testing';
        $factory = new FirebaseTokenFactory($secret, 'HS384');
        $token = $factory->create([], ['*']);

        $decoded = JWT::decode($token, new Key($secret, 'HS384'));
        $this->assertObjectHasProperty('mercure', $decoded);
        $this->assertObjectHasProperty('publish', $decoded->mercure);
    }

    /**
     * Test creating multiple tokens produces different results
     */
    public function testMultipleTokensAreDifferent(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $token1 = $factory->create([], ['topic1']);
        $token2 = $factory->create([], ['topic2']);

        $this->assertNotSame($token1, $token2);

        $decoded1 = JWT::decode($token1, new Key($this->secret, 'HS256'));
        $decoded2 = JWT::decode($token2, new Key($this->secret, 'HS256'));

        $this->assertSame(['topic1'], $decoded1->mercure->publish);
        $this->assertSame(['topic2'], $decoded2->mercure->publish);
    }

    /**
     * Test creating a token with multiple topic selectors
     */
    public function testCreateWithMultipleTopics(): void
    {
        $factory = new FirebaseTokenFactory($this->secret);
        $publish = [
            'https://example.com/feeds/{id}',
            'https://example.com/books/{id}',
            'https://example.com/authors/*',
        ];
        $subscribe = [
            'https://example.com/notifications/{userId}',
        ];
        $token = $factory->create($subscribe, $publish);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertSame($publish, $decoded->mercure->publish);
        $this->assertSame($subscribe, $decoded->mercure->subscribe);
    }
}
