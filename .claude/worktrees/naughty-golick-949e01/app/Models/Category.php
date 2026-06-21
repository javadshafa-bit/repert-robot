<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    protected $fillable = ['name', 'sort_order', 'is_active'];

    public function fields() {
        return $this->hasMany(CategoryField::class)->orderBy('sort_order');
    }

    public function reports() {
        return $this->hasMany(Report::class);
    }
}