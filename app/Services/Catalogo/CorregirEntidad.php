<?php

namespace App\Services\Catalogo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

class CorregirEntidad
{
    public function __construct(private ImportarCapa $importador) {}

    public function documento(object $registro): ?stdClass
    {
        if (! $registro->vigente || $registro->paquete_id !== null) {
            return null;
        }
        $contenido = Storage::disk('local')->get($registro->ruta);
        abort_unless(is_string($contenido) && hash_equals($registro->sha256, hash('sha256', $contenido)), 503,
            'El archivo de la versión vigente no está disponible o no coincide con su huella.');

        return json_decode($contenido, false, 64, JSON_THROW_ON_ERROR);
    }

    public function guardar(object $registro, int $elemento, int $usuario, ?string $reemplazo): int
    {
        return DB::transaction(function () use ($registro, $elemento, $usuario, $reemplazo) {
            $vigente = DB::table('catalogo.capas')->where('id', $registro->capa_id)->lockForUpdate()->value('version_vigente_id');
            abort_if($vigente !== $registro->version_id, 409, 'La capa cambió. Abre el detalle de su versión vigente.');
            $documento = $this->documento($registro);
            $indice = $elemento - 1;
            abort_unless(isset($documento->features[$indice]), 404);

            if ($reemplazo === null) {
                if (count($documento->features) === 1) {
                    throw ValidationException::withMessages(['entidad' => 'Es la última entidad. Retira la capa desde el catálogo.']);
                }
                array_splice($documento->features, $indice, 1);
            } else {
                $nueva = $this->convertir($reemplazo);
                if (json_encode($documento->features[$indice]) === json_encode($nueva)) {
                    throw ValidationException::withMessages(['entidad' => 'Edita la entidad antes de guardar.']);
                }
                $documento->features[$indice] = $nueva;
            }

            $contenido = json_encode($documento, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            if (strlen($contenido) > ImportarCapa::MAX_BYTES) {
                throw ValidationException::withMessages(['entidad' => 'La capa resultante excede 5 MiB. Reduce el tamaño del GeoJSON.']);
            }
            $resultado = $this->importador->ejecutar($registro->capa_id, $usuario, "correccion-{$registro->id}-$elemento.geojson",
                $contenido, ['fuente' => $registro->fuente, 'licencia' => $registro->licencia,
                    'origen' => $registro->origen, 'fecha_dato' => $registro->fecha_dato]);
            if ($resultado['duplicada']) {
                throw ValidationException::withMessages(['entidad' => 'La corrección coincide con un archivo ya procesado en esa capa.']);
            }
            DB::table('app.auditoria')->insert([
                'usuario_id' => $usuario,
                'accion' => $reemplazo === null ? 'eliminar_entidad' : 'editar_entidad',
                'objeto' => 'importacion/'.$resultado['id'],
                'detalle' => json_encode(['importacion_origen' => $registro->id, 'elemento' => $elemento], JSON_THROW_ON_ERROR),
            ]);

            return $resultado['id'];
        });
    }

    private function convertir(string $reemplazo): stdClass
    {
        try {
            $entidad = json_decode($reemplazo, false, 60, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['entidad' => 'La entidad no contiene JSON válido.']);
        }
        if (! $entidad instanceof stdClass || ($entidad->type ?? null) !== 'Feature') {
            throw ValidationException::withMessages(['entidad' => 'Ingresa una sola entidad GeoJSON de tipo Feature.']);
        }

        return $entidad;
    }
}
