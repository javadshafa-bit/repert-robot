<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotState extends Model
{
    protected $fillable = [
        'chat_id', 'step', 'last_message_id', 'representative_id',
        'department_id',
        'category_id', 'jalali_month', 'current_field_index',
        'draft_data', 'field_queue',
    ];

    protected $casts = [
        'draft_data'  => 'array',
        'field_queue' => 'array',
    ];

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }
}
