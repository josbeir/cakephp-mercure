<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Internal;

use Cake\TestSuite\TestCase;
use Mercure\Internal\SubscriptionUrlBuilder;

/**
 * SubscriptionUrlBuilder Test Case
 *
 * Tests the SubscriptionUrlBuilder utility for building Mercure subscription URLs.
 */
class SubscriptionUrlBuilderTest extends TestCase
{
    /**
     * Test build with no topics or options returns base URL
     */
    public function testBuildWithNoTopicsOrOptionsReturnsBaseUrl(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $result = SubscriptionUrlBuilder::build($hubUrl, []);

        $this->assertEquals($hubUrl, $result);
    }

    /**
     * Test build with single topic
     */
    public function testBuildWithSingleTopic(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['/books/123'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertEquals(
            'https://hub.example.com/.well-known/mercure?topic=%2Fbooks%2F123',
            $result,
        );
    }

    /**
     * Test build with multiple topics
     */
    public function testBuildWithMultipleTopics(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['/books/123', '/notifications/*', '/user/456'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertEquals(
            'https://hub.example.com/.well-known/mercure?topic=%2Fbooks%2F123&topic=%2Fnotifications%2F%2A&topic=%2Fuser%2F456',
            $result,
        );
    }

    /**
     * Test build with topic containing special characters
     */
    public function testBuildWithSpecialCharactersInTopic(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['/books/{id}', '/user?status=active'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertStringContainsString('topic=%2Fbooks%2F%7Bid%7D', $result);
        $this->assertStringContainsString('topic=%2Fuser%3Fstatus%3Dactive', $result);
    }

    /**
     * Test build with hub URL that already has query parameters
     */
    public function testBuildWithExistingQueryParameters(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure?existing=param';
        $topics = ['/books/123'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertEquals(
            'https://hub.example.com/.well-known/mercure?existing=param&topic=%2Fbooks%2F123',
            $result,
        );
    }

    /**
     * Test build with additional options
     */
    public function testBuildWithAdditionalOptions(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['/books/123'];
        $options = [
            'lastEventID' => 'urn:uuid:abc-123',
            'authorization' => 'Bearer token',
        ];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics, $options);

        $this->assertStringContainsString('topic=%2Fbooks%2F123', $result);
        $this->assertStringContainsString('lastEventID=urn%3Auuid%3Aabc-123', $result);
        $this->assertStringContainsString('authorization=Bearer+token', $result);
    }

    /**
     * Test build with array value in options
     */
    public function testBuildWithArrayValueInOptions(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = [];
        $options = [
            'filter' => ['type1', 'type2', 'type3'],
        ];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics, $options);

        $this->assertStringContainsString('filter=type1', $result);
        $this->assertStringContainsString('filter=type2', $result);
        $this->assertStringContainsString('filter=type3', $result);
    }

    /**
     * Test build with topics and options together
     */
    public function testBuildWithTopicsAndOptions(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['/books/123', '/notifications/*'];
        $options = [
            'lastEventID' => 'urn:uuid:xyz',
        ];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics, $options);

        $this->assertStringContainsString('topic=%2Fbooks%2F123', $result);
        $this->assertStringContainsString('topic=%2Fnotifications%2F%2A', $result);
        $this->assertStringContainsString('lastEventID=urn%3Auuid%3Axyz', $result);
    }

    /**
     * Test build with empty topics array and options returns base URL
     */
    public function testBuildWithEmptyTopicsAndNoOptionsReturnsBaseUrl(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $result = SubscriptionUrlBuilder::build($hubUrl, [], []);

        $this->assertEquals($hubUrl, $result);
    }

    /**
     * Test build properly encodes URI template topics
     */
    public function testBuildProperlyEncodesUriTemplates(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['https://example.com/books/{id}'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertStringContainsString(
            'topic=https%3A%2F%2Fexample.com%2Fbooks%2F%7Bid%7D',
            $result,
        );
    }

    /**
     * Test build with wildcard topic selector
     */
    public function testBuildWithWildcardTopicSelector(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['*'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertEquals(
            'https://hub.example.com/.well-known/mercure?topic=%2A',
            $result,
        );
    }

    /**
     * Test build with numeric values in options
     */
    public function testBuildWithNumericValuesInOptions(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = [];
        $options = [
            'limit' => 10,
            'offset' => 5,
        ];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics, $options);

        $this->assertStringContainsString('limit=10', $result);
        $this->assertStringContainsString('offset=5', $result);
    }

    /**
     * Test build maintains parameter order
     */
    public function testBuildMaintainsParameterOrder(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = ['/first', '/second'];
        $options = ['key' => 'value'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics, $options);

        // Topics should come before options
        $topicPos = strpos($result, 'topic=%2Ffirst');
        $optionPos = strpos($result, 'key=value');

        $this->assertNotFalse($topicPos);
        $this->assertNotFalse($optionPos);
        $this->assertLessThan($optionPos, $topicPos);
    }

    /**
     * Test build with HTTPS URL
     */
    public function testBuildWithHttpsUrl(): void
    {
        $hubUrl = 'https://secure.hub.example.com/.well-known/mercure';
        $topics = ['/secure/data'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertStringStartsWith('https://', $result);
    }

    /**
     * Test build with HTTP URL (for development)
     */
    public function testBuildWithHttpUrl(): void
    {
        $hubUrl = 'http://localhost:3000/.well-known/mercure';
        $topics = ['/local/topic'];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics);

        $this->assertStringStartsWith('http://', $result);
        $this->assertStringContainsString('topic=%2Flocal%2Ftopic', $result);
    }

    /**
     * Test build with complex real-world scenario
     */
    public function testBuildWithComplexRealWorldScenario(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';
        $topics = [
            '/users/123/notifications',
            '/books/{id}',
            'https://example.com/api/resources/*',
        ];
        $options = [
            'lastEventID' => 'urn:uuid:a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ];

        $result = SubscriptionUrlBuilder::build($hubUrl, $topics, $options);

        $this->assertStringContainsString('topic=%2Fusers%2F123%2Fnotifications', $result);
        $this->assertStringContainsString('topic=%2Fbooks%2F%7Bid%7D', $result);
        $this->assertStringContainsString('topic=https%3A%2F%2Fexample.com%2Fapi%2Fresources%2F%2A', $result);
        $this->assertStringContainsString('lastEventID=urn%3Auuid%3Aa1b2c3d4-e5f6-7890-abcd-ef1234567890', $result);
    }
}
