<?php

namespace App\Console\Commands;

use App\Services\InfraestructuraImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportarInfraestructura extends Command
{
    protected $signature = 'infra:importar
        {archivo : Ruta del archivo .geojson}
        {--zona= : ID de la zona}
        {--tipo= : Nombre del tipo (Colegio, Centro de salud, Vía)}';

    protected $description = 'Importa infraestructura desde un GeoJSON (FeatureCollection)';

    public function handle(InfraestructuraImportService $servicio): int
    {
        $archivo = $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: $archivo");
            return self::FAILURE;
        }

        $zonaId = (int) $this->option('zona');
        if (!DB::table('zonas')->where('id', $zonaId)->exists()) {
            $this->error('Indica una zona válida con --zona=ID.');
            return self::FAILURE;
        }

        $tipoId = DB::table('tipos_infraestructura')->where('nombre', $this->option('tipo'))->value('id');
        if (!$tipoId) {
            $this->error('Indica un tipo válido con --tipo="Colegio" (Colegio, Centro de salud o Vía).');
            return self::FAILURE;
        }

        try {
            $r = $servicio->importar(file_get_contents($archivo), $zonaId, (int) $tipoId);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Insertados: {$r['insertados']} | Omitidos: {$r['omitidos']}");
        foreach ($r['motivos'] as $motivo) {
            $this->line(" - $motivo");
        }

        return self::SUCCESS;
    }
}