<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property ApplicationStatus $status
 */
class Application extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description'];

    /**
     * Toda solicitud nueva nace en `created`. El estado NO es asignable en masa:
     * solo cambia a través de ApplicationTransitionService.
     */
    protected $attributes = [
        'status' => 'created',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
        ];
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class)->orderByDesc('id');
    }
}
