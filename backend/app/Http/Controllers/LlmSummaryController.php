<?php

namespace App\Http\Controllers;

use App\Services\LlmSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmSummaryController extends Controller
{
    /**
     * Siempre responde 200: si el LLM falla, devuelve la estructura de degradación
     * ({summary: null, fallback: true, message}) para que el tablero siga funcionando.
     */
    public function __invoke(Request $request, LlmSummaryService $service): JsonResponse
    {
        return response()->json($service->summarize($request->boolean('refresh')));
    }
}
