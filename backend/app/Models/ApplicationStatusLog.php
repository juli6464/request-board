<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de transiciones (auditoría). Solo se inserta, nunca se actualiza.
 */
class ApplicationStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['application_id', 'from_status', 'to_status'];

    protected function casts(): array
    {
        return [
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
