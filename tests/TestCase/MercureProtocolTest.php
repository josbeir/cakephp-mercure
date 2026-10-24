<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Mercure\Authorization;
use Mercure\Controller\Component\MercureComponent;
use Mercure\Internal\SubscriptionUrlBuilder;
use Mercure\Jwt\FirebaseTokenFactory;
use Mercure\Publisher;
use Mercure\View\Helper\MercureHelper;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression coverage for the Mercure 1.x wire protocol and facade configuration.
 */
class MercureProtocolTest extends TestCase
{
    private const SECRET = 'mercure-protocol-test-secret-at-least-32-bytes';

    /**
     * Configure separate internal and public hub URLs.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Authorization::clear();
        Publisher::clear();
        Configure::write('Mercure', [
            'url' => 'http://mercure/.well-known/mercure',
            'public_url' => 'https://hub.example.com/.well-known/mercure',
            'jwt' => [
                'secret' => self::SECRET,
                'publish' => ['*'],
                'additional_claims' => [
                    'iss' => 'https://app.example.com',
                    'sub' => 'app',
                    'client_id' => 'cakephp',
                ],
            ],
        ]);
    }

    /**
     * Reset facade state.
     */
    protected function tearDown(): void
    {
        Authorization::clear();
        Publisher::clear();
        Configure::delete('Mercure');
        parent::tearDown();
    }

    /**
     * Tokens carry OAuth access-token headers, identity and explicit grants.
     */
    public function testAccessTokenFormatAndFreshIdentifiers(): void
    {
        $factory = new FirebaseTokenFactory(self::SECRET, defaultClaims: [
            'iss' => 'https://app.example.com',
            'aud' => ['https://hub.example.com/.well-known/mercure'],
            'sub' => 'app',
            'client_id' => 'cakephp',
        ]);
        $pattern = ['match' => 'https://example.com/books/:id', 'match_type' => 'urlpattern'];
        $jwt = $factory->create([$pattern], ['*']);
        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $jwt)[0]), true);
        $claims = JWT::decode($jwt, new Key(self::SECRET, 'HS256'));

        $this->assertSame('at+jwt', $header['typ']);
        $this->assertObjectNotHasProperty('mercure', $claims);
        $this->assertSame(['https://hub.example.com/.well-known/mercure'], $claims->aud);
        $this->assertSame('https://app.example.com', $claims->iss);
        $this->assertSame('app', $claims->sub);
        $this->assertSame('cakephp', $claims->client_id);
        $this->assertEqualsWithDelta(time(), $claims->iat, 2);
        $this->assertEqualsWithDelta(time() + 3600, $claims->exp, 2);
        $this->assertSame('https://mercure.rocks/authorization-detail', $claims->authorization_details[0]->type);
        $this->assertSame(['publish'], $claims->authorization_details[0]->actions);
        $this->assertSame('*', $claims->authorization_details[0]->topics[0]->match);
        $this->assertSame(['subscribe'], $claims->authorization_details[1]->actions);
        $this->assertSame($pattern, (array)$claims->authorization_details[1]->topics[0]);

        $second = JWT::decode($factory->create(), new Key(self::SECRET, 'HS256'));
        $this->assertNotSame($claims->jti, $second->jti);
        $this->assertSame([], $second->authorization_details);
    }

    /**
     * Both facades use default identity claims; cookie grants never include publish permissions.
     */
    public function testSubscriberCookieUsesSharedClaimsAndSecureDefaults(): void
    {
        $response = Authorization::setCookie(new Response(), ['/books/1'], [
            'sub' => 'user-42',
            'exp' => time() + 600,
            'mercure' => ['publish' => ['*']],
            'authorization_details' => [['actions' => ['publish']]],
        ]);
        $cookie = $response->getCookieCollection()->get('__Secure-mercure_access_token');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);
        $claims = JWT::decode($jwt, new Key(self::SECRET, 'HS256'));

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('user-42', $claims->sub);
        $this->assertSame('https://hub.example.com/.well-known/mercure', $claims->aud);
        $this->assertEqualsWithDelta(time() + 600, $claims->exp, 2);
        $this->assertCount(1, $claims->authorization_details);
        $this->assertSame(['subscribe'], $claims->authorization_details[0]->actions);
        $this->assertSame('/books/1', $claims->authorization_details[0]->topics[0]->match);
        $this->assertObjectNotHasProperty('mercure', $claims);

        $cleared = Authorization::clearCookie($response)->getCookieCollection()->get($cookie->getName());
        $this->assertTrue($cleared->isSecure());
        $this->assertTrue($cleared->isExpired());
    }

    /**
     * Cookie expiry and an explicitly configured audience are preserved.
     */
    public function testCookieLifetimeAndAudienceOverride(): void
    {
        Configure::write('Mercure.jwt.additional_claims.aud', 'urn:mercure:hub');
        Configure::write('Mercure.cookie', ['name' => 'access_token', 'secure' => false, 'lifetime' => 120]);
        $cookie = Authorization::setCookie(new Response(), ['*'])->getCookieCollection()->get('access_token');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);
        $claims = JWT::decode($jwt, new Key(self::SECRET, 'HS256'));
        $this->assertFalse($cookie->isSecure());
        $this->assertSame('urn:mercure:hub', $claims->aud);
        $this->assertEqualsWithDelta(time() + 120, $claims->exp, 2);
    }

    /**
     * A complete token configuration can issue cookies without a publishing URL.
     */
    public function testExplicitAudienceDoesNotRequireHubUrl(): void
    {
        Configure::delete('Mercure.url');
        Configure::delete('Mercure.public_url');
        Configure::write('Mercure.jwt.additional_claims.aud', 'urn:mercure:hub');

        $cookie = Authorization::setCookie(new Response(), ['*'])
            ->getCookieCollection()->get('__Secure-mercure_access_token');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);
        $claims = JWT::decode($jwt, new Key(self::SECRET, 'HS256'));
        $this->assertSame('urn:mercure:hub', $claims->aud);
    }

    /**
     * Distinct exact topics keep their grants and subscription parameters.
     *
     * @param array<string|array{match: string, match_type?: string}> $topics Distinct topic matchers
     */
    #[DataProvider('numericTopics')]
    public function testNumericTopicsRemainDistinct(array $topics): void
    {
        $helper = new MercureHelper(new View(), ['defaultTopics' => [$topics[0]]]);
        $url = $helper->url([$topics[1], $topics[0]]);
        $this->assertSame('https://hub.example.com/.well-known/mercure?match=01&match=1', $url);

        $controller = new Controller(new ServerRequest());
        $component = new MercureComponent(new ComponentRegistry($controller));
        $component->addSubscribe($topics[0])->authorize([$topics[1], $topics[0]])->discover(includeTopics: true);
        $jwt = $controller->getResponse()->getCookieCollection()->get('__Secure-mercure_access_token')->getValue();
        $this->assertIsString($jwt);
        $claims = JWT::decode($jwt, new Key(self::SECRET, 'HS256'));
        $this->assertSame(['01', '1'], array_column($claims->authorization_details[0]->topics, 'match'));
        $this->assertStringContainsString('?match=01&match=1', $controller->getResponse()->getHeaderLine('Link'));
    }

    /**
     * @return array<string, array{array<string|array{match: string, match_type?: string}>}>
     */
    public static function numericTopics(): array
    {
        return [
            'strings' => [['01', '1']],
            'typed matchers' => [[['match' => '01'], ['match' => '1']]],
        ];
    }

    /**
     * Matchers survive helper defaults, controller accumulation and discovery URLs.
     */
    public function testHelpersAndComponentsUseModernMatchers(): void
    {
        $pattern = ['match' => 'https://example.com/books/:id', 'match_type' => 'urlpattern'];
        $helper = new MercureHelper(new View(), ['defaultTopics' => [$pattern]]);
        $helper->addTopic('/books/1');

        $url = $helper->url([$pattern]);
        $this->assertSame(1, substr_count($url, 'match_urlpattern='));
        $this->assertStringContainsString('match=%2Fbooks%2F1', $url);
        $this->assertStringNotContainsString('topic=', $url);

        $controller = new Controller(new ServerRequest());
        $component = new MercureComponent(new ComponentRegistry($controller));
        $component->addSubscribe($pattern)->authorize([$pattern])->discover(includeTopics: true);
        $jwt = $controller->getResponse()->getCookieCollection()->get('__Secure-mercure_access_token')->getValue();
        $this->assertIsString($jwt);
        $claims = JWT::decode($jwt, new Key(self::SECRET, 'HS256'));
        $this->assertCount(1, $claims->authorization_details[0]->topics);
        $this->assertStringContainsString('match_urlpattern=', $controller->getResponse()->getHeaderLine('Link'));
    }

    /**
     * Reconnection cursors use the new spelling.
     */
    public function testSubscriptionUrlUsesModernCursor(): void
    {
        $url = SubscriptionUrlBuilder::build(
            'https://hub.example.com/.well-known/mercure?existing=1',
            ['/books/1', ['match' => 'https://example.com/books/:id', 'match_type' => 'urlpattern']],
            ['last_event_id' => 'event-1'],
        );
        $this->assertSame(
            'https://hub.example.com/.well-known/mercure?existing=1&match=%2Fbooks%2F1'
            . '&match_urlpattern=https%3A%2F%2Fexample.com%2Fbooks%2F%3Aid&last_event_id=event-1',
            $url,
        );
    }

    /**
     * @param string $claim Required claim to remove
     */
    #[DataProvider('requiredClaims')]
    public function testMissingIdentityClaimsAreRejected(string $claim): void
    {
        $claims = ['iss' => 'issuer', 'aud' => 'hub', 'sub' => 'app', 'client_id' => 'cakephp'];
        unset($claims[$claim]);
        $factory = new FirebaseTokenFactory(self::SECRET, defaultClaims: $claims);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"' . $claim . '"');
        $factory->create();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredClaims(): array
    {
        return ['issuer' => ['iss'], 'audience' => ['aud'], 'subject' => ['sub'], 'client' => ['client_id']];
    }

    /**
     * Tokens must never be included in modern subscription URLs.
     */
    public function testQueryStringTokensAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SubscriptionUrlBuilder::build('https://hub.example.com', [], ['authorization' => 'secret']);
    }

    /**
     * @param array<string, mixed> $invalidClaims Invalid identity or lifetime values
     */
    #[DataProvider('invalidClaims')]
    public function testMalformedClaimsAreRejected(array $invalidClaims): void
    {
        $claims = ['iss' => 'issuer', 'aud' => 'hub', 'sub' => 'app', 'client_id' => 'cakephp'];
        $factory = new FirebaseTokenFactory(self::SECRET, defaultClaims: array_replace($claims, $invalidClaims));
        $this->expectException(InvalidArgumentException::class);
        $factory->create();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidClaims(): array
    {
        return [
            'empty issuer' => [['iss' => '']],
            'numeric subject' => [['sub' => 42]],
            'empty audience' => [['aud' => []]],
            'object-shaped audience' => [['aud' => ['resource' => 'hub']]],
            'invalid audience entry' => [['aud' => ['hub', 42]]],
            'string expiry' => [['exp' => 'tomorrow']],
            'null issue time' => [['iat' => null]],
            'empty identifier' => [['jti' => '']],
        ];
    }

    /**
     * An explicit factory receives the same shared claims as the built-in default.
     */
    public function testConfiguredFactoryReceivesSubscriberIdentity(): void
    {
        Configure::write('Mercure.jwt.factory', FirebaseTokenFactory::class);
        $cookie = Authorization::setCookie(new Response(), ['/books/1'])
            ->getCookieCollection()->get('__Secure-mercure_access_token');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);
        $claims = JWT::decode($jwt, new Key(self::SECRET, 'HS256'));
        $this->assertSame('https://app.example.com', $claims->iss);
        $this->assertSame('https://hub.example.com/.well-known/mercure', $claims->aud);
    }

    /**
     * Explicit match types must be supported and case-sensitive.
     */
    public function testUnsupportedMatcherIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SubscriptionUrlBuilder::build('https://hub.example.com', [['match' => '*', 'match_type' => 'URLPattern']]);
    }
}
