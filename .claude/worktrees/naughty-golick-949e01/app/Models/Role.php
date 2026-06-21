<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'permissions', 'all_departments'];

    protected $casts = [
        'permissions'     => 'array',
        'all_departments' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'role_department');
    }

    /** لیست کامل دسترسی‌های تعریف‌شده در سیستم */
    public static function allPermissions(): array
    {
        return [
            'reports'         => 'مشاهده گزارش‌ها',
            'reports.export'  => 'خروجی اکسل گزارش‌ها',
            'categories'      => 'فرم‌ساز و دسته‌بندی',
            'departments'     => 'دپارتمان‌ها',
            'representatives' => 'استان‌ها و نمایندگان',
            'settings'        => 'تنظیمات ربات',
            'users'           => 'مدیریت کاربران و نقش‌ها',
        ];
    }
}
