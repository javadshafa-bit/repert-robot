<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'is_super_admin', 'is_platform_admin'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /** سوپرادمین پلتفرم: مالک این نصب، بدون تعلق به هیچ مستأجری */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) return true;

        return $this->roles->contains(
            fn($role) => in_array($permission, $role->permissions ?? [])
        );
    }

    /**
     * IDs دپارتمان‌هایی که این کاربر مجاز به دیدن گزارش‌های آن‌هاست.
     * null یعنی همه دپارتمان‌ها مجاز هستند.
     */
    public function allowedDepartmentIds(): ?array
    {
        if ($this->isSuperAdmin()) return null;

        // اگر حتی یک نقش «همه دپارتمان‌ها» داشته باشد، فیلتر اعمال نمی‌شود
        if ($this->roles->contains('all_departments', true)) return null;

        return $this->roles
            ->flatMap(fn($role) => $role->departments->pluck('id'))
            ->unique()
            ->values()
            ->toArray();
    }
}
