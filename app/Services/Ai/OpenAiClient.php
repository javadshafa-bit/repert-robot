<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * لایه‌ی نازک روی API اوپن‌ای‌آی (Chat Completions + Embeddings).
 * پروکسی و base_url قابل تنظیم‌اند تا از سرور داخل ایران هم کار کند.
 */
class OpenAiClient
{
    protected function http(): PendingRequest
    {
        $key = config('ai.api_key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY تنظیم نشده است.');
        }

        $options = [];
        if ($proxy = config('ai.proxy')) {
            $options['proxy'] = $proxy;
        }

        return Http::withToken($key)
            ->baseUrl(config('ai.base_url'))
            ->acceptJson()
            ->timeout(config('ai.timeout'))
            ->connectTimeout(10)
            ->retry(2, 800, throw: false)
            ->withOptions($options);
    }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     */
    public function chat(array $messages): string
    {
        $response = $this->http()->post('/chat/completions', [
            'model'    => config('ai.chat_model'),
            'messages' => $messages,
            // مدل‌های GPT-5 به‌جای max_tokens این را می‌پذیرند و temperature سفارشی را قبول نمی‌کنند
            'max_completion_tokens' => config('ai.max_output_tokens'),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI chat error '.$response->status().': '.mb_substr($response->body(), 0, 500));
        }

        return trim((string) $response->json('choices.0.message.content', ''));
    }

    /**
     * @param string[] $texts
     * @return array<int, float[]> به همان ترتیب ورودی
     */
    public function embed(array $texts): array
    {
        $texts = array_values(array_map(fn ($t) => mb_substr((string) $t, 0, 8000), $texts));
        if ($texts === []) {
            return [];
        }

        $response = $this->http()->post('/embeddings', [
            'model' => config('ai.embedding_model'),
            'input' => $texts,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embedding error '.$response->status().': '.mb_substr($response->body(), 0, 500));
        }

        $out = [];
        foreach ($response->json('data', []) as $row) {
            $out[$row['index']] = $row['embedding'];
        }
        ksort($out);

        return array_values($out);
    }
}
