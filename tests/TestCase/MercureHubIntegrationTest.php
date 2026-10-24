<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Response;
use Cake\TestSuite\TestCase;
use Firebase\JWT\JWT;
use Mercure\Authorization;
use Mercure\Internal\SubscriptionUrlBuilder;
use Mercure\Publisher;
use Mercure\Update\Update;

/**
 * Run against a real Mercure 1.x hub without protocol_version_compatibility.
 * Set MERCURE_TEST_URL to enable these tests; CI provisions the hub.
 */
class MercureHubIntegrationTest extends TestCase
{
    private const SECRET = 'mercure-protocol-test-secret-at-least-32-bytes';

    private string $hubUrl;

    /**
     * Use a prefix-less cookie for the local HTTP test hub.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $url = getenv('MERCURE_TEST_URL');
        if ($url === false || $url === '') {
            $this->markTestSkipped('Set MERCURE_TEST_URL to run real Mercure 1.x integration tests.');
        }

        $this->hubUrl = $url;
        Authorization::clear();
        Publisher::clear();
        Configure::write('Mercure', [
            'url' => $url,
            'jwt' => [
                'secret' => self::SECRET,
                'additional_claims' => [
                    'iss' => 'https://app.example.com',
                    'sub' => 'app',
                    'client_id' => 'cakephp',
                ],
            ],
            'cookie' => ['name' => 'mercure_access_token', 'secure' => false],
        ]);
    }

    /**
     * Reset global configuration.
     */
    protected function tearDown(): void
    {
        Authorization::clear();
        Publisher::clear();
        Configure::delete('Mercure');
        parent::tearDown();
    }

    /**
     * Reproduce the original rejection and verify the new publisher token succeeds.
     */
    public function testLegacyTokenRejectedAndModernPublisherAccepted(): void
    {
        $legacy = JWT::encode(['mercure' => ['publish' => ['*']]], self::SECRET, 'HS256');
        $response = (new Client())->post($this->hubUrl, ['topic' => '/legacy-test', 'data' => 'test'], [
            'headers' => ['Authorization' => 'Bearer ' . $legacy],
        ]);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertTrue(Publisher::publish(new Update('/modern-test', 'modern token accepted')));
    }

    /**
     * The generated cookie authenticates a URL Pattern subscription receiving a private update.
     */
    public function testPrivateUpdateReceivedUsingCookieAndUrlPattern(): void
    {
        $pattern = ['match' => 'https://example.com/books/:id', 'match_type' => 'urlpattern'];
        $cookie = Authorization::setCookie(new Response(), [$pattern])
            ->getCookieCollection()->get('mercure_access_token');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);
        $url = SubscriptionUrlBuilder::build($this->hubUrl, [$pattern]);
        $context = stream_context_create(['http' => [
            'header' => "Cookie: mercure_access_token={$jwt}\r\nOrigin: {$this->hubUrl}\r\n",
            'timeout' => 5,
        ]]);
        $stream = fopen($url, 'r', false, $context);
        $this->assertIsResource($stream);

        try {
            stream_set_timeout($stream, 5);
            $data = 'private-update-' . bin2hex(random_bytes(8));
            $this->assertTrue(Publisher::publish(new Update('https://example.com/books/42', $data, private: true)));
            $received = '';
            while (($line = fgets($stream)) !== false) {
                $received .= $line;
                if (str_contains($received, $data)) {
                    break;
                }
            }

            $this->assertStringContainsString($data, $received);
        } finally {
            fclose($stream);
        }
    }
}
