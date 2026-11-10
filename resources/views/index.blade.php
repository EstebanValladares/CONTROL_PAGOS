<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💳</text></svg>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Control y Conciliación de Pagos OC - Grupo Velas</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Vue 3 CDN -->
  <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Estilos del Sistema y Modo Claro -->
  <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v=2.0">
</head>
<body class="theme-light bg-slate-100 text-slate-900 min-h-screen transition-colors duration-200">
  @verbatim
<div id="app" v-cloak class="theme-light px-3 py-3 w-full max-w-[99%] mx-auto relative">
    
    <!-- MENSAJE FLOTANTE ROJO DE ERROR / NO DISPONIBLE -->
    <transition name="slide-fade">
      <div v-if="mostrarToastError" 
           class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] w-[92%] max-w-lg shadow-2xl">
        <div class="bg-rose-950/95 border-2 border-rose-500 text-white rounded-2xl p-4 shadow-2xl shadow-rose-950/90 backdrop-blur-md flex items-start gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center shrink-0 text-rose-400 mt-0.5">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-bold text-rose-200">No se encuentra disponible</h4>
            <p class="text-xs text-rose-300/90 mt-0.5 leading-relaxed">{{ toastMensaje }}</p>
          </div>
          <button @click="mostrarToastError = false" class="text-rose-400 hover:text-white transition p-1 ml-1" title="Cerrar aviso">
            <i class="fa-solid fa-xmark text-base"></i>
          </button>
        </div>
      </div>
    </transition>

    <!-- MENSAJE FLOTANTE VERDE DE ÉXITO -->
    <transition name="slide-fade">
      <div v-if="mostrarToastExito" 
           class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] w-[92%] max-w-lg shadow-2xl">
        <div class="bg-emerald-950/95 border-2 border-emerald-500 text-white rounded-2xl p-4 shadow-2xl shadow-emerald-950/90 backdrop-blur-md flex items-start gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center shrink-0 text-emerald-400 mt-0.5">
            <i class="fa-solid fa-circle-check text-lg"></i>
          </div>
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-bold text-emerald-200">Operación Exitosa</h4>
            <p class="text-xs text-emerald-300/90 mt-0.5 leading-relaxed">{{ toastExitoMensaje }}</p>
          </div>
          <button @click="mostrarToastExito = false" class="text-emerald-400 hover:text-white transition p-1 ml-1" title="Cerrar aviso">
            <i class="fa-solid fa-xmark text-base"></i>
          </button>
        </div>
      </div>
    </transition>

    <!-- PANTALLA DE ACCESO / LOGIN CORPORATIVO -->
    <div v-if="!usuarioActivo" class="min-h-[85vh] flex flex-col items-center justify-center py-8 px-4">
      <div class="w-full max-w-lg bg-slate-800/95 border border-slate-700/80 p-8 rounded-2xl shadow-2xl backdrop-blur-md transition-all duration-300"
           :class="{'shake-error border-rose-500/50 shadow-rose-950/40': shakeCard}">
        
        <!-- Header del Login -->
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-600/20 border border-indigo-500/30 rounded-2xl mb-3 shadow-inner">
            <i class="fa-solid fa-file-invoice-dollar text-2xl text-indigo-400"></i>
          </div>
          <h2 class="text-2xl font-bold text-white tracking-tight">Control y Conciliación de OCs</h2>
          <p class="text-xs text-slate-400 mt-1">Grupo Velas • Módulo de Operación Semanal</p>
        </div>

        <!-- Alerta de Error de Login -->
        <div v-if="errorLogin" class="mb-4 p-3.5 rounded-xl bg-rose-950/60 border border-rose-500/40 text-xs text-rose-200 flex items-center gap-2.5">
          <i class="fa-solid fa-triangle-exclamation text-rose-400 text-sm"></i>
          <span>{{ errorLogin }}</span>
        </div>

        <!-- FORMULARIO INSTITUCIONAL: EMPRESA, USUARIO Y CONTRASEÑA -->
        <form @submit.prevent="iniciarSesionUsuario" class="space-y-4">
          <!-- SELECTOR DE EMPRESA / GRUPO -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
              Empresa / Grupo:
            </label>
            <div class="relative">
              <i class="fa-solid fa-building absolute left-3.5 top-3.5 text-slate-400 text-xs"></i>
              <select v-model="loginEmpresa"
                      class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 cursor-pointer">
                <option value="ARYVE">CONSTRUCCIONES ARYVE</option>
                <option value="VELAS">GRUPO VELAS</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
              Usuario, No. de Empleado o Correo:
            </label>
            <div class="relative">
              <i class="fa-solid fa-user absolute left-3.5 top-3.5 text-slate-400 text-xs"></i>
              <input type="text" v-model="loginUsuario" autofocus
                     placeholder="Ingresa tu usuario, no. de empleado o correo..."
                     class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1.5">
              Contraseña de Acceso:
            </label>
            <div class="relative">
              <i class="fa-solid fa-lock absolute left-3.5 top-3.5 text-slate-400 text-xs"></i>
              <input type="password" v-model="loginPassword"
                     placeholder="Ingresa tu contraseña..."
                     class="w-full bg-slate-900 border rounded-xl pl-9 pr-4 py-2.5 text-sm text-slate-200 focus:outline-none transition-all duration-200"
                     :class="errorLogin ? 'border-rose-500 ring-1 ring-rose-500 bg-rose-950/20 placeholder-rose-400/50' : 'border-slate-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500'">
            </div>
          </div>

          <button type="submit" :disabled="cargandoLogin || !loginUsuario || !loginPassword"
                  class="w-full mt-2 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-sm font-semibold flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 transition cursor-pointer">
            <i class="fa-solid" :class="cargandoLogin ? 'fa-spinner fa-spin' : 'fa-arrow-right-to-bracket'"></i>
            <span>{{ cargandoLogin ? 'Verificando credenciales en ' + (loginEmpresa === 'VELAS' ? 'Grupo Velas...' : 'Construcciones Aryve...') : 'Iniciar Sesión' }}</span>
          </button>
        </form>

        <p class="text-[11px] text-center text-slate-500 mt-5">
          Acceso institucional • Sistema de Control y Conciliación
        </p>

      </div>
    </div>

    <!-- CONTENIDO PRINCIPAL DEL DASHBOARD (SI YA INICIÓ SESIÓN) -->
    <div v-else>

      <!-- HEADER EJECUTIVO -->
            <!-- HEADER PRINCIPAL COMPACTO Y UNIFICADO -->
      <header class="flex flex-col md:flex-row justify-between items-start md:items-center py-3 px-4 mb-4 bg-slate-850/90 border border-slate-700/80 rounded-2xl shadow-xl gap-3">
        
        <!-- Empresa activa -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border"
               :class="empresaActiva === 'VELAS' ? 'bg-amber-500/10 text-amber-300 border-amber-500/30' : 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30'">
            <i class="fa-solid fa-building text-[10px]"></i>
            <span>{{ empresaActiva === 'VELAS' ? 'GRUPO VELAS' : 'CONSTRUCCIONES ARYVE' }}</span>
          </div>
        </div>

        <!-- Derecha: acciones de sesión y exportación -->
        <div class="flex items-center gap-2.5 w-full md:w-auto justify-end flex-wrap">

          <button @click="cerrarSesion"
                  class="px-3 py-1.5 rounded-lg border border-rose-500/40 bg-rose-500/10 text-rose-300 hover:bg-rose-600 hover:text-white transition flex items-center gap-1.5 text-xs font-bold"
                  title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Salir</span>
          </button>

          <!-- BOTONES DE ACCIÓN EXCEL -->
          <a v-if="puedeVerConcentrado"
             :href="'/api/descargar-excel?empresa=' + encodeURIComponent(empresaActiva) + '&plaza=' + encodeURIComponent(filtroPlaza) + '&semana=' + encodeURIComponent(filtroSemana) + '&responsable=' + encodeURIComponent(filtroResponsable)" 
             class="bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-sm transition">
            <i class="fa-solid fa-file-excel"></i>
            <span>Exportar Excel</span>
          </a>

          <button v-if="puedeVerConcentrado && vistaActiva === 'CONCILIACION'"
                  @click="cargarDatos(1)" class="bg-indigo-600 hover:bg-indigo-500 text-white px-2.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1 transition" title="Refrescar">
            <i class="fa-solid fa-rotate" :class="{'fa-spin': cargando}"></i>
            <span>Actualizar</span>
          </button>
        </div>

      </header>

      <!-- CONTENEDOR VISTA 1: CONCILIACIÓN DE PAGOS (SOLO USUARIOS AUTORIZADOS) -->
      <div v-if="puedeVerConcentrado" v-show="vistaActiva === 'CONCILIACION'">

    <!-- TARJETAS DE RESUMEN (KPIS DEL DIBUJO CON TOTALES REALES DEL FILTRO) -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 my-6">
      <div class="bg-slate-800/80 border border-slate-700/80 p-5 rounded-xl">
        <p class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Total OCs en Filtro</p>
        <p class="text-3xl font-extrabold text-white mt-1">{{ formatearEntero(totalFiltrado) }}</p>
        <span class="text-xs text-indigo-400 mt-2 inline-block">De {{ formatearEntero(kpisGlobales.total_registros) }} registros totales</span>
      </div>

      <div class="bg-slate-800/80 border border-slate-700/80 p-5 rounded-xl">
        <p class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Importe Total Solicitado</p>
        <p class="text-3xl font-extrabold text-white mt-1">${{ formatearNumero(sumaImporte) }}</p>
        <span class="text-xs text-slate-400 mt-2 inline-block">Monto total de las OCs filtradas</span>
      </div>

      <div class="bg-slate-800/80 border border-slate-700/80 p-5 rounded-xl">
        <p class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Total Programado</p>
        <p class="text-3xl font-extrabold text-emerald-400 mt-1">${{ formatearNumero(sumaPagando) }}</p>
        <span class="text-xs text-emerald-500/80 mt-2 inline-block">Monto programado en esta vista</span>
      </div>

      <div class="bg-slate-800/80 border border-slate-700/80 p-5 rounded-xl">
        <p class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Diferencia / Pendiente</p>
        <p class="text-3xl font-extrabold text-amber-400 mt-1">${{ formatearNumero(sumaDiferencia) }}</p>
        <span class="text-xs text-amber-500/80 mt-2 inline-block">Saldo por liquidar</span>
      </div>
    </section>

    <!-- BARRA DE FILTROS LIMPIA Y BIEN ESTRUCTURADA -->
    <section class="bg-slate-800/80 border border-slate-700/80 p-5 rounded-xl mb-6 shadow-lg">
      <div class="flex flex-col gap-4">
        
        <!-- Fila 1: Búsqueda Rápida y Limpiar Filtros -->
        <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
          <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
            <input type="text" v-model="filtroBusqueda" @keyup.enter="cargarDatos(1)" 
                   placeholder="Buscar por O.C., Contratista, Proveedor, Obra..." 
                   class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
          </div>
          
          <div class="flex items-center gap-2">
            <button @click="cargarDatos(1)" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold flex items-center gap-2 transition shadow-sm">
              <i class="fa-solid fa-filter"></i>
              <span>Filtrar</span>
            </button>
            <button @click="limpiarFiltros" class="px-3.5 py-2.5 bg-slate-700/70 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-medium flex items-center gap-1.5 transition" title="Restablecer filtros">
              <i class="fa-solid fa-arrow-rotate-left text-xs"></i>
              <span>Limpiar</span>
            </button>
          </div>
        </div>

        <!-- Fila 2: Cuadrícula Equilibrada de 4 Filtros Específicos -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-slate-700/50">
          
          <!-- Filtro Plaza / Zona -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-semibold text-slate-400">Plaza / Zona</label>
              <span v-if="perfilUsuario && perfilUsuario.rol !== 'ADMIN'" class="text-[10px] font-semibold text-amber-400 flex items-center gap-1 bg-amber-400/10 px-1.5 py-0.2 rounded border border-amber-400/20" title="Plaza fija asignada a tu usuario">
                <i class="fa-solid fa-lock text-[8px]"></i> Fija
              </span>
            </div>

            <!-- Si es ADMIN: Puede manipular libremente todas las plazas -->
            <select v-if="perfilUsuario && perfilUsuario.rol === 'ADMIN'" 
                    v-model="filtroPlaza" @change="cargarDatos(1)" 
                    class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer">
              <option value="">Todas las Plazas</option>
              <option v-for="p in listaPlazas" :key="p" :value="p">{{ p }}</option>
            </select>

            <!-- Si es Usuario Normal: FIJA y BLOQUEADA (solo ve su plaza) -->
            <div v-else class="relative">
              <select v-model="filtroPlaza" disabled 
                      class="w-full bg-slate-900/60 border border-slate-700/60 rounded-lg pl-3 pr-8 py-2 text-sm text-slate-300 font-semibold cursor-not-allowed opacity-90 shadow-inner">
                <option :value="filtroPlaza">{{ filtroPlaza || 'Plaza Asignada' }}</option>
              </select>
              <div class="absolute right-3 top-2.5 text-xs text-amber-400/80 pointer-events-none" title="Tu plaza está fija y asignada por sistema">
                <i class="fa-solid fa-lock"></i>
              </div>
            </div>
          </div>

          <!-- Filtro Rubro (Mano de Obra, Insumos, etc.) -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Rubro (Mano de Obra...)</label>
            <select v-model="filtroRubro" @change="cargarDatos(1)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
              <option value="">Todos los Rubros</option>
              <option v-for="rb in listaRubros" :key="rb" :value="rb">{{ rb }}</option>
            </select>
          </div>

          <!-- Filtro Responsable -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Enviado Por</label>
            <div v-if="usuarioActivo !== 'ADMIN'" class="relative">
              <input type="text" :value="usuarioActivo + ' (Mis OCs)'" disabled
                     class="w-full bg-slate-950 border border-indigo-500/50 text-indigo-300 font-semibold rounded-lg px-3 py-2 text-sm cursor-not-allowed">
              <i class="fa-solid fa-lock absolute right-3 top-2.5 text-xs text-indigo-400/70"></i>
            </div>
            <select v-else v-model="filtroResponsable" @change="cargarDatos(1)" 
                    class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
              <option value="">Todos los Responsables</option>
              <option v-for="r in listaResponsables" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <!-- Filtro Semana -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-semibold text-slate-400">Semana de Corte</label>
              <button v-if="semanaActualOficial" @click="filtroSemana = semanaActualOficial.etiqueta; cargarDatos(1)" 
                      class="text-[11px] text-emerald-400 hover:text-emerald-300 font-bold flex items-center gap-1 cursor-pointer" 
                      :title="'Semana en curso oficial (' + semanaActualOficial.fecha_inicio + ' al ' + semanaActualOficial.fecha_final + ')'">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span>{{ semanaActualOficial.etiqueta }}</span>
              </button>
            </div>
            <select v-model="filtroSemana" @change="cargarDatos(1)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer">
              <option value="">Todas las Semanas</option>
              <option v-if="semanaActualOficial && !listaSemanas.includes(semanaActualOficial.etiqueta)" :value="semanaActualOficial.etiqueta">
                {{ semanaActualOficial.etiqueta }} (En Curso)
              </option>
              <option v-for="s in listaSemanas" :key="s" :value="s">
                {{ s === (semanaActualOficial && semanaActualOficial.etiqueta) ? s + ' (En Curso)' : s }}
              </option>
            </select>
          </div>

        </div>

      </div>
    </section>

    <!-- TABLA EJECUTIVA PRINCIPAL -->
    <main class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-2xl">
      <!-- Contenedor con Scroll Interno y Altura Controlada -->
      <div class="overflow-x-auto max-h-[58vh] overflow-y-auto relative scrollbar-thin scrollbar-thumb-slate-700">
        <table class="w-full text-left border-collapse text-xs min-w-[1250px]">
          <thead class="sticky top-0 z-10 bg-slate-900 shadow-md">
            <tr class="bg-slate-900 border-b border-slate-700 text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">
              <th class="py-2.5 px-3 text-center w-16">Conciliar</th>
              <th class="py-2.5 px-3 w-24">Plaza</th>
              <th class="py-2.5 px-3 w-56">Obra</th>
              <th class="py-2.5 px-3 w-24"># O.C.</th>
              <th class="py-2.5 px-3 min-w-[200px]">Proveedor / Contratista</th>
              <th class="py-2.5 px-3 w-32">Rubro</th>
              <th class="py-2.5 px-3 text-right w-32">Importe Total ($)</th>
              <th class="py-2.5 px-3 text-right w-32">Programado ($)</th>
              <th class="py-2.5 px-3 text-right w-32">Diferencia ($)</th>
              <th class="py-2.5 px-3 text-center w-24">Semana</th>
              <th class="py-2.5 px-3 text-center w-32">Responsable</th>
              <th class="py-2.5 px-3 text-center w-24">Estatus</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-700/50">
            <tr v-if="cargando">
              <td colspan="12" class="text-center py-10 text-indigo-400">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i>
                Consultando información...
              </td>
            </tr>
            <tr v-else-if="registros.length === 0">
              <td colspan="12" class="text-center py-8 text-slate-500">
                <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                No se encontraron registros con los filtros seleccionados.
              </td>
            </tr>
            <tr v-else v-for="item in registros" :key="item.id_fila" 
                class="hover:bg-slate-750/50 transition"
                :class="{'bg-emerald-950/20': item.CONCILIADO}">
              
              <!-- CHECKBOX DEL DIBUJO -->
              <td class="py-2 px-3 text-center">
                <input type="checkbox" v-model="item.CONCILIADO" :true-value="1" :false-value="0" @change="guardarCambio(item)"
                       class="w-3.5 h-3.5 rounded border-slate-700 text-indigo-600 focus:ring-indigo-500 bg-slate-900 cursor-pointer">
              </td>

              <!-- PLAZA / ZONA -->
              <td class="py-2 px-3 font-semibold text-slate-200">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                  {{ item.ZONA || 'N/A' }}
                </span>
              </td>

              <!-- OBRA -->
              <td class="py-2 px-3 text-slate-300 font-mono text-xs">
                <div class="font-bold text-slate-200 leading-tight">{{ item['NOMBRE OBRA'] || item.FRACCIONAMIENTO || item.OBRA }}</div>
                <div class="text-[10px] text-slate-500 truncate max-w-[220px]">Clave: {{ item.OBRA }}</div>
              </td>

              <!-- # OC -->
              <td class="py-2 px-3 font-mono font-bold text-indigo-400">
                {{ item['O.C.'] }}
              </td>

              <!-- PROVEEDOR -->
              <td class="py-2 px-3 text-slate-200">
                <div class="font-medium truncate max-w-[240px] leading-tight">{{ item.CONTRATISTA }}</div>
                <div class="text-[10px] text-slate-500 font-mono">Prov: {{ item['CLAVE PROV'] || item['ID PROV'] }}</div>
              </td>

              <!-- RUBRO (MO, MAT, SUB) -->
              <td class="py-2 px-3">
                <span class="px-2 py-0.5 rounded text-[11px] font-bold"
                      :class="{
                        'bg-amber-500/10 text-amber-400 border border-amber-500/30': item.RUBRO === 'MANO DE OBRA' || item.RUBRO === 'MO',
                        'bg-blue-500/10 text-blue-400 border border-blue-500/30': item.RUBRO.includes('CONCRETO') || item.RUBRO.includes('MAT'),
                        'bg-purple-500/10 text-purple-400 border border-purple-500/30': item.RUBRO.includes('SUB'),
                        'bg-slate-700 text-slate-300': !item.RUBRO.includes('MANO') && !item.RUBRO.includes('MAT') && !item.RUBRO.includes('SUB')
                      }">
                  {{ item.RUBRO }}
                </span>
              </td>

              <!-- IMPORTE TOTAL -->
              <td class="py-2 px-3 text-right font-mono font-semibold text-slate-200">
                ${{ formatearNumero(item.IMPORTE) }}
              </td>

              <!-- PROGRAMADO -->
              <td class="py-2 px-3 text-right font-mono font-bold text-emerald-400">
                ${{ formatearNumero(item['PAGO A']) }}
              </td>

              <!-- DIFERENCIA -->
              <td class="py-2 px-3 text-right font-mono font-bold"
                  :class="item.DIFERENCIA > 0 ? 'text-amber-400' : 'text-slate-500'">
                ${{ formatearNumero(item.DIFERENCIA) }}
              </td>

              <!-- SEMANA -->
              <td class="py-2 px-3 text-center font-mono text-[11px] text-slate-400">
                {{ item.SEMANA }}
              </td>

              <!-- ENVIADO POR -->
              <td class="py-2 px-3 text-center">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-slate-900 border border-slate-700 text-slate-300 truncate max-w-[140px]">
                  <i class="fa-solid fa-user-circle text-slate-400 text-xs"></i>
                  <span class="truncate">{{ item['ENVIADO POR'] || 'N/A' }}</span>
                </span>
              </td>

              <!-- ESTATUS -->
              <td class="py-2 px-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                      :class="item.CONCILIADO ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-slate-700 text-slate-400'">
                  {{ item.CONCILIADO ? 'CONCILIADO' : (item.ESTATUS || 'PENDIENTE') }}
                </span>
              </td>

            </tr>
          </tbody>
        </table>
      </div>

      <!-- PAGINACIÓN SERVER-SIDE COMPACTA CON SELECTOR DE FILAS Y TELEMETRÍA -->
      <footer class="flex flex-col sm:flex-row justify-between items-center px-4 py-2.5 bg-slate-900/90 border-t border-slate-700 gap-2 text-xs text-slate-400">
        <div class="flex items-center gap-3">
          <div>
            Página <span class="font-bold text-white">{{ paginaActual }}</span> de <span class="font-bold text-white">{{ totalPaginas }}</span> 
            ({{ formatearEntero(totalFiltrado) }} registros)
          </div>
          <div class="flex items-center gap-1.5 border-l border-slate-700 pl-3">
            <span class="text-slate-400">Mostrar:</span>
            <select v-model="limiteFilas" @change="cargarDatos(1)" 
                    class="bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-200 text-xs focus:outline-none focus:border-indigo-500 cursor-pointer">
              <option :value="10">10 filas</option>
              <option :value="15">15 filas</option>
              <option :value="25">25 filas</option>
              <option :value="50">50 filas</option>
            </select>
          </div>
        </div>

        <!-- Indicador de telemetría visible solo para Administrador en el footer de la tabla -->
        <div v-if="usuarioActivo === 'ADMIN'" class="hidden md:flex items-center gap-2 text-[11px] text-slate-400 font-mono">
          <i class="fa-solid fa-bolt" :class="capaOrigen === 'CACHE_HASH_RAM_O1' ? 'text-cyan-400' : 'text-indigo-400'"></i>
          <span>Resuelto en <strong class="text-white">{{ tiempoRespuestaMs }} ms</strong> vía <strong :class="capaOrigen === 'CACHE_HASH_RAM_O1' ? 'text-cyan-400' : 'text-indigo-400'">{{ capaOrigen === 'CACHE_HASH_RAM_O1' ? 'Capa 1: Tabla Hash RAM O(1)' : 'Capa 2: Índice B-Tree Disco O(log N)' }}</strong></span>
        </div>

        <div class="flex items-center gap-1.5">
          <button @click="cargarDatos(1)" :disabled="paginaActual <= 1" class="px-2 py-1 bg-slate-800 border border-slate-700 rounded hover:bg-slate-700 disabled:opacity-40 transition text-xs">
            <i class="fa-solid fa-angles-left"></i>
          </button>
          <button @click="cargarDatos(paginaActual - 1)" :disabled="paginaActual <= 1" class="px-2.5 py-1 bg-slate-800 border border-slate-700 rounded hover:bg-slate-700 disabled:opacity-40 transition text-xs">
            Anterior
          </button>
          <button @click="cargarDatos(paginaActual + 1)" :disabled="paginaActual >= totalPaginas" class="px-2.5 py-1 bg-slate-800 border border-slate-700 rounded hover:bg-slate-700 disabled:opacity-40 transition text-xs">
            Siguiente
          </button>
          <button @click="cargarDatos(totalPaginas)" :disabled="paginaActual >= totalPaginas" class="px-2 py-1 bg-slate-800 border border-slate-700 rounded hover:bg-slate-700 disabled:opacity-40 transition text-xs">
            <i class="fa-solid fa-angles-right"></i>
          </button>
        </div>
      </footer>
    </main>
    </div> <!-- Fin de vistaActiva === 'CONCILIACION' -->

    <!-- ==================================================== -->
    <!-- VISTA 2: ÓRDENES DE COMPRA                          -->
    <!-- ==================================================== -->
    <div v-show="vistaActiva === 'ORDENES_VIVO'">
            <!-- Tarjetas KPIs Vivo y Ficha de Datos del Usuario (DISEÑO ULTRA COMPACTO) -->
      <section class="mx-2 sm:mx-5 grid grid-cols-1 lg:grid-cols-12 gap-3 mb-4">
        
        <!-- Tarjeta Izquierda: ÓRDENES HOY (Compacta) -->
        <div class="lg:col-span-4 bg-slate-800/90 border border-slate-700/80 px-4 py-2.5 rounded-xl shadow-md flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2 mb-0.5">
              <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                <i class="fa-regular fa-calendar-check text-xs"></i> ÓRDENES HOY
              </span>
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            </div>
            <div class="flex items-baseline gap-2">
              <span class="text-2xl font-black text-white leading-tight">{{ kpisVivo.ordenes_hoy }}</span>
              <span class="text-[11px] text-slate-400">órdenes hoy</span>
            </div>
          </div>
          <div class="text-right">
            <span class="text-[10px] text-slate-400 block uppercase font-semibold">Monto total de órdenes hoy</span>
            <p class="text-xl font-black text-emerald-300 font-mono leading-tight">${{ formatearNumero(kpisVivo.monto_hoy) }}</p>
          </div>
        </div>

        <!-- Tarjeta Derecha: DATOS DEL USUARIO (Compacta al máximo) -->
        <div class="lg:col-span-8 bg-slate-800/90 border border-slate-700/80 px-4 py-2.5 rounded-xl shadow-md flex items-center justify-between flex-wrap gap-2">
          
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
              <i class="fa-solid fa-id-card text-sm"></i>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-base font-extrabold text-white tracking-tight leading-tight">{{ usuarioActivo }}</h2>
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                  {{ perfilUsuario?.puesto || 'Puesto no asignado' }}
                </span>
              </div>
              <span class="text-[10px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                <span>Sesión Activa</span> • 
                <span class="text-amber-300 font-semibold">{{ (perfilUsuario && perfilUsuario.es_admin_funcional) ? 'Supervisión Global' : 'Mis Órdenes' }}</span>
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <!-- Botón alternar Mis Órdenes / Toda la Empresa -->
            <button v-if="perfilUsuario && (perfilUsuario.rol === 'ADMIN' || perfilUsuario.es_admin_funcional)"
                    @click="filtroVivoMisOrdenes = !filtroVivoMisOrdenes; cargarOrdenesVivo(1)"
                    class="px-3 py-1 bg-slate-900 hover:bg-slate-700 border border-slate-700 rounded-lg text-xs font-semibold text-slate-200 flex items-center gap-1.5 transition cursor-pointer shadow-sm">
              <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i>
              <span>{{ filtroVivoMisOrdenes ? 'Ver Toda la Empresa' : 'Ver Solo Mis Órdenes' }}</span>
            </button>
          </div>

        </div>

      </section>

<!-- Barra de Filtros de OCs en Vivo -->
            <!-- BARRA UNIFICADA DE FILTROS Y ACCIONES DE PROGRAMACIÓN -->
      <section class="mx-2 sm:mx-5 bg-gradient-to-r from-slate-800 via-slate-850 to-slate-800 border-2 border-emerald-500/30 p-4 rounded-2xl shadow-2xl mb-5 space-y-3.5">
        
        <!-- Fila 1: Semana de Corte, búsqueda y refresco -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
          <!-- Semana de Corte + Buscador + Refrescar -->
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1">
            <!-- Filtro Semana de Corte -->
            <div class="min-w-[190px]">
              <div class="flex items-center justify-between mb-1 px-1">
                <span class="text-[11px] font-semibold text-slate-400">Semana de Corte</span>
                <button v-if="semanaActualOficial" 
                        @click="filtroVivoSemana = semanaActualOficial.etiqueta; semanaCorteMasiva = semanaActualOficial.etiqueta; cargarOrdenesVivo(1)" 
                        class="text-[11px] text-emerald-400 hover:text-emerald-300 font-bold flex items-center gap-1 cursor-pointer" 
                        :title="'Semana en curso oficial (' + semanaActualOficial.fecha_inicio + ' al ' + semanaActualOficial.fecha_final + ')'">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                  <span>{{ semanaActualOficial.etiqueta }}</span>
                </button>
              </div>
              <select v-model="filtroVivoSemana" @change="onCambioSemanaCorteFiltro" 
                      :disabled="!listaSemanas.length && !semanaActualOficial"
                      class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                <option v-if="semanaActualOficial && !listaSemanas.includes(semanaActualOficial.etiqueta)" :value="semanaActualOficial.etiqueta" class="bg-slate-900 text-white">
                  {{ semanaActualOficial.etiqueta }} (En Curso)
                </option>
                <option v-for="s in listaSemanas" :key="s" :value="s" class="bg-slate-900 text-white">
                  {{ s === (semanaActualOficial && semanaActualOficial.etiqueta) ? s + ' (En Curso)' : s }}
                </option>
              </select>
            </div>

            <!-- Buscador y botón refrescar -->
            <div class="flex items-center gap-2 flex-1 sm:self-end">
              <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                <input type="text" v-model="filtroVivoBusqueda" @keyup.enter="cargarOrdenesVivo(1)" @input="debounceBusquedaVivo"
                       placeholder="Buscar por # O.C., Folio EK10, Proveedor, Obra..."
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-emerald-500">
              </div>
              <button @click="cargarOrdenesVivo(1)" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer" title="Refrescar órdenes en tiempo real">
                <i class="fa-solid fa-rotate" :class="{'fa-spin': cargandoVivo}"></i>
                <span>Refrescar</span>
              </button>
            </div>
          </div>

        </div>

        <!-- Fila 2: Barra de Programación (Filtro Obra limpia, Botón 100%, Totalizador y Guardar Todo) -->
        <div class="pt-2 border-t border-slate-700/60 flex items-center justify-between flex-wrap gap-3">
          
          <!-- Lado Izquierdo: Búsqueda de obra y botón Pagar 100% -->
          <div class="flex items-center gap-3 flex-wrap">
            <div class="relative w-full sm:w-64">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
              <input id="filtro-clave-obra"
                     v-model="filtroVivoObra"
                     @change="aplicarFiltroObra"
                     @focus="mostrarSugerenciasObra = true"
                     @input="mostrarSugerenciasObra = true"
                     @keyup.enter="obrasSugeridasVivo.length ? seleccionarObraVivo(obrasSugeridasVivo[0]) : aplicarFiltroObra()"
                     @keydown.esc="mostrarSugerenciasObra = false"
                     @blur="mostrarSugerenciasObra = false"
                     aria-label="Buscar obra"
                     placeholder="Buscar obra..."
                     autocomplete="off"
                     class="w-full rounded-lg border border-slate-700 bg-slate-900/90 py-2 pl-9 pr-9 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
              <button v-if="filtroVivoObra" type="button" @click="filtroVivoObra = ''; aplicarFiltroObra()"
                      class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 transition hover:bg-slate-700 hover:text-white" title="Limpiar búsqueda">
                <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
              </button>
              <div v-if="mostrarSugerenciasObra && obrasSugeridasVivo.length"
                   class="absolute left-0 top-full z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-600 bg-slate-800 p-1 shadow-xl">
                <button v-for="obra in obrasSugeridasVivo" :key="obra" type="button"
                        @mousedown.prevent="seleccionarObraVivo(obra)"
                        class="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-slate-200 transition hover:bg-emerald-500/15 hover:text-emerald-300">
                  {{ obra }}
                </button>
              </div>
            </div>

            <!-- Botones de Acción Masiva -->
            <div class="flex items-center gap-2">
              <button type="button" @click="llenarTodasConSaldo" 
                      class="px-3 py-1.5 bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/40 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                      title="Poner el 100% de saldo a todas las órdenes visibles">
                <i class="fa-solid fa-bolt-lightning text-xs"></i>
                <span>Pagar Todo al 100%</span>
              </button>
              <button type="button" @click="limpiarTodosLosInputs" v-if="ordenesListasParaGuardar.length > 0"
                      class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-xl text-xs transition cursor-pointer"
                      title="Limpiar montos capturados">
                <i class="fa-solid fa-eraser"></i>
                <span>Limpiar</span>
              </button>
            </div>
          </div>

          <!-- Lado Derecho: Totalizador y Botón Guardar Todo (sin emoji) -->
          <div class="flex items-center gap-4">
            <div class="text-right">
              <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                CAPTURADAS PARA PAGO: <strong class="text-white">{{ ordenesListasParaGuardar.length }}</strong> de {{ ordenesFiltradasObra.length }}
              </div>
              <div class="text-xl font-mono font-extrabold text-emerald-300">
                ${{ formatearNumero(totalMontoLote) }}
              </div>
            </div>

            <button @click="guardarLoteMasivo" :disabled="guardandoLote || ordenesListasParaGuardar.length === 0"
                    class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black rounded-xl text-xs flex items-center gap-2 shadow-xl shadow-emerald-500/30 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer uppercase tracking-wider">
              <i class="fa-solid" :class="guardandoLote ? 'fa-spinner fa-spin' : 'fa-floppy-disk text-sm'"></i>
              <span>{{ guardandoLote ? 'PREPARANDO LOTE...' : 'GUARDAR TODO (' + ordenesListasParaGuardar.length + ')' }}</span>
            </button>
          </div>

        </div>

      </section>

<!-- Tabla de Órdenes de Compra en Vivo (Edición en Línea Masiva) -->
      <div class="mx-2 sm:mx-5 bg-slate-800/80 border border-slate-700/80 rounded-2xl shadow-xl overflow-hidden mb-6">
        <div class="p-3.5 border-b border-slate-700/80 flex items-center justify-between flex-wrap gap-2 bg-slate-850">
          <div></div>
          <!-- Paginación arriba -->
          <div class="flex items-center gap-2 text-xs">
            <button @click="cargarOrdenesVivo(paginaVivo - 1)" :disabled="paginaVivo <= 1 || cargandoVivo"
                    class="px-2.5 py-1 bg-slate-700 disabled:opacity-30 rounded text-slate-300 hover:text-white cursor-pointer disabled:cursor-not-allowed">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span class="text-slate-400">Pág. {{ paginaVivo }} de {{ totalPaginasVivo }}</span>
            <button @click="cargarOrdenesVivo(paginaVivo + 1)" :disabled="paginaVivo >= totalPaginasVivo || cargandoVivo"
                    class="px-2.5 py-1 bg-slate-700 disabled:opacity-30 rounded text-slate-300 hover:text-white cursor-pointer disabled:cursor-not-allowed">
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>

        <div class="overflow-x-auto max-h-[74vh] overflow-y-auto relative scrollbar-thin scrollbar-thumb-slate-700">
          <table class="w-full text-left border-collapse text-xs min-w-[1450px]">
            <thead class="sticky top-0 z-10 bg-slate-900 shadow-md">
              <tr class="bg-slate-900 border-b border-slate-700 text-[11px] font-bold text-slate-400 uppercase tracking-wider whitespace-nowrap">
                <th class="py-2.5 px-3 w-16 text-center">OBRA</th>
                <th class="py-2.5 px-3 w-20 text-center">OC</th>
                <th class="py-2.5 px-3 text-center w-36">FOLIO EK10 - EK9</th>
                <th class="py-2.5 px-3 w-24 text-center">FECHA</th>
                <th class="py-2.5 px-3 min-w-[180px]">PROVEEDOR / CONTRATISTA</th>
                <th class="py-2.5 px-3 w-24 text-right">IMPORTE ($)</th>
                <th class="py-2.5 px-3 w-24 text-right text-teal-400" title="Total recibido físicamente en almacén / remisiones">RECIBIDO ($)</th>
                <th class="py-2.5 px-3 w-24 text-right text-sky-400" title="Total de facturas registradas por contabilidad">FACTURADO ($)</th>
                <th class="py-2.5 px-3 min-w-[145px] w-36 text-center text-cyan-300" title="Macroclasificación Enkontrol (1, 2, 4, 7)">TIPO</th>
                <th class="py-2.5 px-3 min-w-[155px] w-44 text-center text-indigo-300" title="Especialidad o rubro de la obra">RUBRO</th>
                <th class="py-2.5 px-3 w-36 text-center text-emerald-400 bg-emerald-950/20 border-x border-emerald-500/30">
                  <span class="flex items-center justify-center gap-1">
                    <i class="fa-solid fa-pencil text-[10px]"></i> IMPORTE A PROGRAMAR ($)
                  </span>
                </th>
                <th class="py-2.5 px-3 w-28 text-right text-amber-400">DIFERENCIA ($)</th>
                <th class="py-2.5 px-3 min-w-[180px] text-slate-300">CONCEPTO / ESTIMACIÓN</th>
                <th class="py-2.5 px-3 w-32">SOLICITÓ</th>
                <th class="py-2.5 px-3 w-28 text-center text-indigo-300" title="Adjuntar comprobantes/facturas (2 a 4 archivos)">DOCUMENTOS</th>
                <th class="py-2.5 px-3 w-20 text-center">HISTORIAL</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
              <tr v-if="cargandoVivo && ordenesVivo.length === 0">
                <td colspan="16" class="text-center py-10 text-emerald-400">
                  <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i>
                  Consultando órdenes de compra en tiempo real...
                </td>
              </tr>
              <tr v-else-if="ordenesFiltradasObra.length === 0">
                <td colspan="16" class="text-center py-10 text-slate-500">
                  <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                  No se encontraron órdenes de compra para los filtros seleccionados.
                </td>
              </tr>
              <template v-else v-for="oc in ordenesFiltradasObra" :key="oc.id">
                <tr class="hover:bg-slate-750/50 transition"
                    :class="{
                      'bg-emerald-950/25 border-l-4 border-l-emerald-400': Number(oc.inputMonto) > 0,
                      'bg-slate-800/40': ocExpandidaId === oc.id
                    }">
                  <!-- 1. OBRA -->
                  <td class="py-2 px-3 text-center font-bold text-amber-300 font-mono">
                    {{ oc.centro_costos || 'N/A' }}
                  </td>

                  <!-- 2. OC -->
                  <td class="py-2 px-3 text-center font-mono font-extrabold text-indigo-400">
                    #{{ oc.numero_oc }}
                  </td>

                  <!-- 3. FOLIO EK10 - EK9 -->
                  <td class="py-2 px-3 text-center whitespace-nowrap">
                    <span v-if="oc.id_ek10" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/25 shadow-sm" title="Folio EK10 vinculado a Orden EK9">
                      <span>{{ oc.id_ek10 }}</span>
                      <i class="fa-solid fa-arrow-right-arrow-left text-[9px] text-cyan-400/80"></i>
                      <span class="text-indigo-300">#{{ oc.numero_oc }}</span>
                    </span>
                    <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono text-slate-400 bg-slate-900 border border-slate-700/80" title="Orden generada directamente en EK9">
                      <span class="text-slate-500 text-[9px]">EK9:</span>
                      <span class="font-bold text-amber-300">#{{ oc.numero_oc }}</span>
                    </span>
                  </td>

                  <!-- 4. FECHA -->
                  <td class="py-2 px-3 text-center whitespace-nowrap font-mono text-slate-300 text-[11px]">
                    {{ oc.fecha || 'N/A' }}
                  </td>

                  <!-- 5. PROVEEDOR / CONTRATISTA -->
                  <td class="py-2 px-3 font-medium text-slate-200">
                    <div class="truncate max-w-[320px]" :title="oc.proveedor">{{ oc.proveedor || 'Sin Proveedor' }}</div>
                  </td>

                  <!-- 6. IMPORTE ($) -->
                  <td class="py-2 px-3 text-right font-mono font-bold text-white whitespace-nowrap">
                    ${{ formatearNumero(oc.total) }}
                  </td>

                  <!-- 6.1 RECIBIDO ALMACÉN ($) (INFORMATIVO) -->
                  <td class="py-2 px-3 text-right font-mono font-semibold whitespace-nowrap"
                      :class="Number(oc.total_rec) > 0 ? 'text-teal-300' : 'text-slate-600'">
                    ${{ formatearNumero(oc.total_rec || 0) }}
                  </td>

                  <!-- 6.2 FACTURADO ($) (INFORMATIVO) -->
                  <td class="py-2 px-3 text-right font-mono font-semibold whitespace-nowrap"
                      :class="Number(oc.total_fac) > 0 ? 'text-sky-300' : 'text-slate-600'">
                    ${{ formatearNumero(oc.total_fac || 0) }}
                  </td>

                  <!-- 7. TIPO (ASIGNADO POR EL ALGORITMO, SOLO LECTURA) -->
                  <td class="py-1.5 px-2 text-center whitespace-nowrap">
                    <span
                            class="campo-tipo-fijo inline-flex max-w-full items-center justify-center rounded-lg px-2 py-1.5 border text-[11px] font-mono font-bold"
                            :class="{
                              'bg-cyan-950/70 text-cyan-300 border-cyan-500/40': (oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(1)'),
                              'bg-amber-950/70 text-amber-300 border-amber-500/40': (oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(2)'),
                              'bg-purple-950/70 text-purple-300 border-purple-500/40': (oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(4)'),
                              'bg-emerald-950/70 text-emerald-300 border-emerald-500/40': (oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(7)'),
                              'bg-slate-900 text-slate-300 border-slate-700': !(oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(1)') && !(oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(2)') && !(oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(4)') && !(oc.inputTipo || oc.tipo_sugerido || oc.tipo || '').includes('(7)')
                            }"
                            :title="'Tipo asignado automáticamente por el sistema: ' + (oc.inputTipo || oc.tipo_sugerido || oc.tipo || '(1) MATERIALES')">
                      {{ oc.inputTipo || oc.tipo_sugerido || oc.tipo || '(1) MATERIALES' }}
                    </span>
                  </td>

                  <!-- 7.1 RUBRO / ESPECIALIDAD (SUGERIDO POR SISTEMA + SELECCIONABLE) -->
                  <td class="py-1.5 px-2 text-center min-w-[170px]">
                    <select v-model="oc.inputRubro"
                            class="campo-rubro w-full text-[11px] font-extrabold uppercase rounded-lg px-2.5 py-1.5 border border-indigo-500/40 bg-indigo-950/60 text-indigo-200 shadow-inner focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition cursor-pointer"
                            :title="'Rubro sugerido: ' + (oc.rubro_sugerido || 'GENERAL') + '. Puedes seleccionar cualquier rubro del catálogo.'">
                      <option v-if="oc.inputRubro && !catalogoRubrosCompleto.includes(oc.inputRubro)"
                              :value="oc.inputRubro">
                        {{ oc.inputRubro }} (sugerido)
                      </option>
                      <option v-for="(rb, indice) in catalogoRubrosCompleto" :key="indice" :value="rb">
                        {{ rb }}
                      </option>
                    </select>
                  </td>

                  <!-- 8. IMPORTE A PROGRAMAR ($) (INPUT DIRECTO EN LÍNEA) -->
                  <td class="py-1.5 px-2 bg-emerald-950/20 border-x border-emerald-500/20">
                    <div class="relative">
                      <span class="absolute left-2 top-2 text-slate-500 font-mono text-[11px]">$</span>
                      <input type="number" step="0.01" min="0" 
                             :max="(oc.saldo_disponible !== undefined && oc.saldo_disponible !== null) ? oc.saldo_disponible : oc.total"
                             v-model.number="oc.inputMonto"
                             @input="validarMontoMaximo(oc)"
                             :disabled="(oc.saldo_disponible !== undefined && oc.saldo_disponible <= 0.009)"
                             :placeholder="(oc.saldo_disponible !== undefined && oc.saldo_disponible <= 0.009) ? 'PAGADO 100%' : '0.00'"
                             class="w-full bg-slate-900 border rounded-lg pl-5 pr-2 py-1.5 text-xs font-mono font-extrabold text-right focus:outline-none focus:ring-2 focus:ring-emerald-400 transition disabled:opacity-40 disabled:bg-slate-950 disabled:cursor-not-allowed disabled:text-slate-500"
                             :class="Number(oc.inputMonto) > 0 ? 'border-emerald-500 bg-emerald-950/30 text-emerald-300 shadow-sm shadow-emerald-500/20' : 'border-slate-700 text-slate-300'">
                    </div>
                  </td>
                  <!-- 9. DIFERENCIA ($) / SALDO RESTANTE -->
                  <td class="py-2 px-3 text-right font-mono font-bold whitespace-nowrap">
                    <span class="inline-block px-1.5 py-0.5 rounded text-[11px]"
                          :class="{
                            'text-emerald-300 bg-emerald-500/10 border border-emerald-500/20': (oc.saldo_disponible - (Number(oc.inputMonto) || 0)) <= 0.01,
                            'text-amber-300 bg-amber-500/10 border border-amber-500/20': (oc.saldo_disponible - (Number(oc.inputMonto) || 0)) > 0.01
                          }">
                      ${{ formatearNumero(Math.max(0, (oc.saldo_disponible !== undefined ? oc.saldo_disponible : oc.total) - (Number(oc.inputMonto) || 0))) }}
                    </span>
                  </td>

                  <!-- 10. CONCEPTO / ESTIMACIÓN (INPUT DIRECTO) -->
                  <td class="py-1.5 px-2">
                    <input type="text" v-model="oc.inputConcepto"
                           placeholder="Est. 1 / Finiquito / Anticipo..."
                           class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-400 transition"
                           :class="{'border-emerald-500/60': Number(oc.inputMonto) > 0}">
                  </td>

                  <!-- 11. SOLICITÓ -->
                  <td class="py-2 px-3 text-slate-300 text-[11px] truncate max-w-[130px]" :title="oc.solicito_empleado">
                    {{ oc.solicito_empleado || ('ID ' + oc.id_empleado) }}
                  </td>

                  <!-- 14.1 ADJUNTAR ARCHIVOS (EN PAUSA HASTA ENVIAR EL PAGO) -->
                  <td class="py-1.5 px-2 text-center whitespace-nowrap">
                    <!-- 1. Si ya cuenta con documentos guardados en la BD (Blindaje anti-duplicados) -->
                    <div v-if="oc.total_documentos > 0" class="flex items-center justify-center gap-1.5">
                      <button type="button" @click="abrirVisorDocumentos(oc)"
                              class="accion-documentos-oc px-2.5 py-1 bg-emerald-600/30 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/50 rounded-lg text-[10px] font-black transition flex items-center gap-1 shadow-sm cursor-pointer"
                              title="OC con comprobantes adjuntados. No se permiten duplicados. Haz clic para verlos.">
                        <i class="fa-solid fa-circle-check text-xs text-emerald-400"></i>
                        <span>ADJUNTADO ({{ oc.total_documentos }})</span>
                      </button>
                    </div>

                    <!-- 2. Si ya tiene archivos en pausa / preparados para este pago -->
                    <div v-else-if="archivosPendientesPorOC[claveDocumentosOC(oc)] && archivosPendientesPorOC[claveDocumentosOC(oc)].length > 0">
                      <button type="button" @click="abrirModalAdjuntarOC(oc)"
                              class="px-2.5 py-1 rounded-lg text-[10px] font-black bg-emerald-500 text-slate-950 border border-emerald-400 flex items-center justify-center gap-1 mx-auto shadow-md hover:bg-emerald-400 cursor-pointer transition animate-pulse"
                              :title="'Archivos en pausa (' + archivosPendientesPorOC[claveDocumentosOC(oc)].length + '). Se guardarán junto con el pago al pulsar GUARDAR TODO.'">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>ADJUNTADO ({{ archivosPendientesPorOC[claveDocumentosOC(oc)].length }})</span>
                      </button>
                    </div>

                    <!-- 3. Si ya liquidó al 100% y no subió archivos: bloqueo -->
                    <div v-else-if="oc.saldo_disponible <= 0.009" class="text-[10px] text-slate-500 font-semibold italic" title="OC liquidada sin documentos adjuntos">
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-900 border border-slate-800">
                        <i class="fa-solid fa-lock text-[9px]"></i> Bloqueado
                      </span>
                    </div>

                    <!-- 4. Permitir preparar documentos antes de capturar el importe -->
                    <div v-else>
                      <button type="button" @click="abrirModalAdjuntarOC(oc)"
                              class="accion-adjuntar-oc px-2.5 py-1 rounded-lg text-[10px] font-bold transition flex items-center justify-center gap-1 mx-auto shadow-sm bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/40 cursor-pointer"
                              :title="Number(oc.inputMonto) > 0 ? 'Preparar comprobantes para el pago de $' + formatearNumero(oc.inputMonto) : 'Preparar comprobantes ahora; captura el importe antes de pulsar GUARDAR TODO'">
                        <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
                        <span>ADJUNTAR</span>
                      </button>
                    </div>
                  </td>

                  <!-- 15. HISTORIAL / DESGLOSE OC -->
                  <td class="py-1.5 px-2 text-center whitespace-nowrap">
                    <button type="button" @click="alternarAcordeonOC(oc)"
                            class="px-2 py-1 rounded text-[11px] font-bold transition flex items-center justify-center gap-1 mx-auto"
                            :class="ocExpandidaId === oc.id ? 'bg-indigo-600 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-200'"
                            :title="'Ver historial de pagos de la OC #' + oc.numero_oc">
                      <i class="fa-solid" :class="ocExpandidaId === oc.id ? 'fa-chevron-up' : 'fa-list-check'"></i>
                      <span>{{ (oc.programaciones || []).length }}</span>
                    </button>
                  </td>
                </tr>

                <!-- HISTORIAL DESPLEGABLE DE LA OC (SI DESEA VER PAGOS PREVIOS) -->
                <tr v-if="ocExpandidaId === oc.id" class="bg-slate-900/90 border-y-2 border-indigo-500/30">
                  <td colspan="16" class="p-3">
                    <div class="bg-slate-850 border border-slate-700 rounded-xl p-3">
                      <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                          <i class="fa-solid fa-receipt text-indigo-400"></i>
                          Historial de Pagos Anteriores de la OC #{{ oc.numero_oc }} ({{ (oc.programaciones || []).length }})
                        </span>
                        <span class="text-xs font-mono text-slate-400">
                          Total acumulado: <strong class="text-emerald-300">${{ formatearNumero(oc.total_programado || 0) }}</strong>
                        </span>
                      </div>

                      <div v-if="!oc.programaciones || oc.programaciones.length === 0" class="text-xs text-slate-500 py-2 italic text-center">
                        No hay pagos programados previamente para esta orden.
                      </div>
                      <div v-else class="space-y-1">
                        <div v-for="(prog, idx) in oc.programaciones" :key="prog.id" 
                             class="flex items-center justify-between p-2 rounded bg-slate-900 border border-slate-700/60 text-xs">
                          <div class="flex items-center gap-2.5">
                            <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-bold flex items-center justify-center">{{ idx + 1 }}</span>
                            <span class="font-mono font-bold text-emerald-300">${{ formatearNumero(prog.monto_programado) }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-500/15 text-indigo-300">{{ prog.rubro }}</span>
                            <span class="text-[10px] font-mono text-cyan-300">{{ prog.semana }}</span>
                            <span class="text-slate-300 text-[11px]">{{ prog.concepto || 'Sin concepto' }}</span>
                          </div>
                          <button v-if="puedeBorrarPagos && esSemanaDeCorteActual(prog.semana)" @click="eliminarAbonoRapido(oc, prog.id)"
                                  class="px-2 py-0.5 bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white rounded text-[10px] font-bold transition">
                            Borrar
                          </button>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================================================= -->
      <!-- SEGUNDA SECCION: GUARDADOS / PROGRAMACIONES DE LA SEMANA (LAYOUT 2 NIVELES TIPO ERP)      -->
      <!-- ========================================================================================= -->
      <div class="mx-2 sm:mx-5 mt-6 bg-slate-800/90 border border-slate-700/80 rounded-2xl shadow-2xl overflow-hidden mb-8 transition-all">
        
        <!-- Banner superior estilo ERP: TOTAL PAGADO -->
        <div class="banner-segunda-tabla bg-gradient-to-r from-slate-900 via-emerald-950/40 to-slate-900 p-4 border-b border-emerald-500/30 flex items-center justify-between flex-wrap gap-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 font-bold text-lg shadow-inner icono-guardado">
              <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-black tracking-widest uppercase px-2.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 badge-guardado">
                  GUARDADO
                </span>
                <span class="text-xs text-slate-400 font-bold uppercase tracking-wider badge-semana">
                  SEMANA: <strong class="text-amber-400 font-mono">{{ semanaCorteMasiva }}</strong>
                </span>
              </div>
              <h3 class="text-base font-black text-white uppercase tracking-tight mt-0.5 titulo-total-pagado flex items-center flex-wrap gap-1.5">
                <span>TOTAL EN GUARDADO:</span>
                <span v-if="filtroVivoObra" class="text-xs font-bold text-amber-400 font-mono px-2 py-0.5 rounded bg-amber-400/10 border border-amber-400/20">OBRA {{ filtroVivoObra }}</span>
                <span class="text-emerald-300 font-mono text-xl ml-1 drop-shadow font-extrabold monto-total-pagado">${{ formatearNumero(totalMontoProgramadoSemana) }}</span>
              </h3>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-slate-300 bg-slate-800/90 border border-slate-700 px-3 py-1.5 rounded-xl shadow-inner badge-contador">
              {{ programacionesSemana.length }} registros en la lista
            </span>
            <button @click="cargarProgramacionesSemana(false)" :disabled="cargandoProgSemana" 
                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-slate-700 cursor-pointer"
                    title="Actualizar lista de programaciones guardadas">
              <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': cargandoProgSemana }"></i>
              <span>Refrescar</span>
            </button>
          </div>
        </div>

        <!-- Tabla de Registros Guardados -->
        <div class="overflow-x-auto max-h-[50vh] overflow-y-auto relative scrollbar-thin scrollbar-thumb-slate-700">
          <table class="w-full text-left border-collapse text-xs min-w-[1300px]">
            <thead class="sticky top-0 z-10 bg-slate-900/95 backdrop-blur-sm shadow-md">
              <tr class="border-b border-slate-700 text-[11px] font-bold text-slate-400 uppercase tracking-wider whitespace-nowrap">
                <th class="py-2.5 px-3 w-16 text-center">OBRA</th>
                <th class="py-2.5 px-3 w-20 text-center">OC</th>
                <th class="py-2.5 px-4">PROVEEDOR / CONTRATISTA</th>
                <th class="py-2.5 px-3 w-36 text-center">TIPO</th>
                <th class="py-2.5 px-3 w-36 text-center">RUBRO</th>
                <th class="py-2.5 px-4">CONCEPTO / ESTIMACIÓN</th>
                <th class="py-2.5 px-3 w-28 text-right font-mono text-emerald-400">IMPORTE ($)</th>
                <th class="py-2.5 px-3 w-28 text-center text-emerald-400">DOCUMENTOS</th>
                <th class="py-2.5 px-3 w-32 text-center">FECHA REGISTRO</th>
                <th
                    class="sticky right-0 z-20 w-20 bg-slate-900 py-2.5 px-2 text-center shadow-[-8px_0_12px_-8px_rgba(0,0,0,0.7)]"
                    title="Acciones">ACCIONES</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-medium">
              <!-- Loading state -->
              <tr v-if="cargandoProgSemana">
                <td colspan="10" class="py-8 text-center text-slate-400">
                  <div class="flex items-center justify-center gap-2">
                    <i class="fa-solid fa-spinner fa-spin text-emerald-400 text-lg"></i>
                    <span>Cargando programaciones guardadas...</span>
                  </div>
                </td>
              </tr>
              <!-- Empty state -->
              <tr v-else-if="programacionesSemana.length === 0">
                <td colspan="10" class="py-8 text-center text-slate-400">
                  <i class="fa-solid fa-folder-open text-2xl text-slate-500 mb-2 block"></i>
                  <span>No hay pagos programados para la semana <strong>{{ semanaCorteMasiva }}</strong><span v-if="filtroVivoObra"> en la obra <strong>{{ filtroVivoObra }}</strong></span>.</span>
                  <div class="text-[11px] text-slate-400 mt-1">Capture importes arriba y pulse "GUARDAR TODO" para guardarlos como pendientes; use el botón de envío de cada fila para mandarlos a BI.</div>
                </td>
              </tr>
              <!-- Filas de programaciones guardadas -->
              <tr v-for="prog in programacionesSemana" :key="prog.id"
                  class="hover:bg-slate-750/70 transition-colors group">
                <td class="py-2 px-3 text-center font-bold text-amber-400">
                  {{ prog.centro_costos }}
                </td>
                <td class="py-2 px-3 text-center font-bold text-white font-mono">
                  {{ prog.numero_oc }}
                </td>
                <td class="py-2 px-4 text-slate-200 truncate max-w-xs" :title="prog.proveedor">
                  {{ prog.proveedor || 'SIN PROVEEDOR' }}
                </td>
                <td class="py-2 px-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black border bg-cyan-950/40 text-cyan-300 border-cyan-500/40">
                    {{ prog.tipo || 'SIN TIPO' }}
                  </span>
                </td>
                <td class="py-2 px-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black border bg-indigo-950/40 text-indigo-300 border-indigo-500/40">
                    {{ prog.rubro || 'SIN RUBRO' }}
                  </span>
                </td>
                <td class="py-2 px-4 text-slate-300 italic truncate max-w-sm" :title="prog.concepto">
                  {{ prog.concepto || '—' }}
                  <span v-if="prog.es_pendiente" class="ml-2 inline-flex items-center rounded bg-amber-500/15 px-1.5 py-0.5 text-[9px] font-black not-italic text-amber-300 border border-amber-500/30">
                    PENDIENTE DE ENVÍO
                  </span>
                </td>
                <td class="monto-programacion-guardada py-2 px-3 text-right font-mono font-extrabold text-emerald-200 drop-shadow-[0_0_4px_rgba(16,185,129,0.35)]">
                  ${{ formatearNumero(prog.monto_programado) }}
                </td>
                <td class="py-2 px-3 text-center whitespace-nowrap">
                  <span v-if="prog.total_documentos > 0"
                        @click="abrirVisorDocumentos(prog)"
                        class="accion-documentos-oc inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black bg-emerald-500/25 text-emerald-300 border border-emerald-500/50 cursor-pointer shadow-sm hover:bg-emerald-500/40 transition"
                        :title="'Ver los ' + prog.total_documentos + ' documentos guardados para esta OC'">
                    <i class="fa-solid fa-circle-check text-emerald-400"></i>
                    <span>ADJUNTADO ({{ prog.total_documentos }})</span>
                  </span>
                  <span v-else-if="documentosTemporalesPorProgramacion[prog.id] > 0"
                        class="accion-documentos-oc inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black bg-emerald-600/35 text-emerald-100 border border-emerald-400/70 shadow-md shadow-emerald-500/20"
                        :title="documentosTemporalesPorProgramacion[prog.id] + ' archivo(s) asignado(s) a este pago en esta sesión; no se enviaron a la base de datos.'">
                    <i class="fa-solid fa-paperclip text-emerald-400"></i>
                    <span>ASIGNADOS ({{ documentosTemporalesPorProgramacion[prog.id] }})</span>
                  </span>
                  <span v-else class="text-[10px] text-slate-500 italic">
                    Sin docs
                  </span>
                </td>
                <td class="py-2 px-3 text-center font-mono text-[10px] text-slate-400 whitespace-nowrap">
                  {{ (prog.creado_en || '').substring(0, 16) }}
                </td>
                <td
                    class="sticky right-0 z-10 bg-slate-800 py-2 px-2 text-center shadow-[-8px_0_12px_-8px_rgba(0,0,0,0.7)] group-hover:bg-slate-750">
                  <div class="flex items-center justify-center gap-1">
                  <button v-if="puedeBorrarPagos" type="button" @click="eliminarAbonoSemana(prog)"
                          class="accion-borrar-programacion inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-500/40 bg-rose-600/20 text-rose-300 transition hover:bg-rose-600 hover:text-white cursor-pointer shadow-sm"
                          :title="prog.es_pendiente ? 'Quitar pago pendiente' : 'Eliminar pago guardado y regresar el saldo a la tabla superior'"
                          :aria-label="prog.es_pendiente ? 'Quitar pago pendiente' : 'Eliminar pago programado'">
                    <i class="fa-solid fa-trash-can text-sm" aria-hidden="true"></i>
                  </button>
                  <button v-if="prog.es_pendiente" type="button" @click="enviarPagoPendiente(prog)"
                          :disabled="enviandoPendienteId !== null"
                          class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-500/40 bg-emerald-600/20 text-emerald-300 transition hover:bg-emerald-600 hover:text-white disabled:cursor-wait disabled:opacity-50 cursor-pointer shadow-sm"
                          title="Enviar y guardar este pago en la base de datos"
                          aria-label="Enviar y guardar pago">
                    <i class="fa-solid" :class="enviandoPendienteId === prog.id ? 'fa-spinner fa-spin' : 'fa-paper-plane'" aria-hidden="true"></i>
                  </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer Totalizador Inferior -->
        <div class="p-3 bg-slate-900 border-t border-slate-700/80 flex items-center justify-between text-xs text-slate-400">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Total programado activo reflejado</span>
          </div>
          <div class="font-bold text-slate-300">
            TOTAL PROGRAMADO: <span class="font-mono text-emerald-400 text-sm font-extrabold ml-1">${{ formatearNumero(totalMontoProgramadoSemana) }}</span>
          </div>
        </div>

      </div>
    </div>

      </div> <!-- Fin de v-else contenido principal -->

    <!-- ==================================================== -->
    
    <!-- ==================================================== -->
    <!-- MODAL PARA ADJUNTAR 2 A 4 DOCUMENTOS A UNA OC       -->
    <!-- ==================================================== -->
    <div v-if="mostrarModalAdjuntarOC" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <div class="modal-adjuntar-card bg-slate-800 border border-slate-700 text-slate-100 rounded-2xl shadow-2xl max-w-lg w-full p-6 transition-all">
        
        <!-- Header -->
        <div class="flex items-start justify-between pb-4 border-b border-slate-700">
          <div>
            <span class="text-[10px] font-mono tracking-widest text-indigo-400 font-bold uppercase">
              GESTIÓN DE EXPEDIENTES OC
            </span>
            <h3 class="text-base font-bold text-white flex items-center gap-2 mt-0.5">
              <i class="fa-solid fa-paperclip text-indigo-400"></i>
              <span>Adjuntar Documentos a OC #{{ ocParaAdjuntar?.numero_oc }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              Obra: <strong class="text-white">{{ ocParaAdjuntar?.centro_costos }}</strong> | Proveedor: <span class="text-slate-300">{{ ocParaAdjuntar?.proveedor }}</span>
            </p>
          </div>
          <button @click="cerrarModalAdjuntarOC" class="text-slate-400 hover:text-white p-1 cursor-pointer">
            <i class="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>

        <!-- Cuerpo del Modal -->
        <div class="py-4 space-y-4 text-xs">
          <div class="bg-indigo-950/40 border border-indigo-500/30 rounded-xl p-3 text-indigo-200">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 font-bold text-indigo-300">
                <i class="fa-solid fa-circle-info"></i>
                <span>Regla de carga de archivos:</span>
              </div>
              <span class="px-2 py-0.5 rounded bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-mono font-bold text-[11px]">
                Pago a Programar: {{ Number(ocParaAdjuntar?.inputMonto) > 0 ? '$' + formatearNumero(ocParaAdjuntar.inputMonto) : 'pendiente' }}
              </span>
            </div>
            <p class="mt-1 text-[11px] text-indigo-200/90 leading-relaxed">
              <span v-if="Number(ocParaAdjuntar?.inputMonto) > 0">Comprobantes para el pago programado de <strong class="text-emerald-300">${{ formatearNumero(ocParaAdjuntar.inputMonto) }}</strong>.</span>
              <span v-else>Prepara los comprobantes ahora y captura el importe de esta OC antes de pulsar <strong>GUARDAR TODO</strong>.</span>
              Selecciona entre <strong>2 a 4 archivos</strong> (Factura PDF, XML, Comprobante, Estimación). Los documentos quedan en pausa por OC; GUARDAR TODO guarda el pago y los documentos localmente. El botón de envío solo lo replica a BI.
            </p>
            <p class="mt-2 text-[11px] text-amber-200">
              Si adjuntas varios XML, la suma de sus totales debe ser menor al importe del pago. No se requiere XML si no adjuntas uno.
            </p>
            <p v-if="!documentosHabilitados" class="mt-1 text-[11px] text-emerald-200">
              El XML se valida en este navegador; los archivos no se envían mientras la carga esté desactivada.
            </p>
          </div>

          <!-- Input File oculto con drag & drop o click -->
          <input type="file" ref="inputModalOCDocs" multiple @change="manejarSeleccionModalDocs" class="hidden"
                 accept=".pdf,.xml,.xlsx,.xls,.docx,.doc,.png,.jpg,.jpeg">

          <div @click="$refs.inputModalOCDocs.click()"
               class="zona-adjuntar-archivos border-2 border-dashed border-slate-600 hover:border-indigo-400 rounded-xl p-6 text-center cursor-pointer transition bg-slate-900/60 hover:bg-slate-900">
            <i class="fa-solid fa-cloud-arrow-up text-3xl text-indigo-400 mb-2 block"></i>
            <span class="font-bold text-slate-200 block text-xs">Haz clic aquí para seleccionar archivos de tu explorador</span>
            <span class="text-[11px] text-slate-500 mt-1 block">Puedes seleccionar varios manteniendo presionada la tecla Ctrl o Shift</span>
          </div>

          <!-- Lista de archivos seleccionados -->
          <div v-if="archivosParaSubirOC.length > 0" class="space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-300">
              <span>Archivos seleccionados ({{ archivosParaSubirOC.length }}):</span>
              <span :class="archivosParaSubirOC.length >= 2 && archivosParaSubirOC.length <= 4 ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold'">
                {{ archivosParaSubirOC.length >= 2 && archivosParaSubirOC.length <= 4 ? 'Cantidad óptima (2 a 4)' : (archivosParaSubirOC.length < 2 ? 'Faltan archivos (mínimo 2)' : 'Máximo 4 permitidos') }}
              </span>
            </div>

            <div class="max-h-40 overflow-y-auto space-y-1.5 pr-1 scrollbar-thin">
              <div v-for="(arch, idx) in archivosParaSubirOC" :key="idx"
                   class="flex items-center justify-between bg-slate-900/90 border border-slate-700/80 rounded-lg p-2 text-[11px]">
                <div class="flex items-center gap-2 truncate max-w-[280px]">
                  <i class="fa-solid fa-file-lines text-indigo-400 text-xs"></i>
                  <span class="truncate font-medium text-slate-200" :title="arch.name">{{ arch.name }}</span>
                  <span class="text-[10px] text-slate-500 font-mono">({{ (arch.size / 1024).toFixed(1) }} KB)</span>
                </div>
                <button type="button" @click="quitarArchivoModal(idx)" class="text-rose-400 hover:text-rose-300 p-1 cursor-pointer" title="Quitar archivo">
                  <i class="fa-solid fa-trash-can text-xs"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-700">
          <button @click="cerrarModalAdjuntarOC" type="button"
                  class="modal-adjuntar-cancelar px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-xl text-xs font-bold transition text-slate-300">
            Cancelar
          </button>
          <button @click="subirArchivosOCSeleccionada" :disabled="subiendoArchivosOC || archivosParaSubirOC.length < 2 || archivosParaSubirOC.length > 4"
                  class="modal-adjuntar-guardar px-5 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 rounded-xl text-xs font-black flex items-center gap-2 shadow-lg shadow-emerald-500/20 transition disabled:opacity-40 disabled:cursor-not-allowed">
            <i class="fa-solid" :class="subiendoArchivosOC ? 'fa-spinner fa-spin' : 'fa-check'"></i>
            <span>{{ subiendoArchivosOC ? 'SUBIENDO...' : 'GUARDAR ARCHIVOS (' + archivosParaSubirOC.length + ')' }}</span>
          </button>
        </div>

      </div>
    </div>

    <!-- ==================================================== -->
    <!-- MODAL VISOR DE DOCUMENTOS ADJUNTOS DE UNA OC        -->
    <!-- ==================================================== -->
    <div v-if="mostrarModalVisorDocs" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <div class="bg-slate-800 border border-slate-700 text-slate-100 rounded-2xl shadow-2xl max-w-lg w-full p-6 transition-all">
        <div class="flex items-start justify-between pb-3 border-b border-slate-700">
          <div>
            <span class="text-[10px] font-mono tracking-widest text-emerald-400 font-bold uppercase">EXPEDIENTE DIGITAL</span>
            <h3 class="text-base font-bold text-white flex items-center gap-2 mt-0.5">
              <i class="fa-solid fa-folder-open text-emerald-400"></i>
              <span>Documentos Adjuntos de OC #{{ ocVisorDocs?.numero_oc }}</span>
            </h3>
            <p class="text-xs text-slate-400">Obra: <strong class="text-white">{{ ocVisorDocs?.centro_costos }}</strong></p>
          </div>
          <button @click="mostrarModalVisorDocs = false" class="text-slate-400 hover:text-white p-1 cursor-pointer">
            <i class="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>

        <div class="py-4 space-y-2 max-h-64 overflow-y-auto">
          <div v-if="!ocVisorDocs?.documentos || ocVisorDocs?.documentos.length === 0" class="text-center py-6 text-slate-400">
            <i class="fa-solid fa-file-circle-exclamation text-2xl mb-1 text-slate-500 block"></i>
            <span>No hay documentos disponibles para esta orden.</span>
          </div>
          <div v-else v-for="doc in ocVisorDocs.documentos" :key="doc.id"
               class="flex items-center justify-between p-2.5 bg-slate-900 border border-slate-700/80 rounded-xl hover:border-slate-600 transition">
            <div class="flex items-center gap-2.5 truncate max-w-[320px]">
              <i class="fa-solid fa-file-pdf text-rose-400 text-sm" v-if="doc.nombre_original.toLowerCase().endsWith('.pdf')"></i>
              <i class="fa-solid fa-file-code text-amber-400 text-sm" v-else-if="doc.nombre_original.toLowerCase().endsWith('.xml')"></i>
              <i class="fa-solid fa-file-excel text-emerald-400 text-sm" v-else-if="doc.nombre_original.toLowerCase().includes('.xls')"></i>
              <i class="fa-solid fa-file text-indigo-400 text-sm" v-else></i>
              <div class="truncate">
                <div class="text-xs font-bold text-slate-200 truncate" :title="doc.nombre_original">{{ doc.nombre_original }}</div>
                <div class="text-[10px] text-slate-500 font-mono">{{ (doc.tamano_bytes / 1024).toFixed(1) }} KB | {{ doc.creado_en }}</div>
              </div>
            </div>
            <a :href="'/api/descargar-archivo-oc/' + doc.id" download
               class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-emerald-300 hover:text-white border border-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-sm">
              <i class="fa-solid fa-download text-xs"></i>
              <span>Descargar</span>
            </a>
          </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-700">
          <button @click="mostrarModalVisorDocs = false" class="px-4 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-xl text-xs font-bold transition">
            Cerrar
          </button>
        </div>
      </div>
    </div>
<!-- MODAL DE PROGRAMACIÓN SEMANAL DE PAGOS               -->
    <!-- ==================================================== -->
    <div v-if="mostrarModalProgramar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      <div class="bg-slate-800 border border-slate-700 text-slate-100 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[92vh] overflow-y-auto p-6 transition-all duration-300">
        
        <!-- Header del Modal -->
        <div class="flex items-start justify-between pb-4 border-b border-slate-700/80">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
              <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white tracking-tight">Programación Semanal de Pago</h3>
              <p class="text-xs text-slate-400">Asignación de Rubro, Saldo Respaldado y Monto Programado</p>
            </div>
          </div>
          <button @click="cerrarModalProgramar" class="text-slate-400 hover:text-white transition p-1 cursor-pointer">
            <i class="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>

        <!-- Formulario -->
        <form @submit.prevent="guardarProgramacionPago" class="space-y-4 pt-4">
          
          <!-- Selector Tipo de Documento -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-semibold text-slate-300">
                Tipo de Documento:
              </label>
              <span v-if="esProgramacionDesdeOC" class="text-[10px] text-amber-400 font-semibold flex items-center gap-1 bg-amber-400/10 px-2 py-0.5 rounded border border-amber-400/20">
                <i class="fa-solid fa-lock text-[9px]"></i> Documento de Enkontrol (Fijo)
              </span>
            </div>
            <!-- Si viene de Acción de una OC: Fijo -->
            <div v-if="esProgramacionDesdeOC" class="flex items-center gap-2">
              <span class="py-1.5 px-3 rounded-lg text-xs font-extrabold bg-indigo-600/30 text-indigo-300 border border-indigo-500/40 inline-flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-file-invoice text-indigo-400 text-xs"></i>
                <span>ORDEN DE COMPRA (OC)</span>
              </span>
            </div>
            <!-- Si es Manual: Selector libre -->
            <div v-else class="grid grid-cols-4 gap-2">
              <button type="button" v-for="tipo in ['OC', 'ESTIMACIÓN', 'REQUISICIÓN', 'FACTURA']" :key="tipo"
                      @click="formProgramar.tipo = tipo"
                      class="py-1.5 px-2 rounded-lg text-xs font-bold border transition text-center cursor-pointer"
                      :class="formProgramar.tipo === tipo ? 'bg-indigo-600 text-white border-indigo-500 shadow-md shadow-indigo-600/30' : 'bg-slate-900 text-slate-400 border-slate-700 hover:text-white'">
                {{ tipo }}
              </button>
            </div>
          </div>

          <!-- Fila 1: Folio y Semana de Corte -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-300">
                  # Folio Documento:
                </label>
                <span v-if="esProgramacionDesdeOC" class="text-[9px] text-slate-400 flex items-center gap-1 font-mono">
                  <i class="fa-solid fa-lock text-[8px] text-amber-400"></i> Fijo
                </span>
              </div>
              <div class="relative">
                <i class="fa-solid fa-hashtag absolute left-3 top-2.5 text-slate-500 text-xs"></i>
                <input type="text" v-model="formProgramar.folio" required
                       :readonly="esProgramacionDesdeOC"
                       placeholder="Ej. 1045 o EK10-1045"
                       class="w-full rounded-lg pl-8 pr-3 py-2 text-xs font-mono font-bold focus:outline-none transition"
                       :class="esProgramacionDesdeOC ? 'bg-slate-900/60 border border-slate-700/60 text-indigo-300 cursor-not-allowed opacity-90 shadow-inner' : 'bg-slate-900 border border-slate-700 text-slate-100 focus:border-indigo-500'">
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">
                Semana de Pago / Corte:
              </label>
              <div class="relative">
                <select v-model="formProgramar.semana" required
                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500 cursor-pointer">
                  <option v-if="semanaActualOficial" :value="semanaActualOficial.etiqueta">
                    {{ semanaActualOficial.etiqueta }} (En Curso)
                  </option>
                  <option v-for="s in listaSemanas" :key="s" :value="s">
                    {{ s }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Fila 2: Obra y Proveedor -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-300">
                  Obra / Centro de Costos:
                </label>
                <span v-if="esProgramacionDesdeOC" class="text-[9px] text-slate-400 flex items-center gap-1 font-mono">
                  <i class="fa-solid fa-lock text-[8px] text-amber-400"></i> Fijo
                </span>
              </div>
              <input type="text" v-model="formProgramar.obra" required
                     :readonly="esProgramacionDesdeOC"
                     placeholder="Ej. OBRA 102 o Lomas"
                     class="w-full rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none transition"
                     :class="esProgramacionDesdeOC ? 'bg-slate-900/60 border border-slate-700/60 text-amber-300 font-mono cursor-not-allowed opacity-90 shadow-inner' : 'bg-slate-900 border border-slate-700 text-slate-100 focus:border-indigo-500'">
            </div>

            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-300">
                  Proveedor / Contratista:
                </label>
                <span v-if="esProgramacionDesdeOC" class="text-[9px] text-slate-400 flex items-center gap-1 font-mono">
                  <i class="fa-solid fa-lock text-[8px] text-amber-400"></i> Fijo
                </span>
              </div>
              <input type="text" v-model="formProgramar.proveedor" required
                     :readonly="esProgramacionDesdeOC"
                     placeholder="Ej. CEMEX, CONCRETOS..."
                     class="w-full rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none transition"
                     :class="esProgramacionDesdeOC ? 'bg-slate-900/60 border border-slate-700/60 text-slate-300 cursor-not-allowed opacity-90 shadow-inner' : 'bg-slate-900 border border-slate-700 text-slate-100 focus:border-indigo-500'">
            </div>
          </div>

          <!-- Fila 3: Rubro Presupuestal -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">
              Rubro de Operación:
            </label>
            <div class="relative">
              <select v-model="formProgramar.rubro" required
                      class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500 cursor-pointer">
                <option value="" class="bg-slate-900 text-slate-400">- Seleccionar Rubro -</option>
                <option value="(1) MATERIALES" class="bg-slate-900 text-white">(1) MATERIALES</option>
                <option value="(2) MANO DE OBRA" class="bg-slate-900 text-white">(2) MANO DE OBRA</option>
                <option value="(4) SUB CONTRATOS" class="bg-slate-900 text-white">(4) SUB CONTRATOS</option>
                <option value="(7) URBANIZACIÓN" class="bg-slate-900 text-white">(7) URBANIZACIÓN</option></select>
            </div>
          </div>

          <!-- BLOQUE FINANCIERO: SALDO RESPALDADO, PAGANDO Y DIFERENCIA (DIBUJO DEL USUARIO) -->
          <div class="p-4 bg-slate-900/90 border border-slate-700/80 rounded-xl space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Desglose Financiero de Pago</span>
              <button type="button" @click="formProgramar.pagando = formProgramar.importe"
                      class="text-[11px] text-emerald-400 hover:text-emerald-300 font-bold underline cursor-pointer">
                Pagar Total (100%)
              </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <!-- Saldo Respaldado / Importe Total (Fijo de Enkontrol si viene de una OC) -->
              <div class="bg-slate-800/80 p-3 rounded-lg border border-slate-700 relative">
                <div class="flex items-center justify-between mb-1">
                  <label class="block text-[11px] font-semibold text-slate-400">
                    Importe Respaldado ($)
                  </label>
                  <span v-if="esProgramacionDesdeOC" class="text-[9px] text-amber-400 font-bold flex items-center gap-1" title="Techo oficial autorizado en Enkontrol">
                    <i class="fa-solid fa-lock text-[8px]"></i> Fijo Enkontrol
                  </span>
                </div>
                <input type="number" step="0.01" min="0" v-model.number="formProgramar.importe" required
                       :readonly="esProgramacionDesdeOC"
                       class="w-full rounded px-2.5 py-1.5 text-sm font-mono font-bold focus:outline-none transition"
                       :class="esProgramacionDesdeOC ? 'bg-slate-900/60 border border-slate-700/60 text-slate-200 cursor-not-allowed opacity-90 shadow-inner' : 'bg-slate-900 border border-slate-700 text-white focus:border-indigo-500'">
                <span v-if="esProgramacionDesdeOC" class="text-[9px] text-slate-500 mt-1 block">
                  Techo oficial Enkontrol
                </span>
              </div>

              <!-- Pagando / Monto Solicitado esta semana -->
              <div class="bg-slate-800/80 p-3 rounded-lg border border-emerald-500/40">
                <label class="block text-[11px] font-semibold text-emerald-400 mb-1">
                  Importe a Programar de la Obra ($)
                </label>
                <input type="number" step="0.01" min="0.01" v-model.number="formProgramar.pagando" required
                       class="w-full bg-slate-900 border border-emerald-500 rounded px-2.5 py-1.5 text-sm font-mono font-bold text-emerald-300 focus:outline-none focus:ring-1 focus:ring-emerald-500">
              </div>

              <!-- Diferencia / Saldo Pendiente (Cálculo Dinámico) -->
              <div class="bg-slate-800/80 p-3 rounded-lg border"
                   :class="calculoDiferencia <= 0.01 ? 'border-emerald-500/40 bg-emerald-950/20' : 'border-amber-500/40 bg-amber-950/20'">
                <label class="block text-[11px] font-semibold mb-1"
                       :class="calculoDiferencia <= 0.01 ? 'text-emerald-400' : 'text-amber-400'">
                  Diferencia / Por Pagar
                </label>
                <div class="text-sm font-mono font-extrabold py-1.5"
                     :class="calculoDiferencia <= 0.01 ? 'text-emerald-300' : 'text-amber-300'">
                  ${{ formatearNumero(calculoDiferencia) }}
                </div>
                <span class="text-[10px] block"
                      :class="calculoDiferencia <= 0.01 ? 'text-emerald-400/80' : 'text-amber-400/80'">
                  {{ calculoDiferencia <= 0.01 ? 'Liquidación total' : 'Saldo remanente' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Concepto / Justificación -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">
              Concepto / Justificación:
            </label>
            <input type="text" v-model="formProgramar.concepto"
                   placeholder="Ej. Estimación 3, Anticipo 20%, Finiquito de colado..."
                   class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-indigo-500">
          </div>

          <!-- SECCIÓN: ADJUNTAR DOCUMENTOS (OC, REQUISICIONES, FACTURAS, ETC.) -->
          <div class="p-3.5 bg-slate-900/90 border border-slate-700/80 rounded-xl space-y-2.5">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-bold text-slate-300 flex items-center gap-1.5">
                <i class="fa-solid fa-paperclip text-emerald-400"></i>
                <span>Documentos de Respaldo:</span>
                <span class="text-[10px] font-normal text-slate-400">(OC, Requisiciones, Facturas, etc.)</span>
              </label>
              <span class="text-[10px] text-slate-400 font-medium">
                {{ archivosAdjuntos.length }} archivo(s) seleccionado(s)
              </span>
            </div>
            <p v-if="!documentosHabilitados" class="text-[11px] font-semibold text-amber-300">
              Modo de pruebas: los documentos no se enviarán ni guardarán.
            </p>

            <!-- Botones de Acción de Archivos -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
              <input type="file" ref="inputArchivos" @change="manejarSeleccionArchivos" multiple class="hidden"
                     accept=".pdf,.xml,.xlsx,.xls,.docx,.doc,.png,.jpg,.jpeg">
              
              <button type="button" @click="$refs.inputArchivos.click()"
                      class="px-3.5 py-2 bg-indigo-600/20 hover:bg-indigo-600 border border-indigo-500/40 hover:border-indigo-500 text-indigo-300 hover:text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                <i class="fa-solid fa-folder-open text-xs"></i>
                <span>Adjuntar Documentos</span>
              </button>

              <button v-if="documentosHabilitados && archivosAdjuntos.length > 0 && !archivosSubidosExito" type="button" @click="subirArchivosServidor"
                      :disabled="cargandoArchivos"
                      class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer shadow-sm disabled:opacity-40">
                <i class="fa-solid" :class="cargandoArchivos ? 'fa-spinner fa-spin' : 'fa-cloud-arrow-up'"></i>
                <span>{{ cargandoArchivos ? 'Cargando en documentos_ejemplo...' : 'Cargar Archivos Seleccionados' }}</span>
              </button>

              <span v-if="archivosSubidosExito" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">
                <i class="fa-solid fa-circle-check"></i>
                <span>{{ mensajeCargaArchivos || 'Archivos guardados en documentos_ejemplo' }}</span>
              </span>
            </div>

            <!-- Lista / Tarjetas de archivos seleccionados -->
            <div v-if="archivosAdjuntos.length > 0" class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
              <div v-for="(item, idx) in archivosAdjuntos" :key="idx"
                   class="flex items-center justify-between px-3 py-1.5 bg-slate-800/90 border border-slate-700 rounded-lg text-xs">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                  <i :class="obtenerIconoArchivo(item.file.name)" class="text-sm shrink-0"></i>
                  <span class="text-slate-200 truncate font-mono text-[11px]">{{ item.file.name }}</span>
                  <span class="text-[10px] text-slate-400 shrink-0">({{ formatearTamanoArchivo(item.file.size) }})</span>
                </div>
                <div class="flex items-center gap-2 shrink-0 ml-2">
                  <span v-if="item.subido" class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                    <i class="fa-solid fa-check"></i> Guardado
                  </span>
                  <button type="button" @click="removerArchivoAdjunto(idx)"
                          class="text-slate-400 hover:text-rose-400 transition p-1" title="Eliminar archivo">
                    <i class="fa-solid fa-xmark text-xs"></i>
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="text-[11px] text-slate-500 italic py-2 text-center border border-dashed border-slate-700/60 rounded-lg">
              Puedes adjuntar múltiples documentos (OC, Requisiciones, Facturas, Estimaciones, etc.)
            </div>
          </div>

          <!-- Ficha de Usuario Emisor -->
          <div class="flex items-center justify-between p-3 bg-slate-900/60 rounded-lg border border-slate-700/60 text-xs text-slate-400">
            <div class="flex items-center gap-2">
              <i class="fa-solid fa-user-check text-indigo-400"></i>
              <span>Solicitante: <strong class="text-slate-200">{{ usuarioActivo }}</strong></span>
            </div>
            <div>
              <span>Plaza: <strong class="text-slate-200">{{ perfilUsuario?.plaza || 'Sin asignar' }}</strong></span>
              <span class="ml-2">Empresa: <strong class="text-slate-200">{{ empresaActiva }}</strong></span>
            </div>
          </div>

          <!-- Botones de Acción -->
          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-700/80">
            <button type="button" @click="cerrarModalProgramar"
                    class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer">
              Cancelar
            </button>
            <button type="submit" :disabled="guardandoProgramacion || formProgramar.pagando <= 0"
                    class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-lg shadow-emerald-600/30 transition cursor-pointer">
              <i class="fa-solid" :class="guardandoProgramacion ? 'fa-spinner fa-spin' : 'fa-check'"></i>
              <span>{{ guardandoProgramacion ? 'Guardando...' : 'Confirmar y Programar Pago' }}</span>
            </button>
          </div>

        </form>

      </div>
    </div>

  </div> <!-- Fin de #app -->
@endverbatim


  <!-- Configuración Inyectada desde el Servidor Flask -->
  <script>
    window.__APP_CONFIG__ = {
      documentosHabilitados: {!! json_encode($documentos_habilitados ?? true) !!},
      puedeBorrarPagosSesion: {!! json_encode($puede_borrar_pagos_sesion ?? false) !!}
    };
  </script>
  <!-- Aplicación Vue 3 Modular -->
  <script src="{{ asset('js/app.js') }}?v=2.0"></script>
</body>
</html>
