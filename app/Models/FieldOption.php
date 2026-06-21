<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldOption extends Model
{
    protected $fillable = ['field_id', 'label', 'sort_order'];

    /** فیلد option ای که این گزینه به آن تعلق دارد */
    public function field()
    {
        return $this->belongsTo(CategoryField::class, 'field_id');
    }

    /** فیلدهای فرزند که فقط وقتی این گزینه انتخاب شود نمایش داده می‌شوند */
    public function childFields()
    {
        return $this->hasMany(CategoryField::class, 'parent_option_id')->orderBy('sort_order');
    }
}
