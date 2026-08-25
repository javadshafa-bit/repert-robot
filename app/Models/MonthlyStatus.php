<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MonthlyStatus extends Model {
    use BelongsToTenant;

    protected $fillable = ['representative_id', 'jalali_month', 'closed_at'];

    protected $casts = ['closed_at' => 'datetime'];

    public function representative() {
        return $this->belongsTo(Representative::class);
    }
}