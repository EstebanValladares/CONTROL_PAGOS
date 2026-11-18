<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Sincronización inteligente de base de datos local (Casa / Trabajo / Git)
        $dbPath = database_path('ecosistema_oc.db');
        $repoPath = base_path('datos/ecosistema_oc.db');

        if (file_exists($repoPath) && filesize($repoPath) > 1000000) {
            if (!file_exists($dbPath) || filesize($dbPath) < 1000000) {
                @copy($repoPath, $dbPath);
            } elseif (filemtime($repoPath) > (filemtime($dbPath) + 2)) {
                // El repositorio trajo cambios más recientes (ej. tras git pull)
                @copy($repoPath, $dbPath);
            }
        }

        // 2. Al finalizar peticiones HTTP que modifiquen datos (POST, PUT, DELETE),
        // reflejar el cambio en datos/ecosistema_oc.db para que Git lo detecte y viaje a GitHub
        $this->app->terminating(function () use ($dbPath, $repoPath) {
            try {
                if (request()->isMethod('POST') || request()->isMethod('PUT') || request()->isMethod('DELETE')) {
                    if (file_exists($dbPath) && filesize($dbPath) > 1000000) {
                        @copy($dbPath, $repoPath);
                    }
                }
            } catch (\Throwable $e) {
                // Silenciar en consola/CLI
            }
        });
    }
}
