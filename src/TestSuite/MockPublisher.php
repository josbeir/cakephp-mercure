<?php
declare(strict_types=1);

namespace Mercure\TestSuite;

use Mercure\PublisherInterface;
use Mercure\Update;

/**
 * Mock Publisher for Testing
 *
 * Provides a simple way to test code that publishes Mercure updates
 * without making HTTP requests.
 */
class MockPublisher implements PublisherInterface
{
    /**
     * @var array<\Mercure\Update>
     */
    private array $updates = [];

    private string $hubUrl;

    /**
     * Constructor
     *
     * @param string $hubUrl Hub URL to return
     */
    public function __construct(string $hubUrl = 'http://localhost:3000/.well-known/mercure')
    {
        $this->hubUrl = $hubUrl;
    }

    /**
     * Publish an update (stores it in memory)
     *
     * @param \Mercure\Update $update The update to publish
     * @return bool Always returns true
     */
    public function publish(Update $update): bool
    {
        $this->updates[] = $update;

        return true;
    }

    /**
     * Get the hub URL
     */
    public function getHubUrl(): string
    {
        return $this->hubUrl;
    }

    /**
     * Get all published updates
     *
     * @return array<\Mercure\Update>
     */
    public function getUpdates(): array
    {
        return $this->updates;
    }

    /**
     * Reset/clear all published updates
     */
    public function reset(): void
    {
        $this->updates = [];
    }
}
