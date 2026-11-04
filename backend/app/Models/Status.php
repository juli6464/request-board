<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de estados. La clave primaria es el código string (natural key),
 * así la API y la tabla `applications` hablan el mismo idioma sin IDs internos.
 */
class Status extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'sort_order', 'is_final'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_final' => 'boolean',
        ];
    }
}
