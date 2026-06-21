<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CategoryField extends Model
{
    protected $fillable = [
        'category_id',
        'label',
        'description',
        'sort_order',
        'is_required',
        'type'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected function typeFa(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                'text' => 'متن',
                'photo' => 'عکس',
                'document' => 'فایل',
                default => 'ناشناخته',
            },
        );
    }
}
