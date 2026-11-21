# Finget PHP SDK

[![Packagist](https://img.shields.io/packagist/v/finget/sdk.svg)](https://packagist.org/packages/finget/sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/finget/sdk.svg)](https://packagist.org/packages/finget/sdk)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Official PHP client for the [Finget](https://finget.ir) public API (`/api/v1`).

Use it on your server to:

- Start **passwordless login** (“Sign in with Finget”)
- Exchange the one-time callback `code` for a Finget user
- Send **push notifications** to linked users
- Inspect site and notification delivery status

> Keep your API key on the server only. Never expose `FINGET_API_KEY` in browsers or mobile apps.

## Links

| | |
|---|---|
| Website | [finget.ir](https://finget.ir) |
| Plugins & SDKs | [finget.ir/plugins](https://finget.ir/plugins) |
| API docs | [Provider panel docs](https://finget.ir/provider/panel/docs) |
| Packagist | [finget/sdk](https://packagist.org/packages/finget/sdk) |
| Laravel package | [finget/laravel](https://packagist.org/packages/finget/laravel) |

## Requirements

- PHP **8.1+**
- Extensions: `ext-curl`, `ext-json`

## Installation

```bash
composer require finget/sdk
```

Or download the ZIP from [finget.ir/plugins](https://finget.ir/plugins) and autoload `src/` yourself (PSR-4: `Finget\\` → `src/`).

## Quick start

```bash
export FINGET_API_KEY=fg_live_...
```

```php
<?php

use Finget\Client;
use Finget\Exception\ApiException;

$finget = Client::fromEnv();
// or: new Client(getenv('FINGET_API_KEY'));

$site = $finget->getSite();
// $site['site'] → domain, displayDomain, name, verifiedAt
```

## Authentication (Sign in with Finget)

1. In the Finget provider panel, copy your **site client ID** and register the callback URL.
2. Redirect the user to Finget with a CSRF `state`.
3. On callback, verify `state`, then exchange `code` for the user profile.

```php
<?php

session_start();

use Finget\Client;
use Finget\Exception\ApiException;

$finget = Client::fromEnv();
$clientId = getenv('FINGET_CLIENT_ID');

// --- Start login ---
$login = $finget->loginUrl($clientId);
$_SESSION['finget_state'] = $login['state'];
header('Location: ' . $login['url']);
exit;

// --- Callback (must match the URL registered in the panel) ---
$expected = $_SESSION['finget_state'] ?? '';
unset($_SESSION['finget_state']);

if (
    empty($_GET['code'])
    || !is_string($_GET['state'] ?? null)
    || !hash_equals($expected, $_GET['state'])
) {
    header('Location: /login');
    exit;
}

try {
    $result = $finget->exchangeCode($_GET['code']);
} catch (ApiException $e) {
    header('Location: /login');
    exit;
}

$user = $result['user']; // id, name, phone
$isNew = $result['isNew'];

// Find or create your local account by $user['id'], then log the user in.
```

### `loginUrl()`

```php
$login = $finget->loginUrl($clientId);           // generates state
$login = $finget->loginUrl($clientId, $state);   // reuse your own state

// ['url' => 'https://finget.ir/auth?client_id=...&state=...', 'state' => '...']
```

## Notifications

Send a notification to one or more linked Finget users (by Finget user id):

```php
$result = $finget->sendNotification([
    'id' => 'order-1234-shipped', // optional idempotency key
    'kind' => 'notice',           // notice | reminder | security | promo
    'title' => 'Your order shipped',
    'body' => 'Order 1234 is on the way.',
    'link' => [
        'url' => 'https://shop.example/orders/1234',
        'label' => 'Track order',
    ],
    'users' => [$user['id']],
    // or: 'audience' => 'all',
]);
```

Optional coupon payload:

```php
'coupon' => [
    'code' => 'SAVE10',
    'note' => 'Valid this week',
    'expiresAt' => '2026-12-31T23:59:59+03:30',
],
```

Fetch delivery status later:

```php
$status = $finget->getNotification('order-1234-shipped');
```

## API reference

| Method | Description |
|--------|-------------|
| `Client::fromEnv(?string $apiKeyEnv = 'FINGET_API_KEY')` | Build from environment variables |
| `getSite(): array` | Validate the API key; return site info |
| `loginUrl(string $clientId, ?string $state = null): array` | Build the auth redirect URL + state |
| `createState(int $bytes = 16): string` | Cryptographically secure hex state |
| `exchangeCode(string $code): array` | Exchange callback code → `{ user, isNew }` |
| `getUser(string $userId): array` | Refresh a linked user’s profile |
| `sendNotification(array $payload): array` | Create a notification (HTTP 201) |
| `getNotification(string $notificationId): array` | Notification status / recipients |

### Environment variables

| Variable | Required | Description |
|----------|----------|-------------|
| `FINGET_API_KEY` | yes (for `fromEnv`) | Site API key (`fg_live_…`) |
| `FINGET_BASE_URL` | no | Override API base (default `https://finget.ir/api/v1`) |
| `FINGET_AUTH_URL` | no | Override login page (default `https://finget.ir/auth`) |

### Constructor options

```php
$finget = new Client(
    apiKey: getenv('FINGET_API_KEY'),
    baseUrl: 'https://finget.ir/api/v1',
    authUrl: 'https://finget.ir/auth',
    timeout: 20, // seconds
);
```

## Error handling

```php
use Finget\Exception\ApiException;
use Finget\Exception\FingetException;

try {
    $finget->getSite();
} catch (ApiException $e) {
    // HTTP / network failure from the API
    $e->getMessage();
    $e->status;     // 0 if transport failed
    $e->errorCode;  // e.g. invalid_api_key when present
    $e->body;       // decoded JSON body
} catch (FingetException $e) {
    // Client-side validation (empty key, empty code, …)
}
```

## Local development

```php
$finget = new Client(
    apiKey: getenv('FINGET_API_KEY'),
    baseUrl: 'http://localhost:3001/api/v1',
    authUrl: 'http://localhost:5173/auth',
);
```

Or set `FINGET_BASE_URL` / `FINGET_AUTH_URL` before `Client::fromEnv()`.

## Laravel

Prefer the first-party package [`finget/laravel`](https://packagist.org/packages/finget/laravel) for routes, config, and the `Finget` facade. It depends on this SDK.

## License

[MIT](LICENSE)
