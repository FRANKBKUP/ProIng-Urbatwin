<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GestionCapasController extends Controller
{
    public function cambiar(Request $request, string $tipo, string $registro): RedirectResponse
    {
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['editar', 'retirar', 'restaurar'])],
            'etiqueta' => ['nullable', 'string', 'max:120'],
        ]);
        $accion = $datos['accion'];
        abort_if($tipo === 'capa' && filter_var($registro, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false, 404);
        $consulta = match ($tipo) {
            'capa' => DB::table('catalogo.capas')->where('id', $registro),
            'tema' => DB::table('geo.temas')->where('codigo', $registro),
        };

        DB::transaction(function () use ($request, $consulta, $tipo, $registro, $accion, $datos) {
            $actual = (clone $consulta)->lockForUpdate()->first();
            abort_if($actual === null, 404);
            $cambios = match ($accion) {
                'editar' => ['etiqueta' => $datos['etiqueta'] ?? null],
                'retirar' => ['retirada_en' => $actual->retirada_en ?? now()],
                'restaurar' => ['retirada_en' => null],
            };
            $consulta->update($cambios);
            DB::table('app.auditoria')->insert([
                'usuario_id' => $request->user()->id,
                'accion' => $accion.'_'.$tipo,
                'objeto' => $tipo.'/'.$registro,
                'detalle' => json_encode($cambios, JSON_THROW_ON_ERROR),
            ]);
        });

        return back()->with('ok', match ($accion) {
            'editar' => 'Nombre visible actualizado.',
            'retirar' => 'Capa retirada del visor y de nuevas consultas. Se conserva su historial.',
            'restaurar' => 'Capa restaurada. Su última versión publicada vuelve a estar disponible.',
        });
    }
}
