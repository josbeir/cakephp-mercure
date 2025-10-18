<?php
declare(strict_types=1);

/**
 * Mercure Plugin Configuration
 *
 * This file contains configuration for the Mercure publisher plugin.
 */

return [
    'Mercure' => [
        /**
         * Mercure Hub URL (Server-side)
         *
         * The URL of your Mercure hub's publish endpoint for server-side publishing.
         * This is used by the Publisher to send updates to the hub.
         * Default: http://localhost:3000/.well-known/mercure
         */
        'url' => env('MERCURE_URL', 'http://localhost:3000/.well-known/mercure'),

        /**
         * Mercure Public URL (Client-side)
         *
         * The publicly accessible URL for client-side EventSource connections.
         * If not set, falls back to 'url'.
         *
         * Use this when your hub has different URLs for:
         * - Internal server-to-server communication (url)
         * - External client-to-server connections (public_url)
         *
         * Example:
         * - url: http://mercure:3000/.well-known/mercure (internal Docker network)
         * - public_url: https://mercure.example.com/.well-known/mercure (public domain)
         *
         * Default: null (uses url)
         */
        'public_url' => env('MERCURE_PUBLIC_URL'),

        /**
         * JWT Configuration
         *
         * Configure JWT authentication for the Mercure hub. Multiple strategies supported:
         *
         * 1. Secret-based (recommended):
         *    - 'secret' => Your JWT signing secret
         *    - 'algorithm' => Signing algorithm (default: HS256)
         *    - 'publish' => Topics allowed to publish (['*'] = all)
         *    - 'subscribe' => Topics allowed to subscribe
         *    - 'additional_claims' => Extra JWT claims (exp, iss, etc.)
         *
         * 2. Static token:
         *    - 'value' => Pre-generated JWT token
         *
         * 3. Custom provider:
         *    - 'provider' => Custom class implementing TokenProviderInterface
         *
         * 4. Custom factory:
         *    - 'factory' => Custom class implementing TokenFactoryInterface
         *
         * 5. Backward compatible:
         *    - Use 'publisher_jwt' at root level (treated as secret)
         */
        'jwt' => [
            'secret' => env('MERCURE_JWT_SECRET', ''),
            'algorithm' => 'HS256',
            'publish' => ['*'],
            'subscribe' => ['*'],
            'additional_claims' => [],
        ],

        /**
         * HTTP Client Options
         *
         * Additional options for the HTTP client used to communicate with the hub.
         * Pass any options supported by CakePHP's HTTP Client.
         *
         * Example for local development (disable SSL verification):
         * 'http_client' => [
         *     'ssl_verify_peer' => false,
         *     'ssl_verify_host' => false,
         * ]
         */
        'http_client' => [],

        /**
         * Cookie Configuration for Subscriber Authorization
         *
         * Configure the authorization cookie used for client-side EventSource subscriptions
         * to private topics. The cookie contains a JWT token that authenticates the subscriber.
         *
         * Security recommendations:
         * - Enable 'secure' in production (requires HTTPS)
         * - Use 'strict' or 'lax' for sameSite in production
         * - Set appropriate domain for cross-subdomain access
         * - Keep lifetime as short as practical for your use case
         */
        'cookie' => [
            /**
             * Cookie name for the authorization token
             * Default: mercureAuthorization
             */
            'name' => env('MERCURE_COOKIE_NAME', 'mercureAuthorization'),

            /**
             * Cookie lifetime in seconds
             * Default: 3600 (1 hour)
             * Set to 0 for session cookie (expires when browser closes)
             */
            'lifetime' => (int)env('MERCURE_COOKIE_LIFETIME', 3600),

            /**
             * Cookie domain
             * Default: null (current domain only)
             * Set to '.example.com' for cross-subdomain access
             */
            'domain' => env('MERCURE_COOKIE_DOMAIN'),

            /**
             * Cookie path
             * Default: /
             */
            'path' => env('MERCURE_COOKIE_PATH', '/'),

            /**
             * Secure flag (HTTPS only)
             * Default: false (for local development)
             * MUST be true in production with HTTPS
             */
            'secure' => filter_var(env('MERCURE_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN),

            /**
             * SameSite attribute
             * Options: 'strict', 'lax', 'none', or null
             * Default: 'lax'
             * Use 'strict' for maximum security, 'lax' for general use
             */
            'sameSite' => env('MERCURE_COOKIE_SAMESITE', 'lax'),

            /**
             * HttpOnly flag
             * Default: true (recommended for security)
             * When true, cookie is not accessible via JavaScript
             */
            'httpOnly' => filter_var(env('MERCURE_COOKIE_HTTPONLY', true), FILTER_VALIDATE_BOOLEAN),
        ],
    ],
];
