<?php

declare(strict_types=1);

namespace Finget;

use Finget\Exception\ApiException;
use Finget\Exception\FingetException;

/**
 * کلاینت رسمی API عمومی فینگت (/api/v1).
 *
 * کلید API را فقط سمت سرور نگه دارید (مثلاً متغیر محیطی FINGET_API_KEY).
 */
final class Client
{
    public const VERSION = '1.0.0';

    public const DEFAULT_BASE_URL = 'https://finget.ir/api/v1';

    public const DEFAULT_AUTH_URL = 'https://finget.ir/auth';

    private string $apiKey;

    private string $baseUrl;

    private string $authUrl;

    private int $timeout;

    /**
     * @param string $apiKey کلید API سایت (fg_live_…)
     * @param string|null $baseUrl آدرس پایهٔ API؛ برای توسعهٔ محلی مثلاً http://localhost:5173/api/v1
     * @param string|null $authUrl آدرس صفحه‌ی ورود فینگت
     * @param int $timeout مهلت هر درخواست بر حسب ثانیه
     */
    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        ?string $authUrl = null,
        int $timeout = 20,
    ) {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new FingetException('کلید API فینگت خالی است.');
        }
        if ($timeout < 1) {
            throw new FingetException('مهلت درخواست باید حداقل ۱ ثانیه باشد.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
        $this->authUrl = rtrim($authUrl ?? self::DEFAULT_AUTH_URL, '/');
        $this->timeout = $timeout;
    }

    /** از متغیر محیطی FINGET_API_KEY (و اختیاری FINGET_BASE_URL / FINGET_AUTH_URL). */
    public static function fromEnv(?string $apiKeyEnv = 'FINGET_API_KEY'): self
    {
        $key = getenv($apiKeyEnv ?: 'FINGET_API_KEY');
        if (!is_string($key) || trim($key) === '') {
            throw new FingetException('متغیر محیطی ' . ($apiKeyEnv ?: 'FINGET_API_KEY') . ' تنظیم نشده است.');
        }

        $base = getenv('FINGET_BASE_URL');
        $auth = getenv('FINGET_AUTH_URL');

        return new self(
            $key,
            is_string($base) && $base !== '' ? $base : null,
            is_string($auth) && $auth !== '' ? $auth : null,
        );
    }

    /** آزمودن کلید؛ مشخصات سایت صاحب کلید. */
    public function getSite(): array
    {
        return $this->request('GET', '/site');
    }

    /**
     * کد یک‌بارمصرف callback را به اطلاعات کاربر تبدیل می‌کند.
     *
     * @return array{user: array{id: string, name: string, phone: string}, isNew: bool}
     */
    public function exchangeCode(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            throw new FingetException('کد ورود (code) خالی است.');
        }

        return $this->request('POST', '/auth/token', ['code' => $code]);
    }

    /**
     * اطلاعات تازه‌ی کاربر وصل به سایت.
     *
     * @return array{user: array{id: string, name: string, phone: string}}
     */
    public function getUser(string $userId): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new FingetException('شناسه‌ی کاربر خالی است.');
        }

        return $this->request('GET', '/users/' . rawurlencode($userId));
    }

    /**
     * ارسال اعلان به کاربران وصل سایت.
     *
     * @param array<string, mixed> $payload فیلدهای مستندات /notifications
     * @return array{notification: array<string, mixed>, skipped?: array<string, int>, duplicate?: bool}
     */
    public function sendNotification(array $payload): array
    {
        return $this->request('POST', '/notifications', $payload, 201);
    }

    /** وضعیت یک پیام (گیرندگان، خوانده‌شدن، کلیک). */
    public function getNotification(string $notificationId): array
    {
        $notificationId = trim($notificationId);
        if ($notificationId === '') {
            throw new FingetException('شناسه‌ی اعلان خالی است.');
        }

        return $this->request('GET', '/notifications/' . rawurlencode($notificationId));
    }

    /** رشته‌ی تصادفی امن برای پارامتر state ورود. */
    public function createState(int $bytes = 16): string
    {
        if ($bytes < 8) {
            throw new FingetException('طول state خیلی کوتاه است.');
        }

        return bin2hex(random_bytes($bytes));
    }

    /**
     * آدرس دکمه‌ی «ورود با فینگت».
     *
     * @param string $clientId شناسه‌ی سایت از پنل
     * @param string|null $state اگر null باشد، ساخته می‌شود (در این حالت مقدار برگشتی را در نشست نگه دارید)
     * @return array{url: string, state: string}
     */
    public function loginUrl(string $clientId, ?string $state = null): array
    {
        $clientId = trim($clientId);
        if ($clientId === '') {
            throw new FingetException('شناسه‌ی سایت (client_id) خالی است.');
        }

        $state ??= $this->createState();
        $query = http_build_query([
            'client_id' => $clientId,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => $this->authUrl . '?' . $query,
            'state' => $state,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @param int|list<int> $okStatuses
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, int|array $okStatuses = 200): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: finget-php/' . self::VERSION,
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new FingetException('راه‌اندازی cURL ممکن نشد.');
        }

        $opts = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new ApiException(
                $error !== '' ? $error : 'اتصال به فینگت برقرار نشد.',
                0,
            );
        }

        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];

        $ok = is_array($okStatuses) ? $okStatuses : [$okStatuses];
        if (!in_array($status, $ok, true)) {
            $message = is_string($data['message'] ?? null)
                ? $data['message']
                : 'درخواست به فینگت ناموفق بود (HTTP ' . $status . ').';
            $code = is_string($data['code'] ?? null) ? $data['code'] : null;
            throw new ApiException($message, $status, $code, $data);
        }

        return $data;
    }
}
