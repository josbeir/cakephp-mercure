<?php
declare(strict_types=1);

namespace Mercure\Jwt;

use Firebase\JWT\JWT;

/**
 * Firebase Token Factory
 *
 * Generates JWT tokens using the firebase/php-jwt library.
 * Creates tokens with the Mercure protocol structure, including publish/subscribe claims.
 */
class FirebaseTokenFactory implements TokenFactoryInterface
{
    /**
     * Constructor
     *
     * @param string $secret The secret key for signing JWTs
     * @param string $algorithm The signing algorithm (default: HS256)
     */
    public function __construct(
        private string $secret,
        private string $algorithm = 'HS256',
    ) {
    }

    /**
     * Create a JWT token with Mercure claims
     *
     * Generates a token following the Mercure protocol specification.
     * The token includes a 'mercure' claim containing publish/subscribe topic selectors.
     *
     * @param array<string>|null $subscribe List of topic selectors to allow subscribing to
     * @param array<string>|null $publish List of topic selectors to allow publishing to
     * @param array<string, mixed> $additionalClaims Additional claims to include in the JWT payload
     * @return string The generated JWT token
     */
    public function create(
        ?array $subscribe = [],
        ?array $publish = [],
        array $additionalClaims = [],
    ): string {
        $mercureClaim = [];

        if (!empty($subscribe)) {
            $mercureClaim['subscribe'] = $subscribe;
        }

        if (!empty($publish)) {
            $mercureClaim['publish'] = $publish;
        }

        $payload = array_merge(
            [
                'mercure' => $mercureClaim,
            ],
            $additionalClaims,
        );

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }
}
