<?php
declare(strict_types=1);

namespace Mercure\Jwt;

use Firebase\JWT\JWT;
use InvalidArgumentException;
use Mercure\Internal\TopicMatcher;

/**
 * Firebase Token Factory
 *
 * Generates JWT tokens using the firebase/php-jwt library.
 * Creates tokens with the Mercure protocol structure, using OAuth access tokens and authorization details.
 */
class FirebaseTokenFactory implements TokenFactoryInterface
{
    /**
     * Minimum key length in bytes for HMAC algorithms.
     *
     * @var array<string, int>
     */
    private const MIN_HMAC_KEY_LENGTH = [
        'HS256' => 32,
        'HS384' => 48,
        'HS512' => 64,
    ];

    /**
     * Constructor
     *
     * @param string $secret The secret key for signing JWTs
     * @param string $algorithm The signing algorithm (default: HS256)
     * @param array<string, mixed> $defaultClaims Claims shared by all generated tokens
     */
    public function __construct(
        private string $secret,
        private string $algorithm = 'HS256',
        private array $defaultClaims = [],
    ) {
        $this->validateSymmetricKeyLength();
    }

    /**
     * Create a JWT token with Mercure claims
     *
     * Generates a token following the Mercure protocol specification.
     * The token includes authorization_details containing publish/subscribe topic matchers.
     *
     * @param array<string|array{match: string, match_type?: string}>|null $subscribe List of topic selectors to allow subscribing to
     * @param array<string|array{match: string, match_type?: string}>|null $publish List of topic selectors to allow publishing to
     * @param array<string, mixed> $additionalClaims Additional claims to include in the JWT payload
     * @return string The generated JWT token
     */
    public function create(
        ?array $subscribe = [],
        ?array $publish = [],
        array $additionalClaims = [],
    ): string {
        $claims = array_replace($this->defaultClaims, $additionalClaims);
        foreach (['iss', 'sub', 'client_id'] as $claim) {
            if (!isset($claims[$claim]) || !is_string($claims[$claim]) || $claims[$claim] === '') {
                throw new InvalidArgumentException(sprintf('Mercure 1.0 requires a non-empty "%s" JWT claim.', $claim));
            }
        }

        $audience = $claims['aud'] ?? null;
        if (
            (!is_string($audience) || $audience === '')
            && (!is_array($audience) || !array_is_list($audience) || $audience === [] || array_filter(
                $audience,
                static fn($value): bool => !is_string($value) || $value === '',
            ) !== [])
        ) {
            throw new InvalidArgumentException('Mercure 1.0 requires a non-empty "aud" JWT claim.');
        }

        $now = time();
        $claims += ['iat' => $now, 'exp' => $now + 3600, 'jti' => bin2hex(random_bytes(16))];
        foreach (['iat', 'exp'] as $claim) {
            if (!is_int($claims[$claim]) && !is_float($claims[$claim])) {
                throw new InvalidArgumentException(sprintf('The "%s" JWT claim must be a NumericDate.', $claim));
            }
        }

        if (!is_string($claims['jti']) || $claims['jti'] === '') {
            throw new InvalidArgumentException('The "jti" JWT claim must be a non-empty string.');
        }

        $details = [];
        foreach (['publish' => $publish ?? [], 'subscribe' => $subscribe ?? []] as $action => $topics) {
            if ($topics !== []) {
                $details[] = [
                    'type' => 'https://mercure.rocks/authorization-detail',
                    'actions' => [$action],
                    'topics' => array_values(array_map(TopicMatcher::normalize(...), $topics)),
                ];
            }
        }

        // Grants come from the explicit arguments, including for subscriber-only cookies.
        unset($claims['mercure']);
        $claims['authorization_details'] = $details;

        return JWT::encode($claims, $this->secret, $this->algorithm, head: ['typ' => 'at+jwt']);
    }

    /**
     * Validate HMAC key lengths to match the minimum security requirements.
     *
     * @throws \InvalidArgumentException When the configured key is too short
     */
    private function validateSymmetricKeyLength(): void
    {
        $minimumLength = self::MIN_HMAC_KEY_LENGTH[$this->algorithm] ?? null;
        if ($minimumLength === null) {
            return;
        }

        if (strlen($this->secret) < $minimumLength) {
            throw new InvalidArgumentException(sprintf(
                'JWT secret is too short for %s. Expected at least %d bytes.',
                $this->algorithm,
                $minimumLength,
            ));
        }
    }
}
