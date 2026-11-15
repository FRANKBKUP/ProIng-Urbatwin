<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Catalogo\ImportarCapa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportarPiuraHistorica extends Command
{
    private const SHA256 = '746091f3d86f10a8e50fbd948a951c1fd102680abd302b20e6c10c6f1cec374a';

    private const FICHA = 'https://mapping.emergency.copernicus.eu/activations/EMSR199/';

    private const DESCARGA = 'https://cems-mapping-website.s3.eu-west-1.amazonaws.com/static/activations/EMSR199/EMSR199_10PIURA_DELINEATION_OVERVIEW-MONIT01_v3_vector.zip';

    protected $signature = 'territorio:importar-piura {administrador} {zip} {directorio}';

    protected $description = 'Publica la delimitación oficial EMSR199 de Piura y conserva la trazabilidad del ZIP original';

    public function handle(ImportarCapa $importador): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('La publicación requiere PostGIS.');

            return self::FAILURE;
        }

        $actor = User::with('rol')->where('email', $this->argument('administrador'))->first();
        if (! $actor?->tieneRol('Administrador')) {
            $this->error('Indica el correo de un administrador activo.');

            return self::FAILURE;
        }

        $directorio = rtrim($this->argument('directorio'), '/\\');
        $original = $this->leer($this->argument('zip'));
        if (hash('sha256', $original) !== self::SHA256) {
            $this->error('El SHA-256 del archivo difiere del paquete Copernicus documentado.');

            return self::FAILURE;
        }
        $preparacion = $this->leer($directorio.'/preparacion.json');
        $metadatos = json_decode($preparacion, true, 32, JSON_THROW_ON_ERROR);
        if (($metadatos['sha256_original'] ?? null) !== self::SHA256) {
            $this->error('Genera primero los GeoJSON con database/data/preparar_piura.py.');

            return self::FAILURE;
        }

        $paquete = $this->archivar($original, $preparacion);
        $cobertura = $this->cobertura($actor->id, $this->leer($directorio.'/aoi.geojson'));
        $conError = false;
        foreach ([
            ['Piura · 26 de marzo de 2017', 'peligro', '2017-03-26', 'inundacion-2017-03-26.geojson'],
            ['Piura · 30 de marzo de 2017', 'peligro', '2017-03-30', 'inundacion-2017-03-30.geojson'],
            ['Red vial de referencia de 2011 · EMSR199', 'vias', '2011-02-22', 'vias.geojson'],
        ] as [$nombre, $tipo, $fecha, $archivo]) {
            $identidad = ['nombre' => $nombre, 'tipo' => $tipo, 'cobertura_id' => $cobertura];
            DB::table('catalogo.capas')->insertOrIgnore($identidad);
            $capa = DB::table('catalogo.capas')->where($identidad)->value('id');
            $resultado = $importador->ejecutar($capa, $actor->id, $archivo, $this->leer($directorio.'/'.$archivo), [
                'fuente' => 'Copernicus Emergency Management Service · EMSR199',
                'licencia' => '© European Union, 2017. Map produced by Copernicus Emergency Management Service.',
                'origen' => 'observado',
                'fecha_dato' => $fecha,
                'paquete_id' => $paquete,
            ]);

            $this->line($nombre.' → '.$resultado['estado'].' (importación '.$resultado['id'].')');
            $conError = $conError || $resultado['estado'] !== 'publicada';
        }

        return $conError ? self::FAILURE : self::SUCCESS;
    }

    private function archivar(string $original, string $preparacion): int
    {
        $ruta = 'originales/fuentes/'.self::SHA256.'.zip';
        $disco = Storage::disk('local');
        if (! $disco->exists($ruta) && ! $disco->put($ruta, $original)) {
            throw new RuntimeException('No se pudo archivar el paquete Copernicus.');
        }
        if (hash('sha256', $disco->get($ruta)) !== self::SHA256) {
            throw new RuntimeException('El archivo privado no coincide con el original.');
        }

        DB::table('catalogo.paquetes_fuente')->insertOrIgnore([
            'producto' => 'EMSR199 · Piura · delineación MONIT01 v3',
            'ficha_url' => self::FICHA,
            'descarga_url' => self::DESCARGA,
            'ruta' => $ruta,
            'sha256' => self::SHA256,
            'bytes' => strlen($original),
            'escala' => 80000,
            'preparacion' => $preparacion,
        ]);

        return DB::table('catalogo.paquetes_fuente')->where('sha256', self::SHA256)->value('id');
    }

    private function cobertura(int $actor, string $geometria): int
    {
        $nombre = 'Piura y Bajo Piura · área analizada por Copernicus';
        DB::insert('INSERT INTO geo.coberturas (nombre, creado_por, geom)
            VALUES (?, ?, ST_Multi(ST_GeomFromGeoJSON(?::text))) ON CONFLICT (nombre) DO NOTHING',
            [$nombre, $actor, $geometria]);
        $id = DB::table('geo.coberturas')->where('nombre', $nombre)
            ->whereRaw('ST_Equals(geom, ST_GeomFromGeoJSON(?::text))', [$geometria])->value('id');
        if ($id === null) {
            throw new RuntimeException('La cobertura registrada con ese nombre usa una geometría diferente.');
        }

        return $id;
    }

    private function leer(string $ruta): string
    {
        if (! is_file($ruta) || ! is_readable($ruta) || filesize($ruta) > 16 * 1024 * 1024) {
            throw new RuntimeException('Archivo requerido ilegible o mayor a 16 MiB: '.basename($ruta));
        }

        return file_get_contents($ruta);
    }
}
