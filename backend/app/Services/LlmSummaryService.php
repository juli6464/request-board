<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera un resumen ejecutivo del tablero con un LLM (Groq, API compatible con OpenAI).
 *
 * Minimización de datos: solo se envía conteo por estado + los N títulos más
 * recientes con su estado y fecha de creación. Nunca descripciones (texto libre,
 * potencialmente largo y con datos sensibles) ni IDs.
 */
class LlmSummaryService
{
    public const FALLBACK_MESSAGE = 'El servicio de analítica IA no está disponible en este momento.';

    private const RECENT_LIMIT = 10;

    /**
     * @return array{summary: string|null, fallback: bool, message: string|null, generated_at?: string, cached?: bool}
     */
    public function summarize(bool $refresh = false): array
    {
        $apiKey = config('services.groq.key');

        if (! config('services.groq.enabled') || blank($apiKey)) {
            return $this->fallback('LLM deshabilitado o sin API key');
        }

        $payload = $this->buildPayload();
        // La caché se invalida sola cuando cambian los datos (huella del payload).
        $cacheKey = 'llm_summary:'.sha1(json_encode($payload));

        if (! $refresh && ($cached = Cache::get($cacheKey))) {
            return [...$cached, 'cached' => true];
        }

        try {
            $response = Http::timeout(4)
                ->connectTimeout(2)
                ->withToken($apiKey)
                ->acceptJson()
                ->post(config('services.groq.url'), [
                    'model' => config('services.groq.model'),
                    'temperature' => 0.3,
                    'max_tokens' => 300,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                    ],
                ])
                ->throw();

            $summary = trim((string) $response->json('choices.0.message.content'));

            if ($summary === '') {
                return $this->fallback('Respuesta vacía del LLM');
            }
        } catch (ConnectionException $e) {
            return $this->fallback('Timeout/conexión: '.$e->getMessage());
        } catch (RequestException $e) {
            return $this->fallback('HTTP '.$e->response->status());
        } catch (Throwable $e) {
            return $this->fallback($e::class.': '.$e->getMessage());
        }

        $result = [
            'summary' => $summary,
            'fallback' => false,
            'message' => null,
            'generated_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, now()->addMinutes(10));

        return [...$result, 'cached' => false];
    }

    /**
     * Datos mínimos y agregados que recibe el modelo.
     */
    public function buildPayload(): array
    {
        $counts = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = [];
        foreach (ApplicationStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $recent = Application::query()
            ->select(['title', 'status', 'created_at'])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Application $a) => [
                'title' => mb_strimwidth($a->title, 0, 120, '…'),
                'status' => $a->status->value,
                'created_at' => $a->created_at?->toDateString(),
            ])
            ->all();

        return [
            'today' => now()->toDateString(),
            'total' => array_sum($byStatus),
            'count_by_status' => $byStatus,
            'recent_applications' => $recent,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Eres un analista de operaciones. Recibirás en JSON el estado de un tablero de solicitudes
        (conteo por estado y las solicitudes más recientes con su estado y fecha de creación).
        Flujo: created -> in_review -> in_progress -> done; paused es un estado de bloqueo.
        Escribe en español un resumen ejecutivo de máximo 4 frases: carga total, dónde se acumula el
        trabajo, cuántas están pausadas/bloqueadas y un riesgo o recomendación concreta.
        No inventes datos que no estén en el JSON. Texto plano, sin markdown ni listas.
        PROMPT;
    }

    private function fallback(string $reason): array
    {
        Log::warning('LlmSummaryService fallback', ['reason' => $reason]);

        return [
            'summary' => null,
            'fallback' => true,
            'message' => self::FALLBACK_MESSAGE,
        ];
    }
}
