<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\OpenAiClient;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * آزمایش اتصال از روی سرور، بدون ربات:
 *   php artisan ai:test                      ← فقط اتصال به OpenAI (از پشت پروکسی) را چک می‌کند
 *   php artisan ai:test 3 "سلام، اسم من جواده"  ← گفتگوی کامل با حافظه، با chat_id آزمایشی
 */
class AiTest extends Command
{
    protected $signature = 'ai:test {tenant?} {message?} {--chat=cli-test}';

    protected $description = 'آزمایش اتصال به OpenAI و حافظه‌ی ربات';

    public function handle(OpenAiClient $client): int
    {
        if (! $this->argument('tenant')) {
            $this->line('base_url: '.config('ai.base_url'));
            $this->line('proxy:    '.(config('ai.proxy') ?: '—'));
            $started = microtime(true);
            $vec = $client->embed(['آزمایش'])[0] ?? [];
            $this->info('embedding OK — '.count($vec).' بُعد');
            $this->info('chat OK — '.$client->chat([['role' => 'user', 'content' => 'فقط بنویس: سلام']]));
            $this->line('زمان: '.round(microtime(true) - $started, 2).'s');

            return self::SUCCESS;
        }

        $tenant = Tenant::findOrFail($this->argument('tenant'));
        $message = $this->argument('message') ?? 'سلام';

        $answer = TenantContext::forTenant($tenant, fn () => app(AiAssistant::class)
            ->reply($this->option('chat'), $message, 'کاربر آزمایشی'));

        $this->line($answer);

        return self::SUCCESS;
    }
}
