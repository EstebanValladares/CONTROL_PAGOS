<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oc_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->string('empresa', 20);
            $table->string('centro_costos', 100);
            $table->unsignedInteger('numero_oc');
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->string('responsable_nombre')->nullable();
            $table->string('estado_seguimiento', 40)->default('ABIERTA');
            $table->dateTime('seguimiento_iniciado_en');
            $table->dateTime('ultimo_movimiento_en')->nullable();
            $table->string('ultimo_movimiento_tipo', 40)->nullable();
            $table->timestamps();

            $table->unique(
                ['empresa', 'centro_costos', 'numero_oc'],
                'oc_seguimientos_identidad_unique'
            );
            $table->index(['empresa', 'responsable_id'], 'oc_seguimientos_responsable_index');
            $table->index(['empresa', 'estado_seguimiento'], 'oc_seguimientos_estado_index');
        });

        Schema::create('oc_movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('empresa', 20);
            $table->string('centro_costos', 100);
            $table->unsignedInteger('numero_oc');
            $table->string('tipo', 40);
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('creado_en');

            $table->index(
                ['empresa', 'centro_costos', 'numero_oc', 'creado_en'],
                'oc_movimientos_orden_fecha_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_movimientos');
        Schema::dropIfExists('oc_seguimientos');
    }
};
