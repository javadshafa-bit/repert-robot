<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'sort_order', 'is_active'];

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}