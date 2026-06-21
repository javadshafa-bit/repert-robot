<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}