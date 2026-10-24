<?php
declare(strict_types=1);

namespace Mercure;

/**
 * Topic Management Trait
 *
 * Provides common functionality for managing Mercure topics.
 * Used by both MercureComponent and MercureHelper to keep topic
 * management logic DRY.
 */
trait TopicManagementTrait
{
    /**
     * Runtime topics
     *
     * @var array<string|array{match: string, match_type?: string}>
     */
    protected array $topics = [];

    /**
     * Add a single topic
     *
     * @param array{match: string, match_type?: string}|string $topic Topic to add
     * @return $this
     */
    public function addTopic(string|array $topic)
    {
        if (!in_array($topic, $this->topics, true)) {
            $this->topics[] = $topic;
        }

        return $this;
    }

    /**
     * Add multiple topics
     *
     * @param array<string|array{match: string, match_type?: string}> $topics Topics to add
     * @return $this
     */
    public function addTopics(array $topics)
    {
        foreach ($topics as $topic) {
            $this->addTopic($topic);
        }

        return $this;
    }

    /**
     * Get all topics
     *
     * @return array<string|array{match: string, match_type?: string}> List of topics
     */
    public function getTopics(): array
    {
        return $this->topics;
    }
}
