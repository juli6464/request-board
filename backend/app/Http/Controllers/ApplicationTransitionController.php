<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\TransitionApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Services\ApplicationTransitionService;
use Illuminate\Http\JsonResponse;

class ApplicationTransitionController extends Controller
{
    public function __construct(private readonly ApplicationTransitionService $transitions) {}

    /**
     * Consultar transiciones: estado actual, a qué estados puede pasar y su historial.
     */
    public function index(Application $application): JsonResponse
    {
        return response()->json([
            'data' => [
                'current_status' => $application->status->value,
                'allowed_transitions' => array_map(
                    fn (ApplicationStatus $s) => ['code' => $s->value, 'name' => $s->label()],
                    $application->status->allowedTransitions(),
                ),
                'history' => $application->statusLogs()->get()->map(fn (ApplicationStatusLog $log) => [
                    'from_status' => $log->from_status->value,
                    'to_status' => $log->to_status->value,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Ejecutar una transición. Si es inválida, el servicio lanza
     * InvalidTransitionException (HTTP 400) y la BD no se toca.
     */
    public function store(TransitionApplicationRequest $request, Application $application): ApplicationResource
    {
        $updated = $this->transitions->transition($application, $request->targetStatus());

        return new ApplicationResource($updated);
    }
}
