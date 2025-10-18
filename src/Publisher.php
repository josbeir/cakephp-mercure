<?php
declare(strict_types=1);

namespace Mercure;

use Mercure\Exception\MercureException;
use Mercure\Jwt\FactoryTokenProvider;
use Mercure\Jwt\FirebaseTokenFactory;
use Mercure\Jwt\StaticTokenProvider;
use Mercure\Jwt\TokenFactoryInterface;
use Mercure\Jwt\TokenProviderInterface;
use Mercure\Service\PublisherService;

/**
 * Publisher Facade
 *
 * Provides a static accessor pattern for the PublisherService.
 *
 * Example usage:
 * ```
 * Publisher::publish(new Update(
 *     topics: '/feeds/123',
 *     data: json_encode(['status' => 'completed'])
 * ));
 * ```
 */
class Publisher extends AbstractMercureFacade
{
    private static ?PublisherInterface $instance = null;

    /**
     * Get the PublisherService instance
     *
     * @throws \Mercure\Exception\MercureException
     */
    public static function getInstance(): PublisherInterface
    {
        if (!self::$instance instanceof PublisherInterface) {
            $hubUrl = self::getHubUrl();
            $tokenProvider = self::createTokenProvider();
            $httpClientConfig = self::getHttpClientConfig();

            self::$instance = new PublisherService($hubUrl, $tokenProvider, $httpClientConfig);
        }

        return self::$instance;
    }

    /**
     * Create token provider based on configuration
     *
     * @throws \Mercure\Exception\MercureException
     */
    private static function createTokenProvider(): TokenProviderInterface
    {
        $jwtConfig = self::getJwtConfig();
        $config = self::getConfig();

        // Option 1: Custom provider class
        if (isset($jwtConfig['provider'])) {
            $providerClass = $jwtConfig['provider'];
            if (!class_exists($providerClass)) {
                throw new MercureException(sprintf("Token provider class '%s' not found", $providerClass));
            }

            $provider = new $providerClass();
            if (!$provider instanceof TokenProviderInterface) {
                throw new MercureException('Token provider must implement TokenProviderInterface');
            }

            return $provider;
        }

        // Option 2: Static token value
        if (isset($jwtConfig['value']) && !empty($jwtConfig['value'])) {
            return new StaticTokenProvider($jwtConfig['value']);
        }

        // Option 3: Factory-based with secret (backward compatibility with publisher_jwt)
        $secret = $jwtConfig['secret'] ?? $config['publisher_jwt'] ?? '';
        if (empty($secret)) {
            throw new MercureException('JWT secret or token must be configured');
        }

        $algorithm = $jwtConfig['algorithm'] ?? 'HS256';
        $publish = $jwtConfig['publish'] ?? ['*'];
        $subscribe = $jwtConfig['subscribe'] ?? [];
        $additionalClaims = $jwtConfig['additional_claims'] ?? [];

        // Option 3a: Custom factory class
        if (isset($jwtConfig['factory'])) {
            $factoryClass = $jwtConfig['factory'];
            if (!class_exists($factoryClass)) {
                throw new MercureException(sprintf("Token factory class '%s' not found", $factoryClass));
            }

            $factory = new $factoryClass($secret, $algorithm);
            if (!$factory instanceof TokenFactoryInterface) {
                throw new MercureException('Token factory must implement TokenFactoryInterface');
            }
        } else {
            // Option 3b: Default Firebase factory
            $factory = new FirebaseTokenFactory($secret, $algorithm);
        }

        return new FactoryTokenProvider($factory, $publish, $subscribe, $additionalClaims);
    }

    /**
     * Set a custom PublisherService instance
     *
     * Useful for testing or when you want to use a different configuration.
     *
     * @param \Mercure\PublisherInterface $publisher Publisher service instance
     */
    public static function setInstance(PublisherInterface $publisher): void
    {
        self::$instance = $publisher;
    }

    /**
     * Clear the singleton instance
     */
    public static function clear(): void
    {
        self::$instance = null;
    }

    /**
     * Publish an update to the Mercure hub
     *
     * @param \Mercure\Update $update The update to publish
     * @return bool True if successful
     * @throws \Mercure\Exception\MercureException
     */
    public static function publish(Update $update): bool
    {
        return self::getInstance()->publish($update);
    }
}
