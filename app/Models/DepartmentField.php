<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class DepartmentField extends Model
{
    protected $fillable = [
        'department_id',
        'label',
        'description',
        'sort_order',
        'is_required',
        'type',
        'is_multiple',
    ];

    protected $casts = [
        'is_multiple' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    protected function typeFa(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                'text'     => 'متن ساده',
                'photo'    => 'عکس',
                'document' => 'فایل',
                'link'     => 'لینک',
                default    => 'ناشناخته',
            },
        );
    }
}
