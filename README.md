[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://github.com/josbeir/cakephp-mercure)
[![Build Status](https://github.com/josbeir/cakephp-mercure/actions/workflows/ci.yml/badge.svg)](https://github.com/josbeir/cakephp-mercure/actions)
[![codecov](https://codecov.io/github/josbeir/cakephp-mercure/graph/badge.svg?token=4VGWJQTWH5)](https://codecov.io/github/josbeir/cakephp-mercure)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://www.php.net/releases/8.2/en.php)
[![Packagist Downloads](https://img.shields.io/packagist/dt/josbeir/cakephp-mercure)](https://packagist.org/packages/josbeir/cakephp-mercure)

# CakePHP Mercure Plugin

Push real-time updates to clients using the Mercure protocol.

[![Mercure](https://mercure.rocks/static/logo.svg)](https://mercure.rocks/)

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
  - [Installing the Plugin](#installing-the-plugin)
  - [Running a Mercure Hub](#running-a-mercure-hub)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
  - [Publishing Updates](#publishing-updates)
  - [Subscribing to Updates](#subscribing-to-updates)
- [Authorization](#authorization)
  - [Publishing Private Updates](#publishing-private-updates)
  - [Setting Authorization Cookies](#setting-authorization-cookies)
    - [Using the Component (Recommended)](#using-the-component-recommended)
    - [Using the View Helper](#using-the-view-helper)
    - [Using the Facade (Alternative)](#using-the-facade-alternative)
- [Mercure Discovery](#mercure-discovery)
  - [Using the View Helper](#using-the-view-helper)
  - [Using the Facade](#using-the-facade)
  - [Using Middleware](#using-middleware)
- [Advanced Configuration](#advanced-configuration)
  - [JWT Token Strategies](#jwt-token-strategies)
  - [HTTP Client Options](#http-client-options)
  - [Cookie Configuration](#cookie-configuration)
- [Testing](#testing)
- [API Reference](#api-reference)
  - [Publisher](#publisher)
  - [MercureComponent](#mercurecomponent)
  - [Authorization](#authorization-1)
  - [MercureHelper](#mercurehelper)
  - [Update](#update)
  - [MercureDiscoveryMiddleware](#mercurediscoverymiddleware)
- [Contributing](#contributing)
- [License](#license)

## Overview

This plugin provides integration between CakePHP applications and the [Mercure protocol](https://mercure.rocks/), enabling real-time push capabilities for modern web applications.

Mercure is an open protocol built on top of Server-Sent Events (SSE) that allows you to:

- Push updates from your server to clients in real-time
- Create live-updating UIs without complex WebSocket infrastructure
- Broadcast data changes to multiple connected users
- Handle authorization for private updates
- Automatically reconnect with missed update retrieval

Common use cases include live dashboards, collaborative editing, real-time notifications, and chat applications.

## Installation

### Installing the Plugin

Install the plugin using Composer:

```bash
composer require josbeir/cakephp-mercure
```

Load the plugin in your `Application.php`:

```php
// src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Mercure');
}
```

Alternatively, you can add it to `config/plugins.php`:

```php
// config/plugins.php
return [
    'Mercure' => [],
];
```

### Running a Mercure Hub

Mercure requires a hub server to manage persistent SSE connections. Download the official hub from [Mercure.rocks](https://mercure.rocks/).

For development, you can run the hub using Docker:

```bash
docker run -d \
    -e SERVER_NAME=:3000 \
    -e MERCURE_PUBLISHER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
    -e MERCURE_SUBSCRIBER_JWT_KEY='!ChangeThisMercureHubJWTSecretKey!' \
    -p 3000:3000 \
    dunglas/mercure
```

If you're using DDEV, you can install the Mercure add-on:

```bash
ddev get Rindula/ddev-mercure
```

For more information, see the [DDEV Mercure add-on](https://addons.ddev.com/addons/Rindula/ddev-mercure).

The hub will be available at `http://localhost:3000/.well-known/mercure`.

## Configuration

The plugin includes a default configuration file with all available options. The configuration is automatically loaded from the plugin's `config/mercure.php` file.

Set the required environment variables in your `.env` file:

```env
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
```

> [!NOTE]
> If your Mercure hub is running on a different subdomain than your CakePHP application, you need to set the cookie domain to the top-level domain:
>
> ```env
> # For cross-subdomain authorization
> MERCURE_COOKIE_DOMAIN=.example.com
> ```
>
> This allows the authorization cookie to be accessible by both your application and the Mercure hub when they are on different subdomains of the same parent domain.

**Configuration structure:**

```php
'Mercure' => [
    'url' => 'http://localhost:3000/.well-known/mercure',
    'public_url' => null, // Optional, defaults to 'url'
    'jwt' => [
        'secret' => '!ChangeThisMercureHubJWTSecretKey!',
        'algorithm' => 'HS256',
        'publish' => ['*'],
        'subscribe' => ['*'],
    ],
]
```

The `url` is used by your CakePHP application to publish updates. Set `public_url` when clients need to connect to a different URL (e.g., when using Docker with internal networking).

To customize the configuration, copy the plugin's config file to your application:

```bash
cp vendor/josbeir/cakephp-mercure/config/mercure.php config/mercure.php
```

## Basic Usage

The plugin provides multiple integration points depending on your use case:

- **Controllers**: Use the `MercureComponent` for the easiest integration (recommended)
- **Templates**: Use the `MercureHelper` for view-layer authorization and URLs
- **Services/Commands**: Use the `Publisher` facade for publishing updates
- **Manual Control**: Use the `Authorization` facade when you need direct response manipulation

### Publishing Updates

Use the `Publisher` facade to send updates to the Mercure hub:

```php
use Mercure\Publisher;
use Mercure\Update;

// In a controller or service
$update = new Update(
    topics: 'https://example.com/books/1',
    data: json_encode(['status' => 'OutOfStock'])
);

Publisher::publish($update);
```

The `topics` parameter identifies the resource being updated. It should be a unique IRI (Internationalized Resource Identifier), typically the resource's URL.

You can publish to multiple topics simultaneously:

```php
$update = new Update(
    topics: [
        'https://example.com/books/1',
        'https://example.com/notifications',
    ],
    data: json_encode(['message' => 'Book status changed'])
);

Publisher::publish($update);
```

### Subscribing to Updates

The plugin provides a View Helper to generate Mercure URLs in your templates.

First, load the helper in your controller or `AppView`:

```php
// In src/View/AppView.php
public function initialize(): void
{
    parent::initialize();
    $this->loadHelper('Mercure.Mercure');
}
```

Then subscribe to updates from your templates:

```php
// In your template
<div id="book-status">Available</div>

<script>
const eventSource = new EventSource('<?= $this->Mercure->url('https://example.com/books/1') ?>');

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    document.getElementById('book-status').textContent = data.status;
};
</script>
```

Subscribe to multiple topics:

```php
<script>
const url = '<?= $this->Mercure->url([
    'https://example.com/books/1',
    'https://example.com/notifications'
]) ?>';

const eventSource = new EventSource(url);
eventSource.onmessage = (event) => {
    console.log('Update received:', event.data);
};
</script>
```

If you need to access the Mercure URL from an external JavaScript file, store it in a data element:

```php
<script type="application/json" id="mercure-url">
<?= json_encode(
    $this->Mercure->url(['https://example.com/books/1']),
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
) ?>
</script>
```

Then retrieve it from your JavaScript:

```javascript
const url = JSON.parse(document.getElementById('mercure-url').textContent);
const eventSource = new EventSource(url);
eventSource.onmessage = (event) => {
    console.log('Update received:', event.data);
};
```

The special topic `*` matches all updates (use with caution in production).

## Authorization

### Publishing Private Updates

Mark updates as private to restrict access to authorized subscribers:

```php
$update = new Update(
    topics: 'https://example.com/users/123/messages',
    data: json_encode(['text' => 'Private message']),
    private: true
);

Publisher::publish($update);
```

Private updates are only delivered to subscribers with valid JWT tokens containing matching topic selectors.

### Setting Authorization Cookies

#### Using the Component (Recommended)

The easiest way to manage authorization in controllers is using the `MercureComponent`:

```php
class BooksController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Mercure.Mercure');
    }
    
    public function view($id)
    {
        $book = $this->Books->get($id);
        
        // Authorize subscriber for private topics
        $this->Mercure->authorize(["https://example.com/books/{$id}"]);
        
        // Or with additional JWT claims
        $this->Mercure->authorize(
            subscribe: ["https://example.com/books/{$id}"],
            additionalClaims: ['user_id' => $this->request->getAttribute('identity')->id]
        );
        
        $this->set('book', $book);
    }
    
    public function logout()
    {
        // Clear authorization on logout
        $this->Mercure->clearAuthorization();
        
        return $this->redirect(['action' => 'login']);
    }
}
```

The component automatically handles response modifications and provides a clean, fluent interface. You can also enable automatic discovery headers:

```php
// In AppController
$this->loadComponent('Mercure.Mercure', [
    'autoDiscover' => true,  // Automatically add discovery headers
]);
```

#### Using the View Helper

For template-based authorization, use the `MercureHelper`:

```php
// In your template
<script>
const url = '<?= $this->Mercure->url(
    topics: ['https://example.com/books/<?= $book->id ?>'],
    subscribe: ['https://example.com/books/<?= $book->id ?>']
) ?>';

const eventSource = new EventSource(url, {
    withCredentials: true
});

eventSource.onmessage = (event) => {
    console.log('Private update:', event.data);
};
</script>
```

#### Using the Facade (Alternative)

For more control or when not using controllers, you can use the `Authorization` facade directly:

```php
use Mercure\Authorization;

public function view($id)
{
    $book = $this->Books->get($id);

    // Allow this user to subscribe to updates for this book
    $response = Authorization::setCookie(
        $this->response,
        subscribe: ["https://example.com/books/{$id}"]
    );

    $this->set('book', $book);
    return $response;
}
```

The cookie must be set before establishing the EventSource connection. The Mercure hub and your CakePHP application should share the same domain (different subdomains are allowed).

## Mercure Discovery

The Mercure protocol supports automatic hub discovery via HTTP Link headers. This allows clients to discover the hub URL without hardcoding it, making your application more flexible and following the Mercure specification.

### Using the View Helper

Add the discovery header in your templates or layouts:

```php
// In your layout or template
<?php $this->Mercure->discover(); ?>
```

This adds a `Link` header to the response:

```
Link: <https://mercure.example.com/.well-known/mercure>; rel="mercure"
```

Clients can then discover the hub URL from the response headers:

```javascript
fetch('/api/resource')
    .then(response => {
        const linkHeader = response.headers.get('Link');
        // Parse the Link header to extract the Mercure hub URL
        const match = linkHeader.match(/<([^>]+)>;\s*rel="mercure"/);
        if (match) {
            const hubUrl = match[1];
            const eventSource = new EventSource(hubUrl + '?topic=/api/resource');
        }
    });
```

### Using the Facade

You can also add the discovery header manually from controllers:

```php
use Mercure\Authorization;

public function index()
{
    $response = Authorization::addDiscoveryHeader($this->response);
    
    // Your controller logic here
    
    return $response;
}
```

### Using Middleware

For automatic discovery headers on all responses, add the middleware to your application:

```php
// In src/Application.php
use Mercure\Http\Middleware\MercureDiscoveryMiddleware;

public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
{
    $middlewareQueue
        // ... other middleware
        ->add(new MercureDiscoveryMiddleware());

    return $middlewareQueue;
}
```

The middleware automatically adds the `Link` header with `rel="mercure"` to all responses, making the hub URL discoverable by any client.

> [!TIP]
> The discovery header uses the `public_url` configuration (or falls back to `url` if not set), ensuring clients always receive the correct publicly-accessible hub URL.

## Advanced Configuration

### JWT Token Strategies

The plugin supports multiple JWT generation strategies:

**1. Secret-based (default):**

```php
'jwt' => [
    'secret' => env('MERCURE_JWT_SECRET'),
    'algorithm' => 'HS256',
    'publish' => ['*'],
    'subscribe' => ['*'],
]
```

**2. Static token:**

```php
'jwt' => [
    'value' => env('MERCURE_JWT_TOKEN'),
]
```

**3. Custom provider:**

```php
'jwt' => [
    'provider' => \App\Mercure\CustomTokenProvider::class,
]
```

Implement `Mercure\Jwt\TokenProviderInterface`:

```php
namespace App\Mercure;

use Mercure\Jwt\TokenProviderInterface;

class CustomTokenProvider implements TokenProviderInterface
{
    public function getJwt(): string
    {
        // Generate and return JWT token
        return $this->generateToken();
    }
}
```

**4. Custom factory:**

```php
'jwt' => [
    'factory' => \App\Mercure\CustomTokenFactory::class,
    'secret' => env('MERCURE_JWT_SECRET'),
    'publish' => ['*'],
]
```

Implement `Mercure\Jwt\TokenFactoryInterface`:

```php
namespace App\Mercure;

use Mercure\Jwt\TokenFactoryInterface;

class CustomTokenFactory implements TokenFactoryInterface
{
    public function __construct(
        private string $secret,
        private string $algorithm
    ) {}

    public function create(array $subscribe = [], array $publish = [], array $additionalClaims = []): string
    {
        // Create and return JWT token
    }
}
```

### HTTP Client Options

Configure the HTTP client used to communicate with the Mercure hub:

```php
'http_client' => [
    'timeout' => 30,
    'ssl_verify_peer' => false, // For local development only
]
```

### Cookie Configuration

Customize the authorization cookie settings. Options are passed directly to CakePHP's `Cookie::create()` method.

```php
'cookie' => [
    'name' => 'mercureAuthorization',
    'expires' => new DateTime('+1 hour'), // Cookie expiration time
    'domain' => '.example.com', // For cross-subdomain access
    'path' => '/',
    'secure' => true, // HTTPS only in production
    'samesite' => 'Lax', // or 'Strict', 'None'
    'httponly' => true,
]
```

**Supported cookie options:**
- `name` - Cookie name (default: `mercureAuthorization`)
- `expires` - Expiration time as `DateTime` object or `null` for session cookie
- `domain` - Cookie domain (use `.example.com` for cross-subdomain access)
- `path` - Cookie path (default: `/`)
- `secure` - Send only over HTTPS (default: `false`)
- `httponly` - Prevent JavaScript access (default: `true`)
- `samesite` - CSRF protection: `Strict`, `Lax`, or `None` (default: `Lax`)

For more details, see the [CakePHP Cookie documentation](https://book.cakephp.org/5/en/controllers/request-response.html#setting-cookies).

## Testing

For testing, mock the Publisher service to avoid actual HTTP calls:

```php
use Mercure\Publisher;
use Mercure\PublisherInterface;

// In your test
public function testPublishing(): void
{
    $mockPublisher = $this->createMock(PublisherInterface::class);
    $mockPublisher->expects($this->once())
        ->method('publish')
        ->willReturn(true);

    Publisher::setInstance($mockPublisher);

    // Test your code that publishes updates
    $this->MyService->doSomething();

    // Clean up
    Publisher::clear();
}
```

Similarly for Authorization:

```php
use Mercure\Authorization;
use Mercure\AuthorizationInterface;

public function testAuthorization(): void
{
    $mockAuth = $this->createMock(AuthorizationInterface::class);
    Authorization::setInstance($mockAuth);

    // Your tests here

    Authorization::clear();
}
```

## API Reference

### Publisher

| Method | Returns | Description |
|--------|---------|-------------|
| `publish(Update $update)` | `bool` | Publish an update to the hub |
| `getHubUrl()` | `string` | Get the server-side hub URL |
| `getPublicUrl()` | `string` | Get the client-side hub URL |
| `setInstance(PublisherInterface $publisher)` | `void` | Set custom instance (for testing) |
| `clear()` | `void` | Clear singleton instance |

### MercureComponent

Controller component for simplified Mercure integration with automatic dependency injection.

**Loading the Component:**

```php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Mercure.Mercure', [
        'autoDiscover' => true,  // Optional: auto-add discovery headers
    ]);
}
```

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `authorize(array $subscribe, array $additionalClaims)` | `$this` | Set authorization cookie |
| `clearAuthorization()` | `$this` | Clear authorization cookie |
| `discover()` | `$this` | Add Mercure discovery Link header |
| `getCookieName()` | `string` | Get the cookie name |
| `getHubUrl()` | `string` | Get the hub URL |
| `getPublicUrl()` | `string` | Get the public hub URL |

All methods support fluent chaining:

```php
$this->Mercure->authorize(['/feeds/123'])->discover();
```

### Authorization

Static facade for direct authorization management (alternative to component).

| Method | Returns | Description |
|--------|---------|-------------|
| `setCookie(Response $response, array $subscribe, array $additionalClaims)` | `Response` | Set authorization cookie |
| `clearCookie(Response $response)` | `Response` | Clear authorization cookie |
| `addDiscoveryHeader(Response $response)` | `Response` | Add Mercure discovery Link header |
| `getCookieName()` | `string` | Get the cookie name |
| `getHubUrl()` | `string` | Get the hub URL |
| `getPublicUrl()` | `string` | Get the public hub URL |

### MercureHelper

| Method | Returns | Description |
|--------|---------|-------------|
| `url(array\|string\|null $topics, array $subscribe, array $additionalClaims)` | `string` | Get hub URL and optionally set authorization |
| `getHubUrl(array $topics, array $options)` | `string` | Get hub URL with optional topics |
| `authorize(array $subscribe, array $additionalClaims)` | `void` | Set authorization cookie |
| `clearAuthorization()` | `void` | Clear authorization cookie |
| `discover()` | `void` | Add Mercure discovery Link header |
| `getCookieName()` | `string` | Get the cookie name |

### Update

**Constructor:**

```php
new Update(
    string|array $topics,
    string $data,
    bool $private = false,
    ?string $id = null,
    ?string $type = null,
    ?int $retry = null
)
```

**Constructor Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$topics` | `string\|array` | Topic IRI(s) for the update |
| `$data` | `string` | Update content (typically JSON) |
| `$private` | `bool` | Whether the update requires authorization |
| `$id` | `?string` | Optional SSE event ID |
| `$type` | `?string` | Optional SSE event type |
| `$retry` | `?int` | Optional reconnection time in milliseconds |

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `getTopics()` | `array` | Get topics |
| `getData()` | `string` | Get data |
| `isPrivate()` | `bool` | Check if private |
| `getId()` | `?string` | Get event ID |
| `getType()` | `?string` | Get event type |
| `getRetry()` | `?int` | Get retry value |

### MercureDiscoveryMiddleware

A PSR-15 middleware that automatically adds the Mercure discovery Link header to all responses.

**Usage:**

```php
// In src/Application.php
use Mercure\Http\Middleware\MercureDiscoveryMiddleware;

public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
{
    $middlewareQueue->add(new MercureDiscoveryMiddleware());
    return $middlewareQueue;
}
```

The middleware adds a `Link` header to every response:

```
Link: <https://mercure.example.com/.well-known/mercure>; rel="mercure"
```

This allows clients to automatically discover the Mercure hub URL without hardcoding it in your application.

---

For more information about the Mercure protocol, visit [mercure.rocks](https://mercure.rocks/).

## Contributing

Contributions are welcome! Please follow these guidelines:

1. **Code Quality**: Ensure all code passes quality checks:
   ```bash
   composer cs-check    # Check code style
   composer stan        # Run PHPStan analysis
   composer test        # Run tests
   ```

2. **Code Style**: Follow CakePHP coding standards. Use `composer cs-fix` to automatically fix style issues.

3. **Tests**: Add tests for new features and ensure all tests pass.

4. **Documentation**: Update the README and inline documentation as needed.

5. **Pull Requests**: Submit PRs against the `main` branch with a clear description of changes.

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
