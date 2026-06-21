<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Representative extends Model {
    protected $fillable = [
        'province_id','first_name','last_name',
        'phone_number','chat_id','is_connected',
    ];

    public function province() {
        return $this->belongsTo(Province::class);
    }

    public function reports() {
        return $this->hasMany(Report::class);
    }

    public function monthlyStatuses() {
        return $this->hasMany(MonthlyStatus::class);
    }

    public function getFullNameAttribute(): string {
        return $this->first_name . ' ' . $this->last_name;
    }
}