<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportarLluviaRango extends Command
{
    protected $signature = 'territorio:importar-lluvia-rango {desde : Primer mes AAAA-MM} {hasta : Último mes AAAA-MM} {--limite= : GeoJSON de departamentos} {--python=python3 : Ejecutable de Python}';

    protected $description = 'Prepara e importa meses de lluvia de Piura sin repetir series existentes';

    public function handle(): int
    {
        $desde = $this->mes($this->argument('desde'));
        $hasta = $this->mes($this->argument('hasta'));
        $limite = $this->option('limite') ?: storage_path('app/private/lluvia-piura-2017/originales/departamentos.geojson');
        if (DB::getDriverName() !== 'pgsql' || ! $desde || ! $hasta || $desde->year < 1981 || $desde->greaterThan($hasta) || $hasta->greaterThanOrEqualTo(now()->startOfMonth()) || ! is_readable($limite)) {
            $this->error('Indica meses completos válidos desde 1981, un límite legible y una conexión PostGIS.');

            return self::FAILURE;
        }
        $disco = Storage::disk('local');
        $base = 'lluvia-piura-lotes';
        $estado = ['desde' => $desde->format('Y-m'), 'hasta' => $hasta->format('Y-m'), 'meses' => []];
        for ($mes = $desde; $mes->lessThanOrEqualTo($hasta); $mes = $mes->addMonth()) {
            $estado['meses'][$mes->format('Y-m')] = ['estado' => 'pendiente'];
        }
        $this->guardarEstado($disco, $base, $estado);
        foreach (array_keys($estado['meses']) as $periodo) {
            $codigo = 'piura-chirps-v3-rnl-'.$periodo;
            if (DB::table('geo.lluvia_series')->where('codigo', $codigo)->exists()) {
                $estado['meses'][$periodo] = ['estado' => 'ya_importado'];
                $this->line($periodo.': ya importado.');
                $this->guardarEstado($disco, $base, $estado);

                continue;
            }
            $carpeta = $disco->path($base.'/'.$periodo);
            $this->info('Preparando '.$periodo.'…');
            try {
                foreach ([
                    [$this->option('python'), '-u', 'database/data/recortar_lluvia_piura.py', $periodo, $limite, $carpeta, '--mes'],
                    [$this->option('python'), '-u', 'database/data/preparar_lluvia_piura.py', $carpeta, '--mes', $periodo],
                ] as $comando) {
                    $resultado = Process::path(base_path())->timeout(1800)->run($comando, fn (string $tipo, string $salida) => $this->output->write($salida));
                    if (! $resultado->successful()) {
                        throw new \RuntimeException('No se pudo preparar el mes; revisa la salida del proceso y la disponibilidad de la fuente.');
                    }
                }
                if ($this->call('territorio:importar-lluvia-piura', ['directorio' => $carpeta]) !== self::SUCCESS) {
                    throw new \RuntimeException('La importación del mes no se completó.');
                }
                $estado['meses'][$periodo] = ['estado' => 'importado'];
            } catch (Throwable $error) {
                $estado['meses'][$periodo] = ['estado' => 'error', 'detalle' => $error->getMessage()];
                $this->guardarEstado($disco, $base, $estado);
                $this->error($periodo.': '.$error->getMessage());
                $this->warn('Los meses restantes siguen pendientes. Puedes ejecutar el mismo comando para continuar.');

                return self::FAILURE;
            }
            $this->guardarEstado($disco, $base, $estado);
        }
        $this->info('Carga terminada. Estado: '.$disco->path($base.'/estado.json'));

        return self::SUCCESS;
    }

    private function mes(string $valor): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $valor.'-01') ?: null;
    }

    private function guardarEstado(Filesystem $disco, string $base, array $estado): void
    {
        if (! $disco->put($base.'/estado.json', json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            throw new \RuntimeException('No se pudo guardar el estado de la carga.');
        }
    }
}
