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
         * Options are passed directly to CakePHP's HTTP Client for communicating with the Mercure hub.
         *
         * @see https://book.cakephp.org/5/en/core-libraries/httpclient.html#request-options
         * @see https://api.cakephp.org/5.0/class-Cake.Http.Client.html
         */
        'http_client' => [],

        /**
         * Cookie Configuration for Subscriber Authorization
         *
         * Options are passed directly to CakePHP's Cookie::create() method.
         * The cookie contains a JWT token that authenticates subscribers to private topics.
         *
         * Supported keys: name, path, domain, secure, httponly, samesite, expires
         *
         * @see https://book.cakephp.org/5/en/controllers/request-response.html#setting-cookies
         * @see https://api.cakephp.org/5.0/class-Cake.Http.Cookie.Cookie.html
         */
        'cookie' => [
            'name' => env('MERCURE_COOKIE_NAME', 'mercureAuthorization'),
            'domain' => env('MERCURE_COOKIE_DOMAIN'),
        ],

        /**
         * Mercure Discovery
         *
         * The plugin supports Mercure hub discovery via Link headers (rel="mercure").
         * This allows clients to automatically discover the hub URL.
         *
         * Three ways to add discovery headers:
         *
         * 1. View Helper (in templates):
         *    $this->Mercure->discover();
         *
         * 2. Controller (manual):
         *    $response = Authorization::addDiscoveryHeader($response);
         *
         * 3. Middleware (automatic for all responses):
         *    Add to Application.php middleware stack:
         *    $middlewareQueue->add(new \Mercure\Http\Middleware\MercureDiscoveryMiddleware());
         *
         /**
          * The discovery header will contain the public_url (or url if not set).
          */

         /**
          * View Class for ViewUpdate
          *
          * Custom View class to use when rendering templates/elements in ViewUpdate.
          * Must extend Cake\View\View.
          *
          * Default: Auto-detected (App\View\AppView if exists, otherwise Cake\View\View)
          *
          * You can override this to use a custom View class:
          * 'view_class' => \App\View\CustomView::class,
          */
     ],
 ];
