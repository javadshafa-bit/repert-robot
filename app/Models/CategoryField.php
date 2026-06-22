<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CategoryField extends Model
{
    protected $fillable = [
        'category_id',
        'parent_option_id',
        'parent_field_id',
        'label',
        'description',
        'sort_order',
        'is_required',
        'type',
        'is_multiple',
    ];

    protected $casts = [
        'is_multiple' => 'boolean',
        'is_required' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /** گزینه‌های این فیلد (فقط اگر type = option) */
    public function options()
    {
        return $this->hasMany(FieldOption::class, 'field_id')->orderBy('sort_order');
    }

    /** گزینه والد (اگر این فیلد زیرفیلد یک option باشد) */
    public function parentOption()
    {
        return $this->belongsTo(FieldOption::class, 'parent_option_id');
    }

    /** فیلدهایی که همیشه بعد از پاسخ دادن به این فیلد نمایش داده می‌شوند */
    public function alwaysChildFields()
    {
        return $this->hasMany(CategoryField::class, 'parent_field_id')->orderBy('sort_order');
    }

    /** فیلد والدِ ثابت (اگر این فیلد زیرفیلد همیشگی یک فیلد دیگر باشد) */
    public function parentField()
    {
        return $this->belongsTo(CategoryField::class, 'parent_field_id');
    }

    /** آیا فیلد سطح اول است؟ (هیچ والدی ندارد) */
    public function isTopLevel(): bool
    {
        return is_null($this->parent_option_id) && is_null($this->parent_field_id);
    }

    protected function typeFa(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                'text'   => 'متن',
                'option' => 'گزینه',
                'photo'  => 'عکس',
                'link'   => 'لینک',
                default  => 'ناشناخته',
            },
        );
    }

    protected function typeColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                'text'   => 'bg-gray-100 text-gray-600',
                'option' => 'bg-purple-100 text-purple-700',
                'photo'  => 'bg-blue-100 text-blue-700',
                'link'   => 'bg-green-100 text-green-700',
                default  => 'bg-gray-100 text-gray-500',
            },
        );
    }
}
