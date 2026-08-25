<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * آماده‌سازی داده‌ی اولیه‌ی یک مستأجر تازه‌تاییدشده.
 *
 * مستأجر جدید کاملاً خالی شروع می‌کند (بدون دپارتمان/دسته‌بندی/فیلد)؛
 * تنها چیزی که ساخته می‌شود لیست استان‌هاست، چون بدون آن ثبت نماینده ممکن نیست.
 */
class TenantProvisioner
{
    public const PROVINCES = [
        'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز',
        'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری', 'خراسان جنوبی',
        'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان',
        'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان',
        'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان',
        'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد',
    ];

    public function provision(Tenant $tenant): void
    {
        $this->seedProvinces($tenant);
    }

    public function seedProvinces(Tenant $tenant): void
    {
        // عمداً از DB::table استفاده می‌شود تا مستقل از TenantContext جاری کار کند
        // (مثلاً وقتی سوپرادمین پلتفرم — که خودش tenant ندارد — مستأجری را تایید می‌کند).
        $existing = DB::table('provinces')->where('tenant_id', $tenant->id)->pluck('name')->all();

        $rows = [];
        foreach (self::PROVINCES as $name) {
            if (in_array($name, $existing, true)) {
                continue;
            }
            $rows[] = [
                'tenant_id'  => $tenant->id,
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            DB::table('provinces')->insert($rows);
        }
    }
}
