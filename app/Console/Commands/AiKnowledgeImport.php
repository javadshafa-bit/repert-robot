<?php

namespace App\Console\Commands;

use App\Models\AiMemory;
use App\Models\Tenant;
use App\Services\Ai\AiMemoryService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * بارگذاری پایگاه دانش یک سازمان از فایل متنی (.txt یا .md).
 *   php artisan ai:knowledge 3 storage/app/faq.txt
 *   php artisan ai:knowledge 3 storage/app/faq.txt --replace   ← نسخه‌ی قبلی همین فایل پاک می‌شود
 */
class AiKnowledgeImport extends Command
{
    protected $signature = 'ai:knowledge {tenant : شناسه‌ی سازمان} {file : مسیر فایل متنی} {--replace}';

    protected $description = 'افزودن متن به پایگاه دانش هوش مصنوعی یک سازمان (RAG)';

    public function handle(AiMemoryService $memory): int
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("فایل پیدا نشد: {$path}");

            return self::FAILURE;
        }

        $text = file_get_contents($path);
        $source = basename($path);

        $count = TenantContext::forTenant($tenant, function () use ($memory, $text, $source, $tenant) {
            if ($this->option('replace')) {
                $deleted = AiMemory::query()
                    ->whereNull('chat_id')
                    ->where('kind', AiMemory::KIND_KNOWLEDGE)
                    ->where('source', $source)
                    ->delete();
                $this->line("{$deleted} تکه‌ی قبلی حذف شد.");
            }

            return $memory->addKnowledge($text, $source, $tenant->id);
        });

        $this->info("{$count} تکه از «{$source}» به پایگاه دانش سازمان {$tenant->id} اضافه شد.");

        return self::SUCCESS;
    }
}
