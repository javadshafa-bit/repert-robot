<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiMemory extends Model
{
    use BelongsToTenant;

    public const KIND_MESSAGE = 'message';
    public const KIND_KNOWLEDGE = 'knowledge';

    protected $fillable = ['tenant_id', 'chat_id', 'kind', 'role', 'source', 'content', 'embedding'];

    // بردار خام در پاسخ‌های JSON/لاگ نیاید
    protected $hidden = ['embedding'];

    /** @param float[] $vector */
    public static function packVector(array $vector): string
    {
        return pack('g*', ...$vector);
    }

    /** @return float[] */
    public function vector(): array
    {
        if ($this->embedding === null || $this->embedding === '') {
            return [];
        }

        return array_values(unpack('g*', $this->embedding));
    }
}
