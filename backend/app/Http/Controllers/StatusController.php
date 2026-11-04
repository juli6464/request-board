<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Status;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    /**
     * Catálogo de estados con su grafo de transiciones (útil para que el
     * frontend construya columnas y filtros sin conocer IDs internos).
     */
    public function index(): JsonResponse
    {
        $statuses = Status::query()->orderBy('sort_order')->get()->map(fn (Status $s) => [
            'code' => $s->code,
            'name' => $s->name,
            'sort_order' => $s->sort_order,
            'is_final' => $s->is_final,
            'allowed_transitions' => array_map(
                fn (ApplicationStatus $t) => $t->value,
                ApplicationStatus::from($s->code)->allowedTransitions(),
            ),
        ]);

        return response()->json(['data' => $statuses]);
    }
}
