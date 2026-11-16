<?php

namespace Tests\Feature;

use App\Console\Commands\SetupFatemiyehSurvey;
use App\Exports\ReportsExport;
use App\Models\BotState;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Report;
use App\Models\Representative;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BotText;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SurveyBotTest extends TestCase
{
    use RefreshDatabase;

    private int $updateId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::forget();
        BotText::flush();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200)]);
        // ۲۰:۳۰ به وقت تهران — وسط یک شب اجرا
        Carbon::setTestNow(Carbon::parse('2026-10-24 17:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TenantContext::forget();
        BotText::flush();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function makeTenant(string $name = 'fatemiyeh'): Tenant
    {
        return Tenant::create([
            'name'                 => $name,
            'owner_name'           => $name,
            'email'                => $name . '@example.com',
            'status'               => Tenant::STATUS_ACTIVE,
            'bot_token'            => 'TOKEN',
            'bot_username'         => 'fatemiyeh_bot',
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => now()->addMonth(),
        ]);
    }

    private function surveyTenant(): Tenant
    {
        $tenant = $this->makeTenant();
        $this->artisan('survey:fatemiyeh', ['tenant' => (string) $tenant->id])->assertSuccessful();
        TenantContext::forget();
        BotText::flush();

        return $tenant;
    }

    private function send(Tenant $tenant, array $payload)
    {
        BotText::flush();
        $response = $this->postJson('/api/bot/webhook/' . $tenant->webhook_secret, ['update_id' => $this->updateId++] + $payload);
        TenantContext::forget();

        return $response->assertOk();
    }

    private function text(Tenant $tenant, string $chatId, string $text)
    {
        return $this->send($tenant, ['message' => [
            'message_id' => $this->updateId,
            'chat'       => ['id' => $chatId],
            'from'       => ['id' => $chatId, 'first_name' => 'Profile'],
            'text'       => $text,
        ]]);
    }

    private function contact(Tenant $tenant, string $chatId, string $phone)
    {
        return $this->send($tenant, ['message' => [
            'message_id' => $this->updateId,
            'chat'       => ['id' => $chatId],
            'from'       => ['id' => $chatId, 'first_name' => 'Profile'],
            'contact'    => ['phone_number' => $phone, 'first_name' => 'Profile', 'user_id' => $chatId],
        ]]);
    }

    private function tap(Tenant $tenant, string $chatId, string $data)
    {
        return $this->send($tenant, ['callback_query' => [
            'id'      => (string) $this->updateId,
            'from'    => ['id' => $chatId],
            'message' => ['message_id' => 77, 'chat' => ['id' => $chatId]],
            'data'    => $data,
        ]]);
    }

    private function state(Tenant $tenant, string $chatId): ?BotState
    {
        return TenantContext::forTenant($tenant, fn () => BotState::where('chat_id', $chatId)->first());
    }

    /** اولین گزینه‌ی سؤال جاری را می‌زند */
    private function answerCurrent(Tenant $tenant, string $chatId, int $optionIndex = 0): void
    {
        $optionId = TenantContext::forTenant($tenant, function () use ($chatId, $optionIndex) {
            $state = BotState::where('chat_id', $chatId)->first();
            $field = CategoryField::find($state->field_queue[0]);

            return $field->options()->get()[$optionIndex]->id;
        });

        $this->tap($tenant, $chatId, 'opt_' . $optionId);
    }

    private function answerAll(Tenant $tenant, string $chatId): void
    {
        for ($i = 0; $i < count(SetupFatemiyehSurvey::QUESTIONS); $i++) {
            $this->answerCurrent($tenant, $chatId);
        }
    }

    /** متن همه‌ی پیام‌هایی که ربات فرستاده */
    private function sentTexts(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/sendMessage'))
            ->map(fn ($pair) => $pair[0]->data()['text'] ?? '')
            ->values()
            ->all();
    }

    private function lastText(): string
    {
        $texts = $this->sentTexts();

        return end($texts) ?: '';
    }

    private function reportsCount(Tenant $tenant): int
    {
        return TenantContext::forTenant($tenant, fn () => Report::count());
    }

    private function register(Tenant $tenant, string $chatId, string $name = 'سید علی حسینی', string $phone = '989121234567'): void
    {
        $this->text($tenant, $chatId, '/start');
        $this->text($tenant, $chatId, $name);
        $this->contact($tenant, $chatId, $phone);
    }

    // ------------------------------------------------------------------
    // tests
    // ------------------------------------------------------------------

    public function test_setup_command_builds_the_fatemiyeh_form_and_turns_on_survey_mode(): void
    {
        $tenant = $this->surveyTenant();

        TenantContext::forTenant($tenant, function () {
            $category = Category::where('name', 'تئاتر فاطمیه')->firstOrFail();
            $fields   = $category->fields()->with('options')->get();

            $this->assertCount(count(SetupFatemiyehSurvey::QUESTIONS), $fields);
            $this->assertTrue($fields->every(fn ($f) => $f->type === 'option' && $f->is_required));
            $this->assertSame(['تالار اندیشه', 'تالار سوره مهر', 'تالار ماه'], $fields[0]->options->pluck('label')->all());
            $this->assertCount(8, $fields[1]->options);

            $this->assertSame('1', Setting::get('bot_survey_mode'));
            $this->assertSame((string) $category->id, Setting::get('survey_category_id'));
            $this->assertSame('nightly', Setting::get('survey_repeat'));
        });

        // دوباره اجرا کردن چیزی را تکراری نمی‌سازد
        $this->artisan('survey:fatemiyeh', ['tenant' => $tenant->email])->assertSuccessful();
        TenantContext::forTenant($tenant, function () {
            $this->assertSame(1, Category::where('name', 'تئاتر فاطمیه')->count());
            $this->assertSame(count(SetupFatemiyehSurvey::QUESTIONS), CategoryField::count());
        });
    }

    public function test_setup_command_fails_for_unknown_tenant(): void
    {
        $this->artisan('survey:fatemiyeh', ['tenant' => '999'])->assertFailed();
    }

    public function test_full_survey_flow_collects_name_phone_and_answers(): void
    {
        $tenant = $this->surveyTenant();

        $this->text($tenant, '1001', '/start');
        $this->assertStringContainsString('تئاتر فاطمیه', $this->lastText());
        $this->assertStringContainsString('نام و نام خانوادگی', $this->lastText());
        $this->assertSame('survey_ask_name', $this->state($tenant, '1001')->step);

        // یک کلمه کافی نیست
        $this->text($tenant, '1001', 'علی');
        $this->assertStringContainsString('کامل', $this->lastText());
        $this->assertSame('survey_ask_name', $this->state($tenant, '1001')->step);

        $this->text($tenant, '1001', '  سید   علي حسینی ');
        $this->assertSame('survey_ask_phone', $this->state($tenant, '1001')->step);

        // دکمه‌ی اشتراک شماره در کیبورد هست
        $askPhone = collect(Http::recorded())->map(fn ($p) => $p[0])->last(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendMessage'));
        $this->assertStringContainsString('request_contact', $askPhone->data()['reply_markup']);

        $this->contact($tenant, '1001', '989121234567');

        $state = $this->state($tenant, '1001');
        $this->assertSame('answering_field', $state->step);
        $this->assertCount(count(SetupFatemiyehSurvey::QUESTIONS), $state->field_queue);
        $this->assertStringContainsString('سؤال ۱ از ۷', $this->lastText());
        $this->assertStringContainsString('امشب در کدام تالار بودید؟', $this->lastText());

        TenantContext::forTenant($tenant, function () {
            $rep = Representative::firstOrFail();
            $this->assertSame('سید علی', $rep->first_name);
            $this->assertSame('حسینی', $rep->last_name);
            $this->assertSame('09121234567', $rep->phone_number);
            $this->assertSame('1001', $rep->chat_id);
            $this->assertSame('تهران', $rep->province->name);
        });

        // متن به‌جای دکمه
        $this->text($tenant, '1001', 'تالار ماه');
        $this->assertStringContainsString('گزینه', $this->lastText());
        $this->assertSame(0, count($this->state($tenant, '1001')->draft_data ?? []));

        $this->answerCurrent($tenant, '1001', 2); // تالار ماه
        $this->assertStringContainsString('سؤال ۲ از ۷', $this->lastText());

        for ($i = 1; $i < count(SetupFatemiyehSurvey::QUESTIONS); $i++) {
            $this->answerCurrent($tenant, '1001');
        }

        $this->assertStringContainsString('سپاسگزاریم', $this->lastText());
        $this->assertSame('survey_done', $this->state($tenant, '1001')->step);

        TenantContext::forTenant($tenant, function () {
            $report = Report::with('representative')->firstOrFail();
            $this->assertCount(count(SetupFatemiyehSurvey::QUESTIONS), $report->data);
            $this->assertSame('تالار ماه', $report->data[0]['value']);
            $this->assertSame('نقطه عطف', $report->data[1]['value']);
            $this->assertSame('09121234567', $report->representative->phone_number);
            $this->assertNull($report->department_id);
            $this->assertSame('1405-08', $report->jalali_month);
        });
    }

    public function test_typed_phone_is_accepted_and_invalid_phone_is_rejected(): void
    {
        $tenant = $this->surveyTenant();

        $this->text($tenant, '2002', '/start');
        $this->text($tenant, '2002', 'زهرا محمدی');

        $this->text($tenant, '2002', '12345');
        $this->assertStringContainsString('معتبر نیست', $this->lastText());
        $this->assertSame('survey_ask_phone', $this->state($tenant, '2002')->step);

        $this->text($tenant, '2002', '۰۹۳۵ ۱۲۳ ۴۵۶۷');
        $this->assertSame('answering_field', $this->state($tenant, '2002')->step);

        TenantContext::forTenant($tenant, fn () => $this->assertSame('09351234567', Representative::firstOrFail()->phone_number));
    }

    public function test_one_response_per_night(): void
    {
        $tenant = $this->surveyTenant();

        $this->register($tenant, '3003');
        $this->answerAll($tenant, '3003');
        $this->assertSame(1, $this->reportsCount($tenant));

        // همان شب، بعد از نیمه‌شب تهران (۰۱:۰۰)
        Carbon::setTestNow(Carbon::parse('2026-10-24 21:30:00', 'UTC'));
        $this->text($tenant, '3003', '/start');
        $this->assertStringContainsString('ثبت شده', $this->lastText());
        $this->assertSame('survey_done', $this->state($tenant, '3003')->step);

        // پیام دلخواه هم همان جواب را می‌گیرد
        $this->text($tenant, '3003', 'سلام');
        $this->assertStringContainsString('ثبت شده', $this->lastText());
        $this->assertSame(1, $this->reportsCount($tenant));

        // شب بعد: بدون پرسیدن دوباره‌ی نام و شماره مستقیم سؤال‌ها
        Carbon::setTestNow(Carbon::parse('2026-10-25 16:00:00', 'UTC'));
        $this->text($tenant, '3003', '/start');
        $this->assertSame('answering_field', $this->state($tenant, '3003')->step);
        $this->assertStringContainsString('سؤال ۱ از ۷', $this->lastText());

        $this->answerAll($tenant, '3003');
        $this->assertSame(2, $this->reportsCount($tenant));
        TenantContext::forTenant($tenant, fn () => $this->assertSame(1, Representative::count()));
    }

    public function test_same_phone_from_another_device_reuses_the_participant(): void
    {
        $tenant = $this->surveyTenant();

        $this->register($tenant, '4004', 'علی محمدی', '09121112233');
        $this->answerAll($tenant, '4004');

        $this->register($tenant, '4005', 'علی محمدی', '+989121112233');

        $this->assertStringContainsString('ثبت شده', $this->lastText());
        $this->assertSame(1, $this->reportsCount($tenant));
        TenantContext::forTenant($tenant, function () {
            $this->assertSame(1, Representative::count());
            $this->assertSame('4005', Representative::first()->chat_id);
        });
    }

    public function test_once_mode_blocks_the_next_night_and_unlimited_allows_repeats(): void
    {
        $tenant = $this->surveyTenant();
        TenantContext::forTenant($tenant, fn () => Setting::set('survey_repeat', 'once'));

        $this->register($tenant, '5005');
        $this->answerAll($tenant, '5005');

        Carbon::setTestNow(Carbon::parse('2026-10-26 16:00:00', 'UTC'));
        $this->text($tenant, '5005', '/start');
        $this->assertStringContainsString('قبلاً ثبت شده', $this->lastText());

        TenantContext::forTenant($tenant, fn () => Setting::set('survey_repeat', 'unlimited'));
        $this->text($tenant, '5005', '/start');
        $this->answerAll($tenant, '5005');
        $this->assertSame(2, $this->reportsCount($tenant));
    }

    public function test_back_button_and_stale_buttons(): void
    {
        $tenant = $this->surveyTenant();
        $this->register($tenant, '6006');

        // سؤال اول دکمه‌ی بازگشت ندارد
        $first = collect(Http::recorded())->map(fn ($p) => $p[0])->last(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendMessage'));
        $this->assertStringNotContainsString('go_back', $first->data()['reply_markup']);

        $hallOptionId = TenantContext::forTenant($tenant, fn () => CategoryField::orderBy('sort_order')->first()->options()->first()->id);

        $this->answerCurrent($tenant, '6006');
        $this->assertCount(1, $this->state($tenant, '6006')->draft_data);

        // دکمه‌ی کهنه‌ی سؤال اول روی سؤال دوم اثری ندارد
        $this->tap($tenant, '6006', 'opt_' . $hallOptionId);
        $this->assertCount(1, $this->state($tenant, '6006')->draft_data);

        // دکمه‌های جریان عادی (منو، پیش‌نمایش) نادیده گرفته می‌شوند
        $this->tap($tenant, '6006', 'main_start_report');
        $this->tap($tenant, '6006', 'confirm_report');
        $this->assertSame('answering_field', $this->state($tenant, '6006')->step);
        $this->assertSame(0, $this->reportsCount($tenant));

        // بازگشت → دوباره سؤال اول
        $this->tap($tenant, '6006', 'go_back');
        $state = $this->state($tenant, '6006');
        $this->assertCount(0, $state->draft_data);
        $this->assertCount(count(SetupFatemiyehSurvey::QUESTIONS), $state->field_queue);
        $this->assertStringContainsString('سؤال ۱ از ۷', $this->lastText());
    }

    public function test_restart_mid_survey_starts_over_without_duplicate_registration(): void
    {
        $tenant = $this->surveyTenant();
        $this->register($tenant, '7007');
        $this->answerCurrent($tenant, '7007');
        $this->answerCurrent($tenant, '7007');

        $this->text($tenant, '7007', '/start');
        $state = $this->state($tenant, '7007');
        $this->assertSame('answering_field', $state->step);
        $this->assertCount(0, $state->draft_data);

        $this->answerAll($tenant, '7007');
        $this->assertSame(1, $this->reportsCount($tenant));
        TenantContext::forTenant($tenant, fn () => $this->assertSame(1, Representative::count()));
    }

    public function test_inactive_survey_form_closes_the_survey(): void
    {
        $tenant = $this->surveyTenant();
        TenantContext::forTenant($tenant, fn () => Category::query()->update(['is_active' => false]));

        $this->text($tenant, '8008', '/start');
        $this->assertStringContainsString('فعال نیست', $this->lastText());
        $this->assertNotSame('survey_ask_name', $this->state($tenant, '8008')->step);
    }

    public function test_normal_mode_is_unchanged_when_survey_is_off(): void
    {
        $tenant = $this->makeTenant('normal');

        $this->text($tenant, '9009', '/start');

        $this->assertSame('waiting_for_contact', $this->state($tenant, '9009')->step);
        $this->assertSame(BotText::defaults()['welcome_message'], $this->lastText());
    }

    public function test_settings_page_shows_and_saves_survey_options(): void
    {
        $tenant = $this->surveyTenant();
        $admin  = User::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'admin',
            'email'          => 'admin@example.com',
            'password'       => 'password123',
            'is_super_admin' => true,
        ]);

        $categoryId = TenantContext::forTenant($tenant, fn () => Category::first()->id);

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('حالت نظرسنجی')
            ->assertSee('تئاتر فاطمیه');

        $this->actingAs($admin)->post('/admin/settings', [
            'bot_survey_mode'    => '0',
            'survey_category_id' => $categoryId,
            'survey_repeat'      => 'once',
        ])->assertRedirect();

        TenantContext::forTenant($tenant, function () use ($categoryId) {
            $this->assertSame('0', Setting::get('bot_survey_mode'));
            $this->assertSame((string) $categoryId, Setting::get('survey_category_id'));
            $this->assertSame('once', Setting::get('survey_repeat'));
        });

        // دسته‌بندی سازمان دیگر پذیرفته نیست
        $other      = $this->makeTenant('other');
        $foreignCat = TenantContext::forTenant($other, fn () => Category::create(['name' => 'x', 'sort_order' => 1, 'is_active' => true])->id);
        TenantContext::forget();

        $this->actingAs($admin)->post('/admin/settings', ['survey_category_id' => $foreignCat])
            ->assertSessionHasErrors('survey_category_id');
    }

    public function test_export_includes_phone_and_tehran_time(): void
    {
        $tenant = $this->surveyTenant();
        $this->register($tenant, '1111', 'مریم احمدی', '09120001122');
        $this->answerAll($tenant, '1111');

        $export = TenantContext::forTenant($tenant, fn () => new ReportsExport(
            Report::with(['representative.province', 'department', 'category'])->get()
        ));

        $headings = $export->headings();
        $row      = $export->array()[0];

        $this->assertSame('09120001122', $row[array_search('شماره تماس', $headings)]);
        $this->assertSame('1405/08/02 20:30', $row[array_search('زمان ثبت (شمسی، تهران)', $headings)]);
        $this->assertSame('تالار اندیشه', $row[array_search('امشب در کدام تالار بودید؟', $headings)]);
    }

    public function test_survey_tenant_gets_a_higher_webhook_limit_keyed_by_tenant(): void
    {
        $survey = $this->surveyTenant();
        $normal = $this->makeTenant('normal');

        $limiter = RateLimiter::limiter('bot-webhook');

        $limitFor = function (Tenant $tenant) use ($limiter) {
            $request = \Illuminate\Http\Request::create('/api/bot/webhook/' . $tenant->webhook_secret, 'POST');
            $request->setRouteResolver(function () use ($request) {
                return app('router')->getRoutes()->match($request);
            });

            return $limiter($request);
        };

        $this->assertSame(3000, $limitFor($survey)->maxAttempts);
        $this->assertSame(300, $limitFor($normal)->maxAttempts);
        $this->assertSame((string) $survey->id, (string) $limitFor($survey)->key);
    }
}
