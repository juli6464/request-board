<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use Illuminate\Support\Facades\DB;

/**
 * Único punto del sistema autorizado a cambiar el estado de una solicitud.
 */
class ApplicationTransitionService
{
    /**
     * @throws InvalidTransitionException si la transición no existe en el flujo.
     */
    public function transition(Application $application, ApplicationStatus $target): Application
    {
        return DB::transaction(function () use ($application, $target) {
            // Bloqueo de fila: dos peticiones concurrentes no pueden validar
            // contra el mismo estado "viejo" y aplicar ambas.
            /** @var Application $locked */
            $locked = Application::query()->lockForUpdate()->findOrFail($application->getKey());

            $current = $locked->status;

            if (! $current->canTransitionTo($target)) {
                // Se lanza ANTES de escribir nada; además la transacción hace rollback.
                throw new InvalidTransitionException($current, $target);
            }

            $locked->status = $target;
            $locked->save();

            ApplicationStatusLog::create([
                'application_id' => $locked->id,
                'from_status' => $current,
                'to_status' => $target,
            ]);

            return $locked;
        });
    }
}
