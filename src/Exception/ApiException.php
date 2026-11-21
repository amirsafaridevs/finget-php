<?php

declare(strict_types=1);

namespace Finget\Exception;

/**
 * پاسخ ناموفق API فینگت.
 *
 * @property-read int $status کد وضعیت HTTP (۰ اگر شبکه قطع باشد)
 * @property-read string|null $errorCode کد ثابت خطا مثل invalid_api_key (اگر آمده باشد)
 * @property-read array<string, mixed> $body بدنهٔ JSON پاسخ
 */
final class ApiException extends FingetException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly array $body = [],
    ) {
        parent::__construct($message, $status);
    }
}
