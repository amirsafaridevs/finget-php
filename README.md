# Finget PHP SDK

کلاینت رسمی **[فینگت](https://finget.ir)** برای PHP: ورود بدون رمز و ارسال اعلان به کاربران وصل‌شده.

- وب‌سایت: [finget.ir](https://finget.ir)
- افزونه‌ها و SDKها: [finget.ir/plugins](https://finget.ir/plugins)
- مستندات API: [finget.ir/provider/panel/docs](https://finget.ir/provider/panel/docs)
- Packagist: [packagist.org/packages/finget/sdk](https://packagist.org/packages/finget/sdk)
- نیازمندی: **PHP 8.1+** با `ext-curl` و `ext-json`

## نصب

با Composer:

```bash
composer require finget/sdk
```

یا فایل ZIP را از صفحه‌ی افزونه‌ها بگیرید و پوشه‌ی `src/` را در پروژه‌تان بگذارید (با autoload خودتان).

## شروع سریع

کلید API را فقط سمت سرور نگه دارید:

```bash
export FINGET_API_KEY=fg_live_...
```

```php
<?php

use Finget\Client;
use Finget\Exception\ApiException;

$finget = Client::fromEnv();
// یا: new Client(getenv('FINGET_API_KEY'));

$site = $finget->getSite();
```

## ورود با فینگت

```php
<?php

session_start();

use Finget\Client;

$finget = Client::fromEnv();
$clientId = getenv('FINGET_CLIENT_ID'); // شناسه‌ی سایت از پنل

// صفحه‌ی ورود
$login = $finget->loginUrl($clientId);
$_SESSION['finget_state'] = $login['state'];
header('Location: ' . $login['url']);
exit;

// آدرس بازگشت (callback) — در پنل ثبت شده باشد
$state = $_SESSION['finget_state'] ?? '';
unset($_SESSION['finget_state']);

if (
    empty($_GET['code'])
    || !is_string($_GET['state'] ?? null)
    || !hash_equals($state, $_GET['state'])
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
// حساب را با $user['id'] پیدا یا بسازید و وارد کنید.
```

## ارسال اعلان

```php
<?php

$result = $finget->sendNotification([
    'id' => 'order-1234-shipped',
    'kind' => 'notice',
    'title' => 'سفارش شما ارسال شد',
    'body' => 'سفارش ۱۲۳۴ تحویل پست شد.',
    'link' => [
        'url' => 'https://shop.ir/orders/1234',
        'label' => 'پیگیری سفارش',
    ],
    'users' => [$user['id']],
]);
```

## توسعهٔ محلی

```php
$finget = new Client(
    apiKey: getenv('FINGET_API_KEY'),
    baseUrl: 'http://localhost:3001/api/v1',
    authUrl: 'http://localhost:5173/auth',
);
```

یا متغیرهای `FINGET_BASE_URL` و `FINGET_AUTH_URL` را قبل از `Client::fromEnv()` تنظیم کنید.

## مجوز

MIT
