<?php

namespace Tests\Feature;

use App\Models\AiMemory;
use App\Models\BotState;
use App\Models\BroadcastMessage;
use App\Models\Province;
use App\Models\Representative;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\BotConnector;
use App\Services\BroadcastSender;
use App\Support\BotText;
use App\Support\Messenger;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ربات تلگرام در کنار بله، و حالت گفتگو با دستیار هوشمند.
 */
class TelegramAndAiBotTest extends TestCase
{
    use RefreshDatabase;

    private int $updateId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::forget();
        BotText::flush();

        config(['ai.api_key' => 'sk-test', 'ai.base_url' => 'https://openai.test/v1']);

        Http::fake([
            'openai.test/v1/embeddings'       => Http::response(['data' => [['index' => 0, 'embedding' => [0.1, 0.2, 0.3]]]], 200),
            'openai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'سلام **جواد**']]]], 200),
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 77, 'username' => 'org_tg_bot']], 200),
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        BotText::flush();
        parent::tearDown();
    }

    // ------------------------------------------------------------------

    private function makeTenant(): Tenant
    {
        $tenant = Tenant::create([
            'name'                 => 'org',
            'owner_name'           => 'org',
            'email'                => 'org@example.com',
            'status'               => Tenant::STATUS_ACTIVE,
            'bot_token'            => 'BALE-TOKEN',
            'telegram_bot_token'   => '12345:TG-TOKEN',
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => now()->addMonth(),
        ]);

        TenantContext::forTenant($tenant, fn () => Province::create(['name' => 'تهران']));

        return $tenant;
    }

    private function setting(Tenant $tenant, string $key, string $value): void
    {
        TenantContext::forTenant($tenant, fn () => Setting::set($key, $value));
        BotText::flush();
    }

    private function tg(Tenant $tenant, array $payload, ?string $secret = null)
    {
        BotText::flush();
        $response = $this->postJson(
            '/api/bot/telegram/' . $tenant->webhook_secret,
            ['update_id' => $this->updateId++] + $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $secret ?? $tenant->telegramSecretToken()],
        );
        TenantContext::forget();

        return $response;
    }

    private function bale(Tenant $tenant, array $payload)
    {
        BotText::flush();
        $response = $this->postJson('/api/bot/webhook/' . $tenant->webhook_secret, ['update_id' => $this->updateId++] + $payload);
        TenantContext::forget();

        return $response->assertOk();
    }

    private function msg(string $chatId, array $extra): array
    {
        return ['message' => ['message_id' => $this->updateId, 'chat' => ['id' => $chatId], 'from' => ['id' => $chatId, 'first_name' => 'Javad']] + $extra];
    }

    private function cb(string $chatId, string $data): array
    {
        return ['callback_query' => ['id' => 'cb' . $this->updateId, 'from' => ['id' => $chatId], 'message' => ['message_id' => 77, 'chat' => ['id' => $chatId]], 'data' => $data]];
    }

    private function state(Tenant $tenant, string $chatKey): ?BotState
    {
        return TenantContext::forTenant($tenant, fn () => BotState::where('chat_id', $chatKey)->first());
    }

    // ------------------------------------------------------------------
    // تلگرام
    // ------------------------------------------------------------------

    public function test_telegram_webhook_rejects_requests_without_the_secret_header(): void
    {
        $tenant = $this->makeTenant();

        $this->tg($tenant, $this->msg('555', ['text' => '/start']), 'wrong')->assertForbidden();

        $this->assertNull($this->state($tenant, 'tg:555'));
        Http::assertNothingSent();
    }

    public function test_telegram_open_access_user_is_stored_with_prefixed_chat_key_and_answered_on_telegram(): void
    {
        $tenant = $this->makeTenant();
        $this->setting($tenant, 'bot_open_access', '1');

        $this->tg($tenant, $this->msg('555', ['text' => '/start']))->assertOk();

        $rep = TenantContext::forTenant($tenant, fn () => Representative::first());
        $this->assertSame('tg:555', $rep->telegram_chat_id);
        $this->assertNull($rep->chat_id, 'شناسه‌ی بله نباید با شناسه‌ی تلگرام پر شود.');
        $this->assertNotNull($this->state($tenant, 'tg:555'));
        $this->assertNull($this->state($tenant, '555'), 'چت تلگرام نباید با چت بله‌ی هم‌شماره قاطی شود.');

        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://api.telegram.org/bot12345:TG-TOKEN/sendMessage')
            && $r['chat_id'] === '555');
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'tapi.bale.ai'));
    }

    public function test_same_number_on_bale_and_telegram_are_separate_chats(): void
    {
        $tenant = $this->makeTenant();
        $this->setting($tenant, 'bot_open_access', '1');

        $this->bale($tenant, $this->msg('555', ['text' => '/start']));
        $this->tg($tenant, $this->msg('555', ['text' => '/start']))->assertOk();

        $reps = TenantContext::forTenant($tenant, fn () => Representative::orderBy('id')->get());
        $this->assertCount(2, $reps);
        $this->assertSame('555', $reps[0]->chat_id);
        $this->assertSame('tg:555', $reps[1]->telegram_chat_id);
    }

    public function test_representative_can_be_connected_on_both_messengers(): void
    {
        $tenant = $this->makeTenant();

        $rep = TenantContext::forTenant($tenant, fn () => Representative::create([
            'province_id'  => Province::first()->id,
            'first_name'   => 'جواد',
            'last_name'    => 'شفا',
            'phone_number' => '09121234567',
            'chat_id'      => '777',
            'is_connected' => true,
        ]));

        $this->tg($tenant, $this->msg('555', ['text' => '/start']))->assertOk();
        $this->tg($tenant, $this->msg('555', ['contact' => ['phone_number' => '+989121234567', 'user_id' => '555']]))->assertOk();

        $rep = TenantContext::forTenant($tenant, fn () => $rep->fresh());
        $this->assertSame('777', $rep->chat_id, 'وصل شدن در تلگرام نباید اتصال بله را پاک کند.');
        $this->assertSame('tg:555', $rep->telegram_chat_id);
        $this->assertTrue((bool) $rep->is_connected);
    }

    public function test_connecting_telegram_sends_secret_token_and_saves_username(): void
    {
        $tenant = $this->makeTenant();

        $result = app(BotConnector::class)->setWebhook($tenant, Messenger::TELEGRAM);

        $this->assertTrue($result['ok']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.telegram.org/bot12345:TG-TOKEN/setWebhook')
            && $r['secret_token'] === $tenant->telegramSecretToken()
            && str_contains($r['url'], '/api/bot/telegram/'));

        $tenant->refresh();
        $this->assertNotNull($tenant->telegram_connected_at);
        $this->assertSame('org_tg_bot', $tenant->telegram_bot_username);
        $this->assertNull($tenant->bot_connected_at, 'اتصال تلگرام نباید وضعیت بله را عوض کند.');
    }

    public function test_disconnecting_telegram_keeps_the_bale_bot(): void
    {
        $tenant = $this->makeTenant();

        app(BotConnector::class)->disconnect($tenant, Messenger::TELEGRAM);

        $tenant->refresh();
        $this->assertNull($tenant->telegram_bot_token);
        $this->assertSame('BALE-TOKEN', $tenant->bot_token);
    }

    public function test_broadcast_reaches_representative_on_both_messengers(): void
    {
        $tenant = $this->makeTenant();

        $message = TenantContext::forTenant($tenant, function () {
            Representative::create([
                'province_id' => Province::first()->id, 'first_name' => 'a', 'last_name' => 'b',
                'chat_id' => '777', 'telegram_chat_id' => 'tg:555', 'is_connected' => true,
            ]);

            return BroadcastMessage::create(['title' => 't', 'body' => 'اطلاعیه', 'schedule_type' => 'instant', 'status' => 'pending']);
        });

        app(BroadcastSender::class)->send($message);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'tapi.bale.ai/botBALE-TOKEN/sendMessage') && $r['chat_id'] === '777');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.telegram.org/bot12345:TG-TOKEN/sendMessage') && $r['chat_id'] === '555');
        $this->assertSame(2, $message->fresh()->sent_count);
    }

    // ------------------------------------------------------------------
    // دستیار هوشمند
    // ------------------------------------------------------------------

    private function loggedInTelegramUser(Tenant $tenant): void
    {
        $this->setting($tenant, 'bot_open_access', '1');
        $this->tg($tenant, $this->msg('555', ['text' => '/start']))->assertOk();
    }

    public function test_ai_button_is_hidden_when_disabled_and_free_text_just_shows_menu(): void
    {
        $tenant = $this->makeTenant();
        $this->loggedInTelegramUser($tenant);

        $this->tg($tenant, $this->msg('555', ['text' => 'سلام']))->assertOk();

        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'openai.test'));
        Http::assertNotSent(fn (HttpRequest $r) => str_contains((string) ($r->data()['reply_markup'] ?? ''), 'ai_chat_start'));
        $this->assertSame('idle', $this->state($tenant, 'tg:555')->step);
    }

    public function test_ai_chat_mode_answers_remembers_and_exits(): void
    {
        $tenant = $this->makeTenant();
        $this->setting($tenant, 'ai_enabled', '1');
        $this->loggedInTelegramUser($tenant);

        Http::assertSent(fn (HttpRequest $r) => str_contains((string) ($r->data()['reply_markup'] ?? ''), 'ai_chat_start'));

        // متن آزاد بیرون از حالت گفتگو به هوش مصنوعی نمی‌رود
        $this->tg($tenant, $this->msg('555', ['text' => 'سلام']))->assertOk();
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'openai.test'));

        $this->tg($tenant, $this->cb('555', 'ai_chat_start'))->assertOk();
        $this->assertSame('ai_chat', $this->state($tenant, 'tg:555')->step);

        $this->tg($tenant, $this->msg('555', ['text' => 'اسم من جواده']))->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'openai.test/v1/chat/completions'));
        // پاسخ مدل به Markdown بله/تلگرام تبدیل شده و دکمه‌ی خروج دارد
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.telegram.org')
            && str_contains($r->url(), 'sendMessage')
            && $r['text'] === 'سلام *جواد*'
            && str_contains((string) ($r->data()['reply_markup'] ?? ''), 'ai_chat_exit'));

        $memories = TenantContext::forTenant($tenant, fn () => AiMemory::where('chat_id', 'tg:555')->count());
        $this->assertSame(2, $memories, 'پیام کاربر و پاسخ دستیار باید با کلید چت تلگرام ذخیره شوند.');

        $this->tg($tenant, $this->cb('555', 'ai_chat_exit'))->assertOk();
        $this->assertSame('idle', $this->state($tenant, 'tg:555')->step);
    }

    public function test_forget_command_clears_memory_inside_ai_chat(): void
    {
        $tenant = $this->makeTenant();
        $this->setting($tenant, 'ai_enabled', '1');
        $this->loggedInTelegramUser($tenant);

        $this->tg($tenant, $this->cb('555', 'ai_chat_start'))->assertOk();
        $this->tg($tenant, $this->msg('555', ['text' => 'اسم من جواده']))->assertOk();
        $this->tg($tenant, $this->msg('555', ['text' => '/forget']))->assertOk();

        $this->assertSame(0, TenantContext::forTenant($tenant, fn () => AiMemory::where('chat_id', 'tg:555')->count()));
    }

    public function test_settings_page_saves_telegram_token_and_ai_toggle(): void
    {
        $tenant = $this->makeTenant();
        $admin = \App\Models\User::factory()->create(['tenant_id' => $tenant->id, 'is_super_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('اتصال به تلگرام')
            ->assertSee('دستیار هوشمند');

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'telegram_bot_token' => '99999:NEW_token-x',
            'ai_enabled'         => '1',
            'ai_system_prompt'   => 'تو دستیار هستی',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame('99999:NEW_token-x', $tenant->telegram_bot_token);
        $this->assertSame('1', TenantContext::forTenant($tenant, fn () => Setting::get('ai_enabled')));
        $this->assertSame('تو دستیار هستی', TenantContext::forTenant($tenant, fn () => Setting::get('ai_system_prompt')));

        $this->actingAs($admin)->post(route('admin.settings.update'), ['telegram_bot_token' => 'not a token'])
            ->assertSessionHasErrors('telegram_bot_token');
    }
}
