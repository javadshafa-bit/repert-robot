<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * قوانین اعتبارسنجی exists/unique به‌صورت پیش‌فرض global scope مدل‌ها را نمی‌بینند
 * (چون مستقیم با query builder کار می‌کنند). این کلاس آن‌ها را به مستأجر جاری محدود می‌کند
 * تا مثلاً کاربر سازمان A نتواند با دستکاری فرم، id متعلق به سازمان B را ثبت کند.
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('tenant_id', TenantContext::id());
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('tenant_id', TenantContext::id());
    }
}
