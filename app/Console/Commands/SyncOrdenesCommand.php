<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SyncOrdenesCommand extends Command
{
    protected $signature = 'oc:sync {--dias=2 : Días hacia atrás para sincronizar}';
    protected $description = 'Sincroniza en tiempo real las órdenes de compra desde Enkontrol Sybase a SQLite';

    public function handle()
    {
        $dias = (int)$this->option('dias') ?: 2;
        $this->info("Iniciando sincronización incremental (últimos {$dias} días)...");

        $script = base_path('sincronizar_ordenes_ek9.py');
        if (!file_exists($script)) {
            $this->error("No se encontró el script {$script}");
            return 1;
        }

        // Buscar ejecutable de Python disponible con pyodbc
        $pythonExec = 'python';
        $posibles = [
            'sys.executable',
            'python3',
            'python'
        ];
        foreach ($posibles as $p) {
            if (file_exists($p)) {
                $pythonExec = $p;
                break;
            }
        }

        $process = new Process([$pythonExec, $script]);
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info("Sincronización completada exitosamente.");
            return 0;
        } else {
            $this->warn("Aviso en sincronización: " . $process->getErrorOutput());
            return 1;
        }
    }
}
