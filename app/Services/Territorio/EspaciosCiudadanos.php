<?php

namespace App\Services\Territorio;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EspaciosCiudadanos
{
    public function grupos(int $usuario): Builder
    {
        return DB::table('app.grupos as g')
            ->join('app.grupo_miembros as m', 'm.grupo_id', '=', 'g.id')
            ->where('m.usuario_id', $usuario)
            ->select('g.id', 'g.nombre', 'g.propietario_id');
    }

    public function lugares(int $usuario, ?string $grupo = null): Builder
    {
        $lugares = DB::table('app.lugares as l')
            ->join('app.usuarios as u', 'u.id', '=', 'l.usuario_id')
            ->where('u.activo', 1)
            ->leftJoin('app.perfiles as p', 'p.usuario_id', '=', 'l.usuario_id')
            ->select('l.id', 'l.nombre', 'l.grupo_id', 'u.nombre as persona', 'p.avatar')
            ->selectRaw('ST_X(l.geom) AS longitud, ST_Y(l.geom) AS latitud')
            ->orderByDesc('l.creado_en')->orderBy('l.id');

        if ($grupo === null) {
            return $lugares->where('l.usuario_id', $usuario);
        }

        abort_unless($this->grupos($usuario)->where('g.id', $grupo)->exists(), 404);

        return $lugares->where('l.grupo_id', $grupo)
            ->whereExists(DB::table('app.grupo_miembros as lector')->selectRaw('1')
                ->whereColumn('lector.grupo_id', 'l.grupo_id')->where('lector.usuario_id', $usuario));
    }

    public function miembros(int $usuario): Collection
    {
        return DB::table('app.grupo_miembros as m')
            ->join('app.grupos as g', 'g.id', '=', 'm.grupo_id')
            ->join('app.usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->leftJoin('app.perfiles as p', 'p.usuario_id', '=', 'm.usuario_id')
            ->leftJoin('geo.departamentos as d', fn ($join) => $join->on('d.codigo', '=', 'p.departamento')->where('p.visible_en_mapa', true))
            ->where('u.activo', 1)
            ->whereExists(DB::table('app.grupo_miembros as propio')->selectRaw('1')
                ->whereColumn('propio.grupo_id', 'm.grupo_id')->where('propio.usuario_id', $usuario))
            ->select('m.grupo_id', 'u.nombre', 'p.avatar', 'd.nombre as departamento')
            ->selectRaw('m.usuario_id = g.propietario_id AS propietario')
            ->selectRaw('(SELECT count(*) FROM app.lugares l WHERE l.grupo_id = m.grupo_id AND l.usuario_id = m.usuario_id) puntos')
            ->orderByDesc('propietario')->orderBy('u.nombre')->get()->groupBy('grupo_id');
    }

    public function crearGrupo(int $propietario, string $nombre): void
    {
        DB::transaction(function () use ($propietario, $nombre) {
            $grupo = (string) Str::uuid();
            DB::table('app.grupos')->insert(['id' => $grupo, 'nombre' => $nombre, 'propietario_id' => $propietario]);
            DB::table('app.grupo_miembros')->insert(['grupo_id' => $grupo, 'usuario_id' => $propietario]);
        });
    }

    public function invitar(int $propietario, string $grupo): string
    {
        abort_unless($this->grupos($propietario)->where('g.id', $grupo)->where('g.propietario_id', $propietario)->exists(), 404);

        $codigo = Str::random(40);
        DB::table('app.grupo_invitaciones')->insert([
            'hash' => hash('sha256', $codigo),
            'grupo_id' => $grupo,
            'vence_en' => now()->addDay(),
        ]);

        return $codigo;
    }

    public function renombrar(int $propietario, string $grupo, string $nombre): void
    {
        $filas = DB::table('app.grupos')->where('id', $grupo)->where('propietario_id', $propietario)
            ->update(['nombre' => $nombre]);
        abort_if($filas === 0, 404);
    }

    public function aceptar(int $usuario, string $codigo): void
    {
        DB::transaction(function () use ($usuario, $codigo) {
            $hash = hash('sha256', $codigo);
            $invitacion = DB::table('app.grupo_invitaciones')->where('hash', $hash)
                ->whereNull('usado_en')->where('vence_en', '>', now())->lockForUpdate()->first();
            if (! $invitacion) {
                throw ValidationException::withMessages(['codigo' => 'El código ya se usó, expiró o no existe.']);
            }
            if ($this->grupos($usuario)->where('g.id', $invitacion->grupo_id)->exists()) {
                throw ValidationException::withMessages(['codigo' => 'Tu cuenta ya pertenece a este grupo.']);
            }

            DB::table('app.grupo_miembros')->insertOrIgnore(['grupo_id' => $invitacion->grupo_id, 'usuario_id' => $usuario]);
            DB::table('app.grupo_invitaciones')->where('hash', $hash)->update(['usado_en' => now()]);
        });
    }

    public function salir(int $usuario, string $grupo): bool
    {
        return DB::transaction(function () use ($usuario, $grupo) {
            $membresia = $this->grupos($usuario)->where('g.id', $grupo)->first();
            abort_if(! $membresia, 404);
            if ($membresia->propietario_id === $usuario) {
                DB::table('app.grupos')->where('id', $grupo)->where('propietario_id', $usuario)->delete();

                return true;
            }
            DB::table('app.grupo_miembros')->where('grupo_id', $grupo)->where('usuario_id', $usuario)->delete();

            return false;
        });
    }
}
