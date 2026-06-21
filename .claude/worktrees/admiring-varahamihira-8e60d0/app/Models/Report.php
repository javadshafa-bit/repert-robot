<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'representative_id', 'department_id', 'category_id', 'jalali_month', 'data',
    ];

    protected $casts = ['data' => 'array'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}