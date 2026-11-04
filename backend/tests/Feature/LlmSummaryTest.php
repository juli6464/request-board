<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmSummaryTest extends TestCase
{
    use RefreshDatabase;

    private const FALLBACK = [
        'summary' => null,
        'fallback' => true,
        'message' => 'El servicio de analítica IA no está disponible en este momento.',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.groq.key' => 'test-key', 'services.groq.enabled' => true, 'cache.default' => 'array']);
    }

    public function test_returns_summary_and_sends_only_minimal_data(): void
    {
        Application::factory()->create(['title' => 'Secreto', 'description' => 'NO_DEBE_SALIR']);
        Application::factory()->status(ApplicationStatus::Paused)->create();

        Http::fake([
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Resumen OK']]]]),
        ]);

        $this->getJson('/api/v1/applications/summary/llm')
            ->assertOk()
            ->assertJson(['summary' => 'Resumen OK', 'fallback' => false]);

        Http::assertSent(function (Request $request) {
            $body = $request->body();
            $payload = json_decode($request['messages'][1]['content'], true);

            return $request->hasHeader('Authorization', 'Bearer test-key')
                && ! str_contains($body, 'NO_DEBE_SALIR')
                && $payload['count_by_status']['paused'] === 1
                && $payload['count_by_status']['created'] === 1
                && array_keys($payload['recent_applications'][0]) === ['title', 'status', 'created_at'];
        });
    }

    public function test_second_call_uses_cache_while_data_is_unchanged(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'x']]]])]);

        $this->getJson('/api/v1/applications/summary/llm')->assertJsonPath('cached', false);
        $this->getJson('/api/v1/applications/summary/llm')->assertJsonPath('cached', true);
        $this->getJson('/api/v1/applications/summary/llm?refresh=1')->assertJsonPath('cached', false);

        Http::assertSentCount(2);
    }

    public function test_fallback_on_timeout(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->getJson('/api/v1/applications/summary/llm')->assertOk()->assertExactJson(self::FALLBACK);
    }

    public function test_fallback_on_provider_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $this->getJson('/api/v1/applications/summary/llm')->assertOk()->assertExactJson(self::FALLBACK);
    }

    public function test_fallback_without_api_key_and_no_http_call(): void
    {
        config(['services.groq.key' => null]);
        Http::fake();

        $this->getJson('/api/v1/applications/summary/llm')->assertOk()->assertExactJson(self::FALLBACK);
        Http::assertNothingSent();
    }

    public function test_fallback_when_disabled(): void
    {
        config(['services.groq.enabled' => false]);
        Http::fake();

        $this->getJson('/api/v1/applications/summary/llm')->assertOk()->assertExactJson(self::FALLBACK);
        Http::assertNothingSent();
    }
}
