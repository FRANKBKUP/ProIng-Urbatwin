<?php

namespace App\Console\Commands;

use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImportarAtlasPeru extends Command
{
    protected $signature = 'territorio:importar-atlas {directorio}';

    protected $description = 'Publica las capas nacionales generadas por preparar_atlas.py';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('El atlas requiere PostGIS.');

            return self::FAILURE;
        }

        $base = rtrim($this->argument('directorio'), '/\\');
        $salida = $base.'/atlas';
        $temas = json_decode(file_get_contents($salida.'/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
        foreach ($this->lotes($salida.'/departamentos.ndjson') as $lote) {
            DB::insert(<<<'SQL'
                INSERT INTO geo.departamentos (codigo, nombre, geom)
                SELECT codigo, nombre, ST_Multi(ST_GeomFromGeoJSON(geometria))
                FROM jsonb_to_recordset(?::jsonb) AS r(codigo text, nombre text, geometria jsonb)
                ON CONFLICT (codigo) DO NOTHING
                SQL, [$lote]);
        }

        foreach ($temas as $tema) {
            $codigo = $tema['codigo'];
            if (! preg_match('/\A[a-z0-9-]{1,48}\z/', $codigo)) {
                throw new RuntimeException('Código de capa inválido.');
            }
            $previo = DB::table('geo.temas')->where('codigo', $codigo)->value('archivo_sha256');
            if ($previo !== null) {
                if ($previo !== $tema['archivo_sha256']) {
                    throw new RuntimeException('El código ya identifica otro archivo. Usa una nueva versión.');
                }
                $this->line($codigo.' ya publicado.');

                continue;
            }

            $origen = $base.'/'.basename($tema['archivo']);
            $ruta = $this->archivar($origen, $tema['archivo_sha256']);
            DB::transaction(function () use ($tema, $codigo, $ruta, $salida) {
                DB::table('geo.temas')->insert([
                    'codigo' => $codigo, 'tipo' => $tema['tipo'], 'titulo' => $tema['titulo'],
                    'periodo' => $tema['periodo'], 'fuente' => $tema['fuente'], 'ficha_url' => $tema['ficha_url'],
                    'archivo_ruta' => $ruta, 'archivo_sha256' => $tema['archivo_sha256'],
                    'leyenda' => json_encode($tema['leyenda'], JSON_THROW_ON_ERROR),
                    'notas' => $tema['notas'],
                ]);
                foreach ($this->lotes("$salida/$codigo.ndjson") as $lote) {
                    DB::insert(<<<'SQL'
                        INSERT INTO geo.zonas_tematicas (tema, clase, distrito, departamento, ubigeo, km2, geom)
                        SELECT ?, clase, distrito, departamento, ubigeo, ST_Area(g.geom::geography) / 1000000, g.geom
                        FROM jsonb_to_recordset(?::jsonb)
                            AS r(clase smallint, distrito text, departamento text, ubigeo text, geometria jsonb)
                        CROSS JOIN LATERAL (SELECT ST_Multi(ST_GeomFromGeoJSON(r.geometria)) AS geom) g
                        SQL, [$codigo, $lote]);
                }
                if (! DB::table('geo.zonas_tematicas')->where('tema', $codigo)->exists()) {
                    throw new RuntimeException('La capa no contiene geometrías.');
                }
            });
            $this->info($codigo.' publicado.');
        }

        return self::SUCCESS;
    }

    private function archivar(string $archivo, string $sha): string
    {
        if (! is_file($archivo) || ! is_readable($archivo) || hash_file('sha256', $archivo) !== $sha) {
            throw new RuntimeException('El archivo de origen no coincide con el manifiesto.');
        }
        $ruta = 'originales/atlas/'.$sha.'.'.pathinfo($archivo, PATHINFO_EXTENSION);
        $disco = Storage::disk('local');
        if ($disco->exists($ruta)) {
            if (hash_file('sha256', $disco->path($ruta)) !== $sha) {
                throw new RuntimeException('El original archivado perdió su integridad.');
            }

            return $ruta;
        }

        $temporal = $ruta.'.'.Str::uuid().'.part';
        $flujo = fopen($archivo, 'rb');
        try {
            if (! $disco->put($temporal, $flujo) || hash_file('sha256', $disco->path($temporal)) !== $sha
                || ! $disco->move($temporal, $ruta)) {
                throw new RuntimeException('No se pudo conservar el archivo de origen.');
            }
        } finally {
            if (is_resource($flujo)) {
                fclose($flujo);
            }
            $disco->delete($temporal);
        }

        return $ruta;
    }

    private function lotes(string $ruta): Generator
    {
        $archivo = new \SplFileObject($ruta);
        $lineas = [];
        $bytes = 0;
        while (! $archivo->eof()) {
            $linea = trim($archivo->fgets());
            if ($linea === '') {
                continue;
            }
            $lineas[] = $linea;
            $bytes += strlen($linea);
            if ($bytes >= 1_000_000) {
                yield '['.implode(',', $lineas).']';
                $lineas = [];
                $bytes = 0;
            }
        }
        if ($lineas !== []) {
            yield '['.implode(',', $lineas).']';
        }
    }
}
