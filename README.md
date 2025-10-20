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
    - [Publishing JSON Data](#publishing-json-data)
    - [Publishing Rendered Views](#publishing-rendered-views)
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
  - [JsonUpdate](#jsonupdate)
  - [ViewUpdate](#viewupdate)
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

> [!IMPORTANT]
> **Minimum Requirements:**
> - PHP 8.2 or higher
> - CakePHP 5.0.1 or higher

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

> [!TIP]
> **Using FrankenPHP?** You're good to go! FrankenPHP has Mercure built in—no separate hub needed. See the [FrankenPHP Mercure documentation](https://frankenphp.dev/docs/mercure/) for details.

## Configuration

The plugin comes with sensible defaults and multiple configuration options.

**Quick Setup (Environment Variables):**

For development, the fastest way to get started is using environment variables in your `.env` file:

```env
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
```

**Configuration Files:**

The plugin loads configuration in this order:

1. **Plugin defaults** - `vendor/josbeir/cakephp-mercure/config/mercure.php` (loaded automatically)
2. **Your overrides** - `config/app_mercure.php` (optional, loaded after plugin defaults)

Create `config/app_mercure.php` in your project to customize any settings. Your values will override the plugin defaults.

**Cross-Subdomain Setup:**

> [!NOTE]
> If your Mercure hub runs on a different subdomain than your CakePHP application (e.g., `hub.example.com` vs `app.example.com`), you must configure the cookie domain:
>
> ```env
> # Allow cookie sharing across subdomains
> MERCURE_COOKIE_DOMAIN=.example.com
> ```
>
> This enables the authorization cookie to be accessible by both your application and the Mercure hub. Without this setting, authorization will fail for cross-subdomain requests.

For a complete list of available environment variables, see the plugin's `config/mercure.php` [file](https://github.com/josbeir/cakephp-mercure/blob/main/config/mercure.php).

## Basic Usage

The plugin provides multiple integration points depending on your use case:

- **Templates**: Use the `MercureHelper` for the easiest, self-contained integration (handles authorization and URLs)
- **Controllers**: Use the `MercureComponent` for centralized authorization and separation of concerns (recommended for testability)
- **Services/Commands**: Use the `Publisher` facade for publishing updates
- **Manual Control**: Use the `Authorization` facade when you need direct response manipulation

> **Note:** Facades (`Publisher`, `Authorization`) can be used in any context where a CakePHP component or helper does not fit, such as in queue jobs, commands, models, or other non-HTTP or background processing code. This makes them ideal for use outside of controllers and views.

### Choosing Your Authorization Strategy

Pick the approach that best fits your workflow:

| Scenario | Recommended Approach | Method to Use |
|----------|---------------------|---------------|
| **Authorize in controller, display URL in template** | `MercureComponent` + `url()` | `$this->Mercure->authorize()` in controller, `$this->Mercure->url($topics)` in template |
| **Authorize directly in template** | `MercureHelper::url()` with `subscribe` | `$this->Mercure->url($topics, $subscribe)` |
| **Public topics (no authorization)** | `url()` | `$this->Mercure->url($topics)` |
| **Manual response control** | `Authorization` facade | `Authorization::setCookie($response, $subscribe)` |

> [!TIP]
> * **Easiest:** Use `MercureHelper::url($topics, $subscribe)` directly in templates for quick setup.
> * **Best Practice:** For larger applications, handle authorization in controllers using `MercureComponent`, then use `url($topics)` in templates. This keeps authorization logic centralized and testable.

### Publishing Updates

Use the `Publisher` facade to send updates to the Mercure hub:

```php
use Mercure\Publisher;
use Mercure\Update\Update;

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

> [!TIP]
> **Using MercureComponent in Controllers:** If you're publishing from a controller, the `MercureComponent` provides convenient methods that eliminate the need to manually create Update objects or call the Publisher facade:
>
> ```php
> // In your controller
> public function initialize(): void
> {
>     parent::initialize();
>     $this->loadComponent('Mercure.Mercure');
> }
>
> public function update($id)
> {
>     $book = $this->Books->get($id);
>     $book = $this->Books->patchEntity($book, $this->request->getData());
>     $this->Books->save($book);
>
>     // Publish JSON directly - no need for Publisher facade
>     $this->Mercure->publishJson(
>         topics: "/books/{$id}",
>         data: ['status' => $book->status, 'title' => $book->title]
>     );
>
>     // Or publish a rendered element
>     $this->Mercure->publishView(
>         topics: "/books/{$id}",
>         element: 'Books/item',
>         data: ['book' => $book]
>     );
> }
> ```
>
> See the [MercureComponent API Reference](#mercurecomponent) for all available methods.

#### Publishing JSON Data

For convenience when publishing JSON data, use the `JsonUpdate` class which automatically encodes arrays and objects to JSON:

```php
use Mercure\Publisher;
use Mercure\Update\JsonUpdate;

// Simple array - no need to call json_encode()
$update = JsonUpdate::create(
    topics: 'https://example.com/books/1',
    data: ['status' => 'OutOfStock', 'quantity' => 0]
);

Publisher::publish($update);

// Or use the fluent builder pattern
$update = (new JsonUpdate('https://example.com/books/1'))
    ->data(['status' => 'OutOfStock', 'quantity' => 0])
    ->build();

Publisher::publish($update);
```

You can customize JSON encoding options:

```php
// With custom JSON encoding options
$update = JsonUpdate::create(
    topics: 'https://example.com/books/1',
    data: ['title' => 'Book & Title', 'price' => 19.99],
    jsonOptions: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

// Or using the fluent builder
$update = (new JsonUpdate('https://example.com/books/1'))
    ->data(['title' => 'Book & Title', 'price' => 19.99])
    ->jsonOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ->build();

Publisher::publish($update);
```

For private updates and event metadata:

```php
$update = JsonUpdate::create(
    topics: 'https://example.com/users/123/notifications',
    data: ['message' => 'New notification', 'unread' => 5],
    private: true
);

// Or chain multiple options with the fluent builder
$update = (new JsonUpdate('https://example.com/books/1'))
    ->data(['title' => 'New Book', 'price' => 29.99])
    ->private()
    ->id('book-123')
    ->type('book.created')
    ->retry(5000)
    ->build();

Publisher::publish($update);
```

#### Publishing Rendered Views

Use the `ViewUpdate` class to automatically render CakePHP views or elements and publish the rendered HTML:

```php
use Mercure\Publisher;
use Mercure\Update\ViewUpdate;

// Render an element
$update = ViewUpdate::create(
    topics: 'https://example.com/books/1',
    element: 'Books/item',
    viewVars: ['book' => $book]
);

// Or use the fluent builder pattern
$update = (new ViewUpdate('https://example.com/books/1'))
    ->element('Books/item')
    ->viewVars(['book' => $book])
    ->build();

Publisher::publish($update);
```

You can also render full templates:

```php
// Render a template
$update = ViewUpdate::create(
    topics: 'https://example.com/notifications',
    template: 'Notifications/item',
    viewVars: ['notification' => $notification]
);

// Or with the fluent builder - add view options too
$update = (new ViewUpdate('https://example.com/notifications'))
    ->template('Notifications/item')
    ->viewVars(['notification' => $notification])
    ->viewOptions(['plugin' => 'MyPlugin'])
    ->build();

Publisher::publish($update);
```

For private updates with event metadata:

```php
$update = ViewUpdate::create(
    topics: 'https://example.com/users/123/messages',
    element: 'Messages/item',
    viewVars: ['message' => $message],
    private: true
);

// Or chain all options with the fluent builder
$update = (new ViewUpdate('https://example.com/users/123/messages'))
    ->element('Messages/item')
    ->viewVars(['message' => $message])
    ->private()
    ->id('msg-456')
    ->type('message.new')
    ->build();

Publisher::publish($update);
```

> [!NOTE]
> **View Class Configuration:** By default, `ViewUpdate` uses CakePHP's automatic view class selection (your `AppView` if it exists, otherwise the base `View` class). You can override this by setting `view_class` in your configuration:
>
> ```php
> // In config/app_mercure.php
> return [
>     'Mercure' => [
>         'view_class' => \App\View\CustomView::class,
>         // ... other config
>     ],
> ];
> ```

### Subscribing to Updates

The plugin provides a View Helper to generate Mercure URLs in your templates.

> [!IMPORTANT]
> **Authorization Consideration:** When using the `MercureHelper`, understand the difference:
> - `$this->Mercure->url($topics)` - Gets URL **without authorization** (when `$subscribe` is omitted)
> - `$this->Mercure->url($topics, $subscribe)` - Gets URL **and sets authorization cookie** (when `$subscribe` is provided)

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
// For public topics (no authorization needed)
const eventSource = new EventSource('<?= $this->Mercure->url(['https://example.com/books/1']) ?>');

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    document.getElementById('book-status').textContent = data.status;
};
</script>
```

Subscribe to multiple topics:

```php
<script>
// Subscribe to multiple topics
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

#### Using the Component (Recommended for Separation of Concerns)

For centralized, testable authorization logic, use the `MercureComponent` in controllers. Topics added via the component are automatically available in your views:

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
        $userId = $this->request->getAttribute('identity')->id;

        // Fluent authorization with builder pattern
        $this->Mercure
            ->addSubscribe("https://example.com/books/{$id}", ['sub' => $userId])
            ->addSubscribe("https://example.com/notifications/*", ['role' => 'user'])
            ->authorize()
            ->discover();

        // Or direct authorization (backward compatible)
        $this->Mercure->authorize(
            subscribe: ["https://example.com/books/{$id}"],
            additionalClaims: ['sub' => $userId]
        );

        // Or build up gradually
        $this->Mercure
            ->addSubscribe("https://example.com/books/{$id}")
            ->addSubscribe("https://example.com/user/{$userId}/updates", ['sub' => $userId])
            ->authorize(); // Uses accumulated topics and claims

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

The component provides separation of concerns (authorization in controller, URLs in template) and is fully testable. You can also enable automatic discovery headers:

```php
// In AppController
$this->loadComponent('Mercure.Mercure', [
    'autoDiscover' => true,  // Automatically add discovery headers
]);
```

#### Authorization Builder Pattern

The component supports a fluent builder pattern for authorization, making it easy to build up topics and claims:

```php
// Build authorization gradually
$this->Mercure
    ->addSubscribe('/books/123', ['sub' => $userId])
    ->addSubscribe('/notifications/*', ['role' => 'admin'])
    ->authorize();

// Add multiple topics at once
$this->Mercure->addSubscribes(
    ['/books/123', '/notifications/{id}'],
    ['sub' => $userId, 'role' => 'admin']
);

// Mix with direct parameters
$this->Mercure
    ->addSubscribe('/books/123')
    ->authorize(['/notifications/{id}'], ['sub' => $userId]);

// Chain with topic management and discovery
$this->Mercure
    ->addTopic('/books/123') // For EventSource subscription
    ->addSubscribe('/books/123', ['sub' => $userId]) // For authorization
    ->authorize()
    ->discover();
```

#### Using the View Helper (Easiest)

For the simplest, self-contained approach, use the `MercureHelper::url()` method with the `subscribe` parameter. This **automatically handles both authorization and URL generation** in one call:

```php
// In your template
<script>
// url() with subscribe parameter SETS AUTHORIZATION COOKIE
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

> [!NOTE]
> **Separation of Concerns:** The `url()` method **only sets authorization when the `$subscribe` parameter is provided**. If you're already handling authorization in your controller (via `MercureComponent`), simply omit the `$subscribe` parameter:
>
> ```php
> // Authorization already set in controller
> $this->Mercure->authorize(['/books/123']);
>
> // In template: just get the URL (no duplicate authorization)
> const url = '<?= $this->Mercure->url(['/books/123']) ?>';
> ```

#### Setting Default Topics

You can configure default topics that will be automatically merged with any topics you provide to `url()`. This is useful when you want certain topics (like notifications or global alerts) to be included in every subscription:

```php
// In your controller or AppView
public function initialize(): void
{
    parent::initialize();

    // Load helper with default topics
    $this->loadHelper('Mercure', [
        'defaultTopics' => [
            'https://example.com/notifications',
            'https://example.com/alerts'
        ]
    ]);
}
```

Now every call to `url()` will automatically include these default topics:

```php
// In your template
<script>
// This will subscribe to: /notifications, /alerts, AND /books/123
const url = '<?= $this->Mercure->url(['/books/123']) ?>';
const eventSource = new EventSource(url, { withCredentials: true });
</script>

// You can also add topics dynamically:
$this->Mercure->addTopic('/user/' . $userId . '/messages');
$this->Mercure->addTopics(['/books/456', '/comments/789']);

// These will be merged with configured defaults
const url = '<?= $this->Mercure->url(['/books/123']) ?>';
// Result includes: /notifications, /alerts, /user/{id}/messages, /books/456, /comments/789, AND /books/123
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

The authorization cookie contains a JWT token that authenticates subscribers to private topics. JWT expiry is automatically calculated based on cookie lifetime settings.

```php
'cookie' => [
    'name' => 'mercureAuthorization',

    // Lifetime in seconds (0 for session cookie)
    'lifetime' => 3600,  // 1 hour

    // Or use explicit expiry datetime
    // 'expires' => '+1 hour',

    // Omit both to use PHP's session.cookie_lifetime setting

    'domain' => '.example.com',
    'path' => '/',
    'secure' => true,      // HTTPS only (recommended)
    'httponly' => true,    // Prevents XSS token theft
    'samesite' => 'strict', // CSRF protection
]
```

**JWT Expiry Management:**

The plugin automatically sets the JWT `exp` claim based on cookie lifetime, following this priority:

1. `additionalClaims['exp']` - Per-request override
2. `cookie.expires` - Explicit datetime (`'+1 hour'`, etc.)
3. `cookie.lifetime` - Seconds (`3600` for 1 hour, `0` for session)
4. `ini_get('session.cookie_lifetime')` - PHP session setting
5. Default: +1 hour

Session cookies (`lifetime: 0`) automatically get a 1-hour JWT expiry for security.

**Security Notes:**

- `httponly: true` (default) prevents JavaScript access while still allowing EventSource connections
- `samesite: 'strict'` (default) provides CSRF protection
- `secure: true` requires HTTPS (recommended for production)
- JWT tokens always expire - no infinite authorization

For more details, see the [CakePHP Cookie documentation](https://book.cakephp.org/5/en/controllers/request-response.html#setting-cookies).

## Testing

For testing, mock the Publisher service to avoid actual HTTP calls:

```php
use Mercure\Publisher;
use Mercure\Service\PublisherInterface;

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
use Mercure\Service\AuthorizationInterface;

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
| `setInstance(PublisherInterface $publisher)` | `void` | Set custom instance (for testing) |
| `clear()` | `void` | Clear singleton instance |

### MercureComponent

Controller component for centralized authorization with separation of concerns and automatic dependency injection.

**Loading the Component:**

```php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Mercure.Mercure', [
        'autoDiscover' => true,  // Optional: auto-add discovery headers
        'defaultTopics' => [     // Optional: topics available in all views
            '/notifications',
            '/global/alerts'
        ]
    ]);
}
```

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `addTopic(string $topic)` | `$this` | Add a topic for the view to subscribe to |
| `addTopics(array $topics)` | `$this` | Add multiple topics for the view |
| `getTopics()` | `array` | Get all topics added in the component |
| `resetTopics()` | `$this` | Reset all accumulated topics |
| `addSubscribe(string $topic, array $additionalClaims = [])` | `$this` | Add a topic to authorize with optional JWT claims |
| `addSubscribes(array $topics, array $additionalClaims = [])` | `$this` | Add multiple topics to authorize with optional JWT claims |
| `getSubscribe()` | `array` | Get accumulated subscribe topics |
| `getAdditionalClaims()` | `array` | Get accumulated JWT claims |
| `resetSubscribe()` | `$this` | Reset accumulated subscribe topics |
| `resetAdditionalClaims()` | `$this` | Reset accumulated JWT claims |
| `authorize(array $subscribe = [], array $additionalClaims = [])` | `$this` | Set authorization cookie (merges with accumulated state, then resets) |
| `clearAuthorization()` | `$this` | Clear authorization cookie |
| `discover()` | `$this` | Add Mercure discovery Link header |
| `publish(Update $update)` | `bool` | Publish an update to the Mercure hub |
| `publishJson(string\|array $topics, mixed $data, ...)` | `bool` | Publish JSON data (auto-encodes) |
| `publishSimple(string\|array $topics, string $data, ...)` | `bool` | Publish simple string data (no encoding) |
| `publishView(string\|array $topics, ?string $template, ?string $element, array $data, ...)` | `bool` | Publish rendered view/element |
| `getCookieName()` | `string` | Get the cookie name |

**Topic Management:**

Topics added in the controller are automatically available in `MercureHelper` in your views:

```php
// In controller
public function view($id)
{
    $book = $this->Books->get($id);

    // Add topics that will be available in the view
    $this->Mercure
        ->addTopic("/books/{$id}")
        ->addTopic("/user/{$userId}/updates")
        ->authorize(["/books/{$id}"]);

    $this->set('book', $book);
}

// In template - topics are automatically included
const url = '<?= $this->Mercure->url() ?>';
// Subscribes to: /books/123 and /user/456/updates (from component)
```

**Authorization Builder Pattern:**

Build up authorization topics and claims fluently, then call `authorize()`:

```php
// Build up gradually with claims
$this->Mercure
    ->addSubscribe('/books/123', ['sub' => $userId])
    ->addSubscribe('/notifications/*', ['role' => 'admin'])
    ->authorize()
    ->discover();

// Add multiple at once
$this->Mercure->addSubscribes(
    ['/books/123', '/notifications/*'],
    ['sub' => $userId, 'role' => 'admin']
);

// Mix builder and direct parameters
$this->Mercure
    ->addSubscribe('/books/123')
    ->authorize(['/notifications/*'], ['sub' => $userId]);

// Chain with topic management
$this->Mercure
    ->addTopic('/books/123')                          // For EventSource
    ->addSubscribe('/books/123', ['sub' => $userId])  // For authorization
    ->authorize()
    ->discover();
```

Claims accumulate across multiple `addSubscribe()` calls. The `authorize()` method automatically resets accumulated state after setting the cookie.

**Publishing convenience methods** make it easy to publish updates directly from controllers:

```php
// Publish JSON data
$this->Mercure->publishJson(
    topics: '/books/123',
    data: ['status' => 'updated', 'title' => $book->title]
);

// Publish rendered element
$this->Mercure->publishView(
    topics: '/books/123',
    element: 'Books/item',
    data: ['book' => $book]
);

// Publish rendered template with layout
$this->Mercure->publishView(
    topics: '/notifications',
    template: 'Notifications/item',
    layout: 'ajax',
    data: ['notification' => $notification]
);

// For advanced use cases, publish an Update object directly
$update = new Update('/books/123', json_encode(['data' => 'value']));
$this->Mercure->publish($update);
```

### Authorization

Static facade for direct authorization management (alternative to component).

| Method | Returns | Description |
|--------|---------|-------------|
| `setCookie(Response $response, array $subscribe, array $additionalClaims)` | `Response` | Set authorization cookie |
| `clearCookie(Response $response)` | `Response` | Clear authorization cookie |
| `addDiscoveryHeader(Response $response)` | `Response` | Add Mercure discovery Link header |
| `getCookieName()` | `string` | Get the cookie name |

### MercureHelper

| Method | Returns | Description |
|--------|---------|-------------|
| `url(array\|string\|null $topics, array $subscribe, array $additionalClaims)` | `string` | Get hub URL **and optionally authorize** (only sets cookie when `$subscribe` is provided). Merges with default topics if configured. |
| `addTopic(string $topic)` | `$this` | Add a single topic to default topics (fluent interface) |
| `addTopics(array $topics)` | `$this` | Add multiple topics to default topics (fluent interface) |
| `authorize(array $subscribe, array $additionalClaims)` | `void` | Set authorization cookie explicitly |
| `clearAuthorization()` | `void` | Clear authorization cookie |
| `discover()` | `void` | Add Mercure discovery Link header |
| `getCookieName()` | `string` | Get the cookie name |
| `getConfig(string $key, mixed $default)` | `mixed` | Get helper configuration (e.g., `getConfig('defaultTopics', [])`) |

**Configuration Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `defaultTopics` | `array` | `[]` | Topics to automatically merge with every subscription (read-only, not mutated by `addTopic()`/`addTopics()`) |

### Update

Base class for Mercure updates. For most use cases, consider using `JsonUpdate` or `ViewUpdate` instead.

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

### JsonUpdate

Specialized Update class that automatically encodes data to JSON. Supports both static factory method and fluent builder pattern.

**Fluent Builder Pattern (Recommended):**

```php
use Mercure\Update\JsonUpdate;

// Basic usage
$update = (new JsonUpdate('/books/1'))
    ->data(['status' => 'OutOfStock', 'quantity' => 0])
    ->build();

// With all options
$update = (new JsonUpdate('/books/1'))
    ->data(['title' => 'Book', 'price' => 29.99])
    ->jsonOptions(JSON_UNESCAPED_UNICODE)
    ->private()
    ->id('book-123')
    ->type('book.updated')
    ->retry(5000)
    ->build();
```

**Builder Methods:**

| Method | Parameter | Returns | Description |
|--------|-----------|---------|-------------|
| `data(mixed $data)` | Data to encode | `$this` | Set data to encode as JSON |
| `jsonOptions(int $options)` | JSON options | `$this` | Set JSON encoding options |
| `private(bool $private = true)` | Private flag | `$this` | Mark as private update |
| `id(string $id)` | Event ID | `$this` | Set SSE event ID |
| `type(string $type)` | Event type | `$this` | Set SSE event type |
| `retry(int $retry)` | Retry delay (ms) | `$this` | Set retry delay |
| `build()` | - | `Update` | Build and return Update |

**Static Factory Method:**

```php
JsonUpdate::create(
    string|array $topics,
    mixed $data,
    bool $private = false,
    ?string $id = null,
    ?string $type = null,
    ?int $retry = null,
    int $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
): Update
```

### ViewUpdate

Specialized Update class that automatically renders CakePHP views or elements. Supports both static factory method and fluent builder pattern.

**Fluent Builder Pattern (Recommended):**

```php
use Mercure\Update\ViewUpdate;

// Render element
$update = (new ViewUpdate('/books/1'))
    ->element('Books/item')
    ->viewVars(['book' => $book])
    ->build();

// Render template with all options
$update = (new ViewUpdate('/notifications'))
    ->template('Notifications/item')
    ->viewVars(['notification' => $notification])
    ->layout('ajax')
    ->viewOptions(['plugin' => 'MyPlugin'])
    ->private()
    ->id('notif-123')
    ->type('notification.new')
    ->build();
```

**Builder Methods:**

| Method | Parameter | Returns | Description |
|--------|-----------|---------|-------------|
| `template(string $template)` | Template name | `$this` | Set template to render |
| `element(string $element)` | Element name | `$this` | Set element to render |
| `viewVars(array $viewVars)` | View variables | `$this` | Set view variables |
| `set(string $key, mixed $value)` | Key, value | `$this` | Set single view variable |
| `layout(?string $layout)` | Layout name | `$this` | Set layout (null to disable) |
| `viewOptions(array $options)` | ViewBuilder options | `$this` | Set ViewBuilder options |
| `private(bool $private = true)` | Private flag | `$this` | Mark as private update |
| `id(string $id)` | Event ID | `$this` | Set SSE event ID |
| `type(string $type)` | Event type | `$this` | Set SSE event type |
| `retry(int $retry)` | Retry delay (ms) | `$this` | Set retry delay |
| `build()` | - | `Update` | Build and return Update |

**Static Factory Method:**

```php
ViewUpdate::create(
    string|array $topics,
    ?string $template = null,
    ?string $element = null,
    array $viewVars = [],
    ?string $layout = null,
    bool $private = false,
    ?string $id = null,
    ?string $type = null,
    ?int $retry = null,
    array $viewOptions = []
): Update
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$topics` | `string\|array` | Topic IRI(s) for the update |
| `$template` | `?string` | Template to render (e.g., 'Books/view') |
| `$element` | `?string` | Element to render (e.g., 'Books/item') |
| `$data` | `array` | View variables to pass to the template/element |
| `$layout` | `?string` | Layout to use (null for no layout) |
| `$private` | `bool` | Whether this is a private update |
| `$id` | `?string` | Optional SSE event ID |
| `$type` | `?string` | Optional SSE event type |
| `$retry` | `?int` | Optional reconnection time in milliseconds |

> [!NOTE]
> Either `template` or `element` must be specified, but not both.

**Example:**

```php
use Mercure\Update\ViewUpdate;

// Render an element
$update = ViewUpdate::create(
    topics: '/books/1',
    element: 'Books/item',
    data: ['book' => $book]
);

// Render a template with layout
$update = ViewUpdate::create(
    topics: '/dashboard',
    template: 'Dashboard/stats',
    layout: 'ajax',
    data: ['stats' => $stats]
);
```

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
