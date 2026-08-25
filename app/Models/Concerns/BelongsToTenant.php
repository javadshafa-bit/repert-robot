<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

/**
 * ایزوله‌سازی خودکار داده‌ی هر مستأجر.
 *
 * - همه‌ی کوئری‌های مدل به tenant_id مستأجر جاری محدود می‌شوند.
 * - هنگام ساخت رکورد جدید، tenant_id خودکار پر می‌شود.
 *
 * اگر مستأجر جاری مشخص نباشد، کوئری عمداً هیچ نتیجه‌ای برنمی‌گرداند و ساخت رکورد
 * خطا می‌دهد. اسکریپت‌های سطح پلتفرم (کرون، artisan) یا باید
 * TenantContext::forTenant() بگذارند یا صریحاً TenantContext::withoutScope() بزنند.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new class implements Scope {
            public function apply(Builder $builder, Model $model): void
            {
                if (TenantContext::bypassed()) {
                    return;
                }

                $tenantId = TenantContext::id();

                if ($tenantId !== null) {
                    $builder->where($model->getTable() . '.tenant_id', $tenantId);
                    return;
                }

                // بدون مستأجر مشخص هیچ رکوردی نباید دیده شود
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = TenantContext::id();

            // withoutScope فقط برای «خواندن» بین‌مستأجری است. اگر آنجا رکورد بسازیم و
            // tenant_id صریحاً ست نشده باشد، رکوردی با tenant_id = null ساخته می‌شود که
            // از دید کل اپ نامرئی است — همان تله‌ی «رکورد گم‌شده». پس بی‌صدا رد نشو.
            if ($tenantId === null) {
                throw new RuntimeException(
                    'ساخت ' . $model::class . ' بدون مستأجر مجاز نیست؛ یا TenantContext را مقدار بدهید'
                    . ' یا هنگام ساخت، tenant_id را صریحاً بنویسید.'
                );
            }

            $model->setAttribute('tenant_id', $tenantId);
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
