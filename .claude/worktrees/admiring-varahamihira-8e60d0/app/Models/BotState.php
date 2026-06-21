<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotState extends Model
{
    protected $fillable = [
        'chat_id', 'step', 'representative_id',
        'department_id',
        'category_id', 'jalali_month', 'current_field_index', 'draft_data',
    ];

    protected $casts = ['draft_data' => 'array'];

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }
}