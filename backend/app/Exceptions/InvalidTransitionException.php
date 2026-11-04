<?php

namespace App\Exceptions;

use App\Enums\ApplicationStatus;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Excepción de dominio: la transición pedida no existe en la máquina de estados.
 * Laravel la convierte automáticamente en una respuesta HTTP 400 vía render().
 */
class InvalidTransitionException extends DomainException
{
    public function __construct(
        public readonly ApplicationStatus $from,
        public readonly ApplicationStatus $to,
    ) {
        parent::__construct(self::buildMessage($from, $to));
    }

    private static function buildMessage(ApplicationStatus $from, ApplicationStatus $to): string
    {
        if ($from === $to) {
            return "La solicitud ya se encuentra en el estado '{$from->value}'.";
        }

        $allowed = array_map(fn (ApplicationStatus $s) => "'{$s->value}'", $from->allowedTransitions());

        $hint = $allowed === []
            ? "'{$from->value}' es un estado final y no admite más transiciones."
            : 'Desde ese estado solo se permite pasar a: '.implode(', ', $allowed).'.';

        return "Transición inválida: no se puede pasar de '{$from->value}' a '{$to->value}'. {$hint}";
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_transition',
            'current_status' => $this->from->value,
            'target_status' => $this->to->value,
            'allowed_transitions' => array_map(fn (ApplicationStatus $s) => $s->value, $this->from->allowedTransitions()),
        ], 400);
    }
}
