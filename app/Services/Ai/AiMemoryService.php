<?php

namespace App\Services\Ai;

use App\Models\AiMemory;
use Illuminate\Support\Collection;

/**
 * حافظه‌ی ربات:
 *  - کوتاه‌مدت: چند پیام آخر همان چت، به ترتیب زمانی.
 *  - بلندمدت (RAG): جست‌وجوی برداری در پیام‌های قدیمی‌تر همان کاربر
 *    و در پایگاه دانش سازمان (chat_id = null).
 *
 * بردارها در MySQL ذخیره می‌شوند و شباهت کسینوسی در PHP حساب می‌شود؛
 * برای چند هزار ردیف در هر سازمان کاملاً کافی است و سرویس جانبی لازم ندارد.
 * همه‌ی کوئری‌ها از مدل Eloquent می‌گذرند، پس scope مستأجر خودکار اعمال می‌شود.
 */
class AiMemoryService
{
    public function __construct(protected OpenAiClient $client) {}

    /** پیام‌های اخیر به شکل آماده برای messages */
    public function recentHistory(string $chatId, ?int $limit = null): array
    {
        $limit ??= config('ai.memory.recent_messages');

        return AiMemory::query()
            ->where('chat_id', $chatId)
            ->where('kind', AiMemory::KIND_MESSAGE)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    /** شناسه‌ی پیام‌هایی که در history آمده‌اند، تا در recall تکرار نشوند */
    public function recentIds(string $chatId, ?int $limit = null): array
    {
        return AiMemory::query()
            ->where('chat_id', $chatId)
            ->where('kind', AiMemory::KIND_MESSAGE)
            ->orderByDesc('id')
            ->limit($limit ?? config('ai.memory.recent_messages'))
            ->pluck('id')
            ->all();
    }

    /**
     * @param float[] $queryVector
     * @return array{memories: Collection, knowledge: Collection}
     */
    public function recall(string $chatId, array $queryVector, array $excludeIds = []): array
    {
        $cfg = config('ai.memory');

        // پیام‌های قدیمی‌تر همین کاربر — پیام‌هایی که در history آمده‌اند تکرار نشوند
        $memoryRows = AiMemory::query()
            ->where('chat_id', $chatId)
            ->where('kind', AiMemory::KIND_MESSAGE)
            ->whereNotNull('embedding')
            ->when($excludeIds, fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->orderByDesc('id')
            ->limit($cfg['scan_limit'])
            ->get();

        $knowledgeRows = AiMemory::query()
            ->whereNull('chat_id')
            ->where('kind', AiMemory::KIND_KNOWLEDGE)
            ->whereNotNull('embedding')
            ->limit($cfg['scan_limit'])
            ->get();

        return [
            'memories'  => $this->rank($memoryRows, $queryVector, $cfg['recall_top_k'], $cfg['min_similarity']),
            'knowledge' => $this->rank($knowledgeRows, $queryVector, $cfg['knowledge_top_k'], $cfg['min_similarity']),
        ];
    }

    /** ذخیره‌ی یک پیام با بردارش (بردار می‌تواند از قبل حساب شده باشد) */
    public function storeMessage(string $chatId, string $role, string $content, ?array $vector = null): AiMemory
    {
        return AiMemory::create([
            'chat_id'   => $chatId,
            'kind'      => AiMemory::KIND_MESSAGE,
            'role'      => $role,
            'content'   => $content,
            'embedding' => $vector ? AiMemory::packVector($vector) : null,
        ]);
    }

    /** افزودن متن به پایگاه دانش سازمان؛ تعداد تکه‌ها را برمی‌گرداند */
    public function addKnowledge(string $text, string $source, ?int $tenantId = null): int
    {
        $chunks = $this->chunk($text, config('ai.memory.chunk_chars'));
        $count = 0;

        foreach (array_chunk($chunks, 50) as $batch) {
            $vectors = $this->client->embed($batch);
            foreach ($batch as $i => $chunk) {
                $attrs = [
                    'chat_id'   => null,
                    'kind'      => AiMemory::KIND_KNOWLEDGE,
                    'source'    => $source,
                    'content'   => $chunk,
                    'embedding' => AiMemory::packVector($vectors[$i]),
                ];
                if ($tenantId !== null) {
                    $attrs['tenant_id'] = $tenantId;   // در غیر این صورت BelongsToTenant از TenantContext پر می‌کند
                }
                AiMemory::create($attrs);
                $count++;
            }
        }

        return $count;
    }

    /** پاک کردن حافظه‌ی گفتگوی یک کاربر (برای دستور /forget) */
    public function forget(string $chatId): int
    {
        return AiMemory::query()->where('chat_id', $chatId)->delete();
    }

    public function countToday(string $chatId): int
    {
        return AiMemory::query()
            ->where('chat_id', $chatId)
            ->where('kind', AiMemory::KIND_MESSAGE)
            ->where('role', 'user')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    // ---------------------------------------------------------------

    protected function rank(Collection $rows, array $query, int $topK, float $min): Collection
    {
        $qNorm = $this->norm($query);
        if ($qNorm == 0.0) {
            return collect();
        }

        return $rows
            ->map(function (AiMemory $row) use ($query, $qNorm) {
                $v = $row->vector();
                $row->setAttribute('score', $this->cosine($query, $qNorm, $v));

                return $row;
            })
            ->filter(fn ($r) => $r->score >= $min)
            ->sortByDesc('score')
            ->take($topK)
            ->values();
    }

    protected function cosine(array $a, float $aNorm, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $bb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $bb += $b[$i] * $b[$i];
        }

        return $bb > 0 ? $dot / ($aNorm * sqrt($bb)) : 0.0;
    }

    protected function norm(array $v): float
    {
        $s = 0.0;
        foreach ($v as $x) {
            $s += $x * $x;
        }

        return sqrt($s);
    }

    /** تکه‌تکه کردن بر اساس پاراگراف، با سقف طول هر تکه */
    protected function chunk(string $text, int $max): array
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text));
        $paragraphs = preg_split("/\n{2,}/u", $text) ?: [];
        $chunks = [];
        $buf = '';

        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            // پاراگراف خیلی بلند را بر اساس جمله می‌شکنیم
            $pieces = mb_strlen($p) > $max ? preg_split('/(?<=[\.\!\?؟。])\s+/u', $p) : [$p];
            foreach ($pieces as $piece) {
                if ($buf !== '' && mb_strlen($buf) + mb_strlen($piece) + 2 > $max) {
                    $chunks[] = $buf;
                    $buf = '';
                }
                while (mb_strlen($piece) > $max) {   // جمله‌ی بی‌نقطه‌ی خیلی بلند
                    $chunks[] = mb_substr($piece, 0, $max);
                    $piece = mb_substr($piece, $max);
                }
                $buf = $buf === '' ? $piece : $buf."\n\n".$piece;
            }
        }
        if ($buf !== '') {
            $chunks[] = $buf;
        }

        return $chunks;
    }
}
