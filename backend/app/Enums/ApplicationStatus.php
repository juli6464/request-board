<?php

namespace App\Enums;

/**
 * Estados de una solicitud.
 *
 * El valor (string) de cada caso es el identificador público del estado:
 * es lo que viaja en la API y lo que se guarda en `applications.status`
 * (FK hacia el catálogo `statuses.code`). Nunca se exponen IDs numéricos.
 */
enum ApplicationStatus: string
{
    case Created = 'created';
    case InReview = 'in_review';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Done = 'done';

    /**
     * Grafo de transiciones permitidas (máquina de estados).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::InReview],
            self::InReview => [self::InProgress, self::Paused],
            self::InProgress => [self::Done, self::Paused],
            self::Paused => [self::InReview, self::InProgress],
            self::Done => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creada',
            self::InReview => 'En revisión',
            self::InProgress => 'En progreso',
            self::Paused => 'Pausada',
            self::Done => 'Terminada',
        };
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
