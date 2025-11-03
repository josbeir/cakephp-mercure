<?php
declare(strict_types=1);

namespace Mercure\Service;

use Cake\Http\Client;
use Exception;
use Mercure\Exception\MercureException;
use Mercure\Internal\PublishQueryBuilder;
use Mercure\Jwt\TokenProviderInterface;
use Mercure\Update\Update;

/**
 * Publisher Service
 *
 * Handles publishing updates to a Mercure hub using CakePHP's HTTP client.
 */
class PublisherService implements PublisherInterface
{
    private Client $httpClient;

    private string $hubUrl;

    private TokenProviderInterface $tokenProvider;

    /**
     * Constructor
     *
     * @param string $hubUrl The Mercure hub URL
     * @param \Mercure\Jwt\TokenProviderInterface $tokenProvider Token provider for JWT authentication
     * @param array<string, mixed> $httpClientConfig HTTP client configuration options
     */
    public function __construct(
        string $hubUrl,
        TokenProviderInterface $tokenProvider,
        array $httpClientConfig = [],
    ) {
        $this->hubUrl = $hubUrl;
        $this->tokenProvider = $tokenProvider;
        $this->httpClient = new Client($httpClientConfig);
    }

    /**
     * Publish an update to the Mercure hub
     *
     * @param \Mercure\Update\Update $update The update to publish
     * @return bool True if successful
     * @throws \Mercure\Exception\MercureException
     */
    public function publish(Update $update): bool
    {
        try {
            $jwt = $this->tokenProvider->getJwt();
            $this->validateJwt($jwt);

            $postData = $this->buildPostData($update);
            $postDataQuery = PublishQueryBuilder::build($postData);

            $response = $this->httpClient->post($this->hubUrl, $postDataQuery, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            if (!$response->isOk()) {
                throw new MercureException(
                    sprintf(
                        'Failed to publish update to Mercure hub. Status: %d, Body: %s',
                        $response->getStatusCode(),
                        $response->getStringBody(),
                    ),
                );
            }

            return true;
        } catch (MercureException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new MercureException(
                'Error publishing to Mercure hub: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e,
            );
        }
    }

    /**
     * Build POST data from update
     *
     * Prepares data array for PublishQueryBuilder to encode.
     * Topics can be provided multiple times for multiple subscriptions.
     *
     * @param \Mercure\Update\Update $update The update object
     * @return array<string, mixed> Post data array
     */
    private function buildPostData(Update $update): array
    {
        return [
            'topic' => $update->getTopics(),
            'data' => $update->getData(),
            'private' => $update->isPrivate() ? 'on' : null,
            'id' => $update->getId(),
            'type' => $update->getType(),
            'retry' => $update->getRetry(),
        ];
    }

    /**
     * Validate JWT token format
     *
     * Validates that the JWT has the correct structure (header.payload.signature).
     * This is a sanity check to catch configuration errors early.
     *
     * Regex ported from Windows Azure Active Directory IdentityModel Extensions for .Net.
     *
     * @param string $jwt The JWT token to validate
     * @throws \Mercure\Exception\MercureException If the JWT format is invalid
     * @license MIT
     * @copyright Copyright (c) Microsoft Corporation
     * @see https://github.com/AzureAD/azure-activedirectory-identitymodel-extensions-for-dotnet/blob/6e7a53e241e4566998d3bf365f03acd0da699a31/src/Microsoft.IdentityModel.JsonWebTokens/JwtConstants.cs#L58
     */
    private function validateJwt(string $jwt): void
    {
        if (empty($jwt)) {
            throw new MercureException('JWT token cannot be empty');
        }

        if (!preg_match('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]*$/', $jwt)) {
            throw new MercureException(
                'The provided JWT is not valid. Expected format: header.payload.signature',
            );
        }
    }

    /**
     * Get the configured hub URL
     */
    public function getHubUrl(): string
    {
        return $this->hubUrl;
    }
}
