<?php

namespace App\Http\Controllers;

use App\Services\Territorio\EspaciosCiudadanos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GruposController extends Controller
{
    public function __construct(private EspaciosCiudadanos $espacios) {}

    public function crear(Request $request): RedirectResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:80']]);
        $this->espacios->crearGrupo($request->user()->id, $datos['nombre']);

        return back()->with('ok', 'Grupo creado.');
    }

    public function invitar(Request $request, string $grupo): RedirectResponse
    {
        return back()->with('invitacion', $this->espacios->invitar($request->user()->id, $grupo));
    }

    public function editar(Request $request, string $grupo): RedirectResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:80']]);
        $this->espacios->renombrar($request->user()->id, $grupo, $datos['nombre']);

        return back()->with('ok', 'Nombre del grupo actualizado.');
    }

    public function aceptar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/']]);
        $this->espacios->aceptar($request->user()->id, $datos['codigo']);

        return back()->with('ok', 'Te uniste al grupo.');
    }

    public function salir(Request $request, string $grupo): RedirectResponse
    {
        $eliminado = $this->espacios->salir($request->user()->id, $grupo);

        return back()->with('ok', $eliminado ? 'Grupo eliminado. Los lugares compartidos volvieron a ser privados.'
            : 'La participación terminó. Tus lugares siguen en tu cuenta.');
    }
}
