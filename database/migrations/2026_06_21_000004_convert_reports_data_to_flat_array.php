<?php

use App\Models\CategoryField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * گزارش‌های قدیمی data را به فرمت جدید flat array تبدیل می‌کند.
     * فرمت قدیم: {"1": "value", "2": "value", ...}  (کلید = field_id)
     * فرمت جدید: [{field_id, label, type, value}, ...]
     */
    public function up(): void
    {
        $reports = DB::table('reports')->get();

        foreach ($reports as $report) {
            $data = json_decode($report->data, true);

            if (empty($data) || !is_array($data)) continue;

            // اگر آرایه indexed باشد (فرمت جدید) نیازی به تبدیل نیست
            if (isset($data[0]) && is_array($data[0]) && array_key_exists('field_id', $data[0])) continue;

            // فرمت قدیم: کلیدها عددی هستند (field_id)
            // بررسی می‌کنیم آیا کلیدها همه عددی‌اند
            $allNumericKeys = array_reduce(array_keys($data), fn($carry, $k) => $carry && is_numeric($k), true);
            if (!$allNumericKeys) continue;

            // بارگذاری فیلدهای دسته‌بندی برای گرفتن label و type
            $fieldIds = array_keys($data);
            $fields   = CategoryField::whereIn('id', $fieldIds)->get()->keyBy('id');

            $newData = [];
            foreach ($data as $fieldId => $value) {
                $field     = $fields->get((int) $fieldId);
                $newData[] = [
                    'field_id' => (int) $fieldId,
                    'label'    => $field?->label ?? "فیلد {$fieldId}",
                    'type'     => $field?->type  ?? 'text',
                    'value'    => $value,
                ];
            }

            DB::table('reports')->where('id', $report->id)->update([
                'data' => json_encode($newData, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        // برگشت به فرمت قدیم پیچیده است — توصیه می‌شود از backup استفاده کنید
    }
};
