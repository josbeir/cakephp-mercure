<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Jwt;

use Cake\TestSuite\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Mercure\Jwt\FirebaseTokenFactory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * FirebaseTokenFactory Test Case
 */
class FirebaseTokenFactoryTest extends TestCase
{
    private string $secret = 'test-secret-key-with-32-bytes-minimum!!';

    /**
     * @var array<string, string>
     */
    private array $claims = ['iss' => 'issuer', 'aud' => 'hub', 'sub' => 'app', 'client_id' => 'tests'];

    /**
     * Test creating a token with publish claims
     */
    public function testCreateWithPublishClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
        $token = $factory->create([], ['*']);

        $this->assertNotEmpty($token);

        // Decode and verify the token
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('authorization_details', $decoded);
        $this->assertSame(['publish'], $decoded->authorization_details[0]->actions);
        $this->assertSame('*', $decoded->authorization_details[0]->topics[0]->match);
    }

    /**
     * Test creating a token with subscribe claims
     */
    public function testCreateWithSubscribeClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
        $subscribe = ['https://example.com/books/1'];
        $token = $factory->create($subscribe, []);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('authorization_details', $decoded);
        $this->assertSame(['subscribe'], $decoded->authorization_details[0]->actions);
        $this->assertSame(
            $subscribe,
            array_column($decoded->authorization_details[0]->topics, 'match'),
        );
    }

    /**
     * Test creating a token with both publish and subscribe claims
     */
    public function testCreateWithPublishAndSubscribeClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
        $publish = ['https://example.com/feeds/1'];
        $subscribe = ['https://example.com/books/1'];
        $token = $factory->create($subscribe, $publish);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('authorization_details', $decoded);
        $this->assertSame(['publish'], $decoded->authorization_details[0]->actions);
        $this->assertSame(['subscribe'], $decoded->authorization_details[1]->actions);
        $this->assertSame($publish, array_column($decoded->authorization_details[0]->topics, 'match'));
        $this->assertSame(
            $subscribe,
            array_column($decoded->authorization_details[1]->topics, 'match'),
        );
    }

    /**
     * Test creating a token with additional claims
     */
    public function testCreateWithAdditionalClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
        $additionalClaims = [
            'iss' => 'test-issuer',
            'sub' => 'test-subject',
            'exp' => time() + 3600,
        ];
        $token = $factory->create([], ['*'], $additionalClaims);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('authorization_details', $decoded);
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
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
        $token = $factory->create([], []);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('authorization_details', $decoded);
        $this->assertSame([], $decoded->authorization_details);
    }

    /**
     * Test creating a token with null claims
     */
    public function testCreateWithNullClaims(): void
    {
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
        $token = $factory->create(null, null);

        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        $this->assertObjectHasProperty('authorization_details', $decoded);
        $this->assertSame([], $decoded->authorization_details);
    }

    /**
     * Test using a different algorithm
     */
    public function testCreateWithDifferentAlgorithm(): void
    {
        $secret = 'a-longer-secret-key-for-hs384-algorithm-testing!!!!';
        $factory = new FirebaseTokenFactory($secret, 'HS384', $this->claims);
        $token = $factory->create([], ['*']);

        $decoded = JWT::decode($token, new Key($secret, 'HS384'));
        $this->assertObjectHasProperty('authorization_details', $decoded);
        $this->assertSame(['publish'], $decoded->authorization_details[0]->actions);
    }

    /**
     * Test creating multiple tokens produces different results
     */
    public function testMultipleTokensAreDifferent(): void
    {
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
        $token1 = $factory->create([], ['topic1']);
        $token2 = $factory->create([], ['topic2']);

        $this->assertNotSame($token1, $token2);

        $decoded1 = JWT::decode($token1, new Key($this->secret, 'HS256'));
        $decoded2 = JWT::decode($token2, new Key($this->secret, 'HS256'));

        $this->assertSame(['topic1'], array_column($decoded1->authorization_details[0]->topics, 'match'));
        $this->assertSame(['topic2'], array_column($decoded2->authorization_details[0]->topics, 'match'));
    }

    /**
     * Test creating a token with multiple topic selectors
     */
    public function testCreateWithMultipleTopics(): void
    {
        $factory = new FirebaseTokenFactory($this->secret, defaultClaims: $this->claims);
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
        $this->assertSame($publish, array_column($decoded->authorization_details[0]->topics, 'match'));
        $this->assertSame(
            $subscribe,
            array_column($decoded->authorization_details[1]->topics, 'match'),
        );
    }

    /**
     * Test creating a token with too short HMAC secret throws an exception
     *
     * @param string $algorithm HMAC algorithm
     * @param string $secret Too short secret
     */
    #[DataProvider('tooShortHmacSecretsProvider')]
    public function testCreateWithTooShortSecretThrowsException(string $algorithm, string $secret): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('JWT secret is too short for %s', $algorithm));

        new FirebaseTokenFactory($secret, $algorithm);
    }

    /**
     * @return array<string, array{algorithm: string, secret: string}>
     */
    public static function tooShortHmacSecretsProvider(): array
    {
        return [
            'hs256' => ['algorithm' => 'HS256', 'secret' => str_repeat('a', 31)],
            'hs384' => ['algorithm' => 'HS384', 'secret' => str_repeat('a', 47)],
            'hs512' => ['algorithm' => 'HS512', 'secret' => str_repeat('a', 63)],
        ];
    }
}
