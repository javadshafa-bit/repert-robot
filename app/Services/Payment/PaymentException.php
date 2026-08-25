<?php

namespace App\Services\Payment;

use RuntimeException;

/** خطای قابل نمایش به کاربر در جریان پرداخت */
class PaymentException extends RuntimeException
{
}
