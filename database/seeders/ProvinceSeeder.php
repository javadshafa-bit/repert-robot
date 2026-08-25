<?php
namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;

/**
 * استان‌ها per-tenant هستند؛ برای هر سازمان موجود لیست استان‌ها ساخته می‌شود.
 * سازمان‌های جدید هنگام تایید توسط سوپرادمین، استان‌هایشان خودکار ساخته می‌شود.
 */
class ProvinceSeeder extends Seeder {
    public function run(): void {
        $provisioner = app(TenantProvisioner::class);

        foreach (Tenant::all() as $tenant) {
            $provisioner->seedProvinces($tenant);
        }
    }
}
