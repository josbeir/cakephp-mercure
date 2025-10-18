<?php
declare(strict_types=1);

namespace Mercure;

/**
 * Publisher Interface
 *
 * Defines the contract for publishing updates to a Mercure hub.
 */
interface PublisherInterface
{
    /**
     * Publish an update to the Mercure hub
     *
     * @param \Mercure\Update $update The update to publish
     * @return bool True if successful
     * @throws \Mercure\Exception\MercureException
     */
    public function publish(Update $update): bool;

    /**
     * Get the configured hub URL
     */
    public function getHubUrl(): string;
}
