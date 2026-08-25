<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Province extends Model {
    use BelongsToTenant;

    protected $fillable = ['name'];

    public function representatives() {
        return $this->hasMany(Representative::class);
    }
}