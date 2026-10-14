<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'usuarios';
    protected $fillable = ['nombre', 'email', 'password', 'rol_id', 'activo'];
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'activo' => 'boolean'];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function tieneRol(string ...$roles): bool
    {
        return in_array($this->rol->nombre, $roles, true);
    }

    // Nuestra tabla no tiene remember_token; esto evita que Laravel lo busque.
    public function getRememberTokenName()
    {
        return '';
    }
}