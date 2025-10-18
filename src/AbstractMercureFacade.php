<?php
declare(strict_types=1);

namespace Mercure;

use Cake\Core\Configure;
use Mercure\Exception\MercureException;

/**
 * Abstract Mercure Facade
 *
 * Base class for Mercure facade classes (Publisher, Authorization).
 * Provides common configuration management functionality.
 */
abstract class AbstractMercureFacade
{
    /**
     * Get the Mercure hub URL from configuration
     *
     * This is the server-side URL used for publishing updates.
     *
     * @throws \Mercure\Exception\MercureException
     */
    public static function getHubUrl(): string
    {
        $config = Configure::read('Mercure', []);
        $url = $config['url'] ?? $config['hub_url'] ?? '';

        if (empty($url)) {
            throw new MercureException('Mercure hub URL is not configured');
        }

        return $url;
    }

    /**
     * Get the Mercure public URL from configuration
     *
     * This is the client-facing URL for EventSource connections.
     * Falls back to hub_url if not configured.
     *
     * @throws \Mercure\Exception\MercureException
     */
    public static function getPublicUrl(): string
    {
        $config = Configure::read('Mercure', []);
        $publicUrl = $config['public_url'] ?? '';

        if (!empty($publicUrl)) {
            return $publicUrl;
        }

        // Fallback to hub_url
        return self::getHubUrl();
    }

    /**
     * Get the full Mercure configuration
     *
     * @return array<string, mixed>
     */
    protected static function getConfig(): array
    {
        return Configure::read('Mercure', []);
    }

    /**
     * Get JWT configuration
     *
     * @return array<string, mixed>
     */
    protected static function getJwtConfig(): array
    {
        $config = self::getConfig();

        return $config['jwt'] ?? [];
    }

    /**
     * Get cookie configuration
     *
     * @return array<string, mixed>
     */
    protected static function getCookieConfig(): array
    {
        $config = self::getConfig();

        return $config['cookie'] ?? [];
    }

    /**
     * Get HTTP client configuration
     *
     * @return array<string, mixed>
     */
    protected static function getHttpClientConfig(): array
    {
        $config = self::getConfig();

        return $config['http_client'] ?? [];
    }
}
