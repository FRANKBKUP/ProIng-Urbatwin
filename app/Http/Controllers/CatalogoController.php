<?php

namespace App\Http\Controllers;

use App\Services\Catalogo\CorregirEntidad;
use App\Services\Catalogo\ImportarCapa;
use App\Services\GeoJsonService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogoController extends Controller
{
    public function index(): View
    {
        return view('geo.catalogo', [
            'maximoImportacion' => min(ImportarCapa::MAX_BYTES, UploadedFile::getMaxFilesize()),
            'coberturas' => DB::table('geo.coberturas')->orderBy('nombre')->get(['id', 'nombre']),
            'capas' => DB::table('catalogo.capas as c')
                ->join('geo.coberturas as g', 'g.id', '=', 'c.cobertura_id')
                ->leftJoin('catalogo.capas_versiones as v', 'v.id', '=', 'c.version_vigente_id')
                ->select('c.id', 'c.nombre', 'c.etiqueta', 'c.retirada_en', 'c.tipo', 'g.nombre as cobertura', 'v.numero')
                ->orderBy('c.id')->get(),
            'temas' => DB::table('geo.temas')->orderBy('codigo')
                ->get(['codigo', 'titulo', 'etiqueta', 'periodo', 'retirada_en']),
            'importaciones' => DB::table('catalogo.importaciones as i')
                ->join('catalogo.capas as c', 'c.id', '=', 'i.capa_id')
                ->join('catalogo.archivos as a', 'a.id', '=', 'i.archivo_id')
                ->orderByDesc('i.id')->simplePaginate(15, ['i.id', 'i.estado', 'c.nombre as capa', 'a.nombre as archivo']),
        ]);
    }

    public function cobertura(Request $request, GeoJsonService $geoJson): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('pgsql.geo.coberturas', 'nombre')],
            'geojson' => ['required', 'string', 'max:100000'],
        ]);
        $objeto = json_decode($datos['geojson'], true, 64);
        if (! is_array($objeto) || ! in_array($objeto['type'] ?? null, ['Polygon', 'MultiPolygon'], true)
            || array_key_exists('crs', $objeto)) {
            throw ValidationException::withMessages(['geojson' => 'Ingresa una geometría Polygon o MultiPolygon en CRS84.']);
        }
        try {
            $geom = $geoJson->geometria($objeto, estricto: true);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['geojson' => $e->getMessage()]);
        }
        $valida = DB::selectOne(<<<'SQL'
            SELECT CASE
                WHEN NOT ST_IsValid(geom) THEN ST_IsValidReason(geom)
                WHEN NOT ST_Covers(ST_MakeEnvelope(-84, -80, -78, 0, 4326), geom)
                    THEN 'La cobertura debe estar dentro de UTM 17 sur.'
            END AS error
            FROM (SELECT ST_GeomFromGeoJSON(?::text) AS geom) g
            SQL, [$geom]);
        if ($valida->error !== null) {
            throw ValidationException::withMessages(['geojson' => $valida->error]);
        }

        DB::transaction(function () use ($datos, $request, $geom) {
            $id = DB::selectOne('INSERT INTO geo.coberturas (nombre, creado_por, geom)
                VALUES (?, ?, ST_Multi(ST_GeomFromGeoJSON(?::text))) RETURNING id',
                [$datos['nombre'], $request->user()->id, $geom])->id;
            $this->registrar($request, 'crear_cobertura', 'cobertura/'.$id);
        });

        return back()->with('ok', 'Cobertura registrada.');
    }

    public function capa(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'cobertura_id' => ['required', 'integer', Rule::exists('pgsql.geo.coberturas', 'id')],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(['peligro', 'vias', 'equipamiento', 'curvas'])],
        ]);
        DB::transaction(function () use ($datos, $request) {
            if (DB::table('catalogo.capas')->insertOrIgnore($datos) === 0) {
                throw ValidationException::withMessages(['nombre' => 'Ya existe una capa con ese nombre en la cobertura.']);
            }
            $id = DB::table('catalogo.capas')->where($datos)->value('id');
            $this->registrar($request, 'crear_capa', 'capa/'.$id);
        });

        return back()->with('ok', 'Capa registrada. Publica su primera versión con un GeoJSON.');
    }

    public function importar(Request $request, ImportarCapa $importador): JsonResponse|RedirectResponse
    {
        $datos = $request->validate([
            'capa_id' => ['required', 'integer', Rule::exists('pgsql.catalogo.capas', 'id')],
            'archivo' => ['required', 'file', 'max:5120'],
            'fuente' => ['required', 'string', 'max:160'],
            'licencia' => ['required', 'string', 'max:200'],
            'origen' => ['required', Rule::in(['simulado', 'observado'])],
            'fecha_dato' => ['required', 'date_format:Y-m-d'],
        ], [
            'archivo.uploaded' => 'El servidor no pudo recibir el archivo. Comprueba el límite indicado en el formulario.',
            'archivo.max' => 'El archivo supera el límite de 5 MiB del catálogo.',
        ]);
        $archivo = $request->file('archivo');
        $resultado = $importador->ejecutar((int) $datos['capa_id'], $request->user()->id,
            $archivo->getClientOriginalName(), $archivo->get(), $datos);

        if ($request->expectsJson()) {
            return response()->json($resultado, $resultado['estado'] === 'rechazada' ? 422 : ($resultado['duplicada'] ? 200 : 201));
        }

        return redirect()->route('geo.catalogo.importacion', $resultado['id']);
    }

    public function importacion(Request $request, int $importacion, CorregirEntidad $correcciones): View
    {
        $registro = $this->obtenerImportacion($importacion);
        $paquete = $registro->paquete_id
            ? DB::table('catalogo.paquetes_fuente')->where('id', $registro->paquete_id)->first()
            : null;
        $documento = $correcciones->documento($registro);
        $entidades = null;
        $elemento = $request->integer('elemento');
        if ($documento !== null) {
            $pagina = min(max(1, $request->integer('entidades', 1)), (int) ceil(count($documento->features) / 12));
            $entidades = new Paginator(array_slice($documento->features, ($pagina - 1) * 12, 13, preserve_keys: true), 12, $pagina, [
                'path' => $request->url(), 'pageName' => 'entidades', 'fragment' => 'entidades',
            ]);
        }
        $origen = DB::table('app.auditoria')->where('objeto', 'importacion/'.$importacion)
            ->whereIn('accion', ['editar_entidad', 'eliminar_entidad'])->value('detalle');

        return view('geo.importacion', [
            'importacion' => $registro,
            'paquete' => $paquete,
            'preparacion' => $paquete ? json_decode($paquete->preparacion, true, 32, JSON_THROW_ON_ERROR) : [],
            'erroresImportacion' => DB::table('catalogo.importacion_errores')->where('importacion_id', $importacion)
                ->orderBy('elemento')->simplePaginate(30),
            'entidades' => $entidades,
            'elemento' => $elemento,
            'entidad' => $documento->features[$elemento - 1] ?? null,
            'correccionOrigen' => $origen ? json_decode($origen, true, flags: JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function corregir(Request $request, int $importacion, int $elemento, CorregirEntidad $correcciones): RedirectResponse
    {
        $reemplazo = $request->isMethod('PATCH')
            ? $request->validate(['entidad' => ['required', 'string', 'max:262144']])['entidad']
            : null;
        $nueva = $correcciones->guardar($this->obtenerImportacion($importacion), $elemento, $request->user()->id, $reemplazo);

        return redirect()->route('geo.catalogo.importacion', $nueva);
    }

    public function original(int $importacion): StreamedResponse
    {
        $registro = $this->obtenerImportacion($importacion);
        abort_unless(Storage::disk('local')->exists($registro->ruta), 404);

        return Storage::disk('local')->download($registro->ruta, 'original-'.$importacion.'.geojson', [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function paquete(int $importacion): StreamedResponse
    {
        $registro = $this->obtenerImportacion($importacion);
        $ruta = DB::table('catalogo.paquetes_fuente')->where('id', $registro->paquete_id)->value('ruta');
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->download($ruta, 'paquete-fuente-'.$importacion.'.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function obtenerImportacion(int $id): object
    {
        $registro = DB::table('catalogo.importaciones as i')
            ->join('catalogo.archivos as a', 'a.id', '=', 'i.archivo_id')
            ->join('catalogo.capas as c', 'c.id', '=', 'i.capa_id')
            ->join('catalogo.fuentes as f', 'f.id', '=', 'i.fuente_id')
            ->leftJoin('catalogo.capas_versiones as v', 'v.importacion_id', '=', 'i.id')
            ->where('i.id', $id)->first(['i.id', 'i.capa_id', 'i.estado', 'i.fecha_dato', 'a.nombre as archivo', 'a.ruta',
                'a.sha256', 'a.paquete_id', 'c.nombre as capa', 'f.nombre as fuente', 'f.licencia', 'f.origen',
                'v.numero', 'v.id as version_id', DB::raw('c.version_vigente_id = v.id AS vigente')]);
        abort_if(! $registro, 404);

        return $registro;
    }

    private function registrar(Request $request, string $accion, string $objeto): void
    {
        DB::table('app.auditoria')->insert(['usuario_id' => $request->user()->id, 'accion' => $accion, 'objeto' => $objeto]);
    }
}
