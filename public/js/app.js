    const { createApp } = Vue;

    createApp({
      data() {
        return {
          baseUrl: (window.__APP_CONFIG__ && window.__APP_CONFIG__.baseUrl) ? window.__APP_CONFIG__.baseUrl.replace(/\/$/, '') : '',
          documentosHabilitados: (window.__APP_CONFIG__ && window.__APP_CONFIG__.documentosHabilitados) !== undefined ? window.__APP_CONFIG__.documentosHabilitados : false,
          puedeBorrarPagosSesion: (window.__APP_CONFIG__ && window.__APP_CONFIG__.puedeBorrarPagosSesion) !== undefined ? window.__APP_CONFIG__.puedeBorrarPagosSesion : false,
          loginEmpresa: localStorage.getItem('empresa_activa_oc') || 'ARYVE',
          empresaActiva: localStorage.getItem('empresa_activa_oc') || 'ARYVE',
          usuarioActivo: localStorage.getItem('usuario_activo_oc') || '',
          perfilUsuario: JSON.parse(localStorage.getItem('perfil_activo_oc') || 'null'),
          loginUsuario: '',
          loginPassword: '',
          errorLogin: '',
          programacionesSemana: [],
          enviandoPendienteId: null,
          totalMontoProgramadoSemana: 0,
          cargandoProgSemana: false,
          cargandoLogin: false,
          mostrarToastError: false,
          toastMensaje: '',
          shakeCard: false,
          toastTimer: null,

          limiteFilas: 15,
          registros: [],
          cargando: false,
          paginaActual: 1,
          totalPaginas: 1,
          totalFiltrado: 0,
          sumaImporte: 0,
          sumaPagando: 0,
          sumaDiferencia: 0,
          kpisGlobales: { total_registros: 0 },
          
          filtroBusqueda: '',
          filtroResponsable: '',
          filtroPlaza: '',
          filtroFraccionamiento: '',
          filtroRubro: '',
          filtroSemana: '',

          listaResponsables: [],
          listaPlazas: [],
          listaFraccionamientos: [],
          listaRubros: [],
          listaSemanas: [],
          semanaActualOficial: null,

          // Notificación de éxito
          mostrarToastExito: false,
          toastExitoMensaje: '',

          // Modal de Programación de Pago
          mostrarModalProgramar: false,
          guardandoProgramacion: false,
          esProgramacionDesdeOC: false,
          formProgramar: {
            tipo: 'OC',
            folio: '',
            obra: '',
            proveedor: '',
            rubro: '',
            importe: 0,
            pagando: 0,
            diferencia: 0,
            semana: '',
            concepto: ''
          },
          archivosAdjuntos: [],
          cargandoArchivos: false,
          archivosSubidosExito: false,
          mensajeCargaArchivos: '',

          // Navegación de pestañas (Inicia en ÓRDENES DE COMPRA al ingresar)
          vistaActiva: 'ORDENES_VIVO',

          // Estado reactivo para Órdenes de Compra
          ordenesVivo: [],
          cargandoVivo: false,
          kpisVivo: {
            ordenes_hoy: 0,
            monto_hoy: 0,
            total_acumulado: 0,
            monto_acumulado: 0
          },
          filtroVivoTemporal: '',
          filtroVivoSemana: '',
          filtroVivoMisOrdenes: true,
          filtroVivoBusqueda: '',
          paginaVivo: 1,
          totalPaginasVivo: 1,
          totalFiltradoVivo: 0,
          sumaTotalVivo: 0,
          ocExpandidaId: null,
          guardandoAbonoRapido: false,
          formAbonoRapido: {
            monto: 0,
            rubro: '',
            concepto: '',
            semana: ''
          },
          // EDICIÓN EN LOTE MASIVA
          filtroVivoObra: '',
          mostrarSugerenciasObra: false,
          catalogoObrasVivo: [],
          catalogoRubrosCompleto: ["01-AREAS COMUNES", "ACABADO", "ACABADO-CUBIERTAS DE MARMOL", "ACABADOS", "ACABADOS - CUBIERTAS DE MARMOL", "ACABADOS YESO", "ACABADOS- CUBIERTAS DE MARMOL", "ACABADOS- HERRERIA", "ACABADOS- INFRAESTRUCTURA", "ACABADOS- YESO", "ACABADOS-AZULEJO", "ACABADOS-CUBIERTAS DE MARMOL", "ACABADOS-ENTORTADO Y EMPASTADOS DE PATIOS", "ACABADOS-ESTUCO", "ACABADOS-HERRERIA", "ACABADOS-HERRERIAS", "ACABADOS-HERRERÍA", "ACABADOS-INFRAESTRUCTURA", "ACABADOS-INSTALACIONES", "ACABADOS-PIEDRA", "ACABADOS-PISO Y ZOCLO", "ACABADOS-YESO", "ACARREOS", "ACC ELECTRICOS", "ACC HIDROSANITARIOS", "ACCESORIOS", "ACCESORIOS DE BAÑO", "ACCESORIOS ELECTRICOS", "ACCESORIOS HIDROSANITARIO", "ACCESORIOS HIDROSANITARIOS", "ACCESORIOS HIDROSANITARIOS, LUMINARIAS", "ACER0", "ACERO", "ACERO Y PLOMERIA", "ACEROS", "ACESORIOS ELECTRICOS", "ADHESIVO", "AIRE ACONDICIONADO", "AIRES ACONDICIONADOS", "AJUSTE", "ALAMBRES, CADENAS, VARILLAS", "ALBANILERIA-IMPERMEABILIZACION", "ALBAÑILERIA-BARDAS", "ALBAÑILERIA-CIMBRADOS", "ALBAÑILERIA-ESCALERA", "ALBAÑILERIA-IMPERMEABILIZACION", "ALBAÑILERIA-IMPERMEABILIZANTE", "ALBAÑILERIA-INTERIOR", "ALBAÑILERIA-MUROS", "ALBAÑILERÍA - IMPERMEABILIZACIÓN", "ALBAÑILERÍA - IMPERMEABILZACIÓN", "ALBAÑILERÍA-CADENAS DE CERRAMIENTO", "ALBAÑILERÍA-CASTILLOS", "ALBAÑILERÍA-ENRASES", "ALBAÑILERÍA-MOCHETAS", "ALBAÑILERÍA-MUROS", "ALBAÑILERÍA-PERFILES", "ALBAÑILERÍA-POYOS", "ALBAÑILERÍA-REPELLO CON MORTERO", "ALBAÑILERÍA-SARDINEL", "ALBAÑILES", "ALBAŃILERIA-CIMBRADOS", "ALBERCA", "ALUMINIOS", "AMORTIZACIONES A CONTRATISTAS", "ANDAMIAJE", "ANDAMIOS", "APLANADOS", "AREA VERDE", "AREAS VERDES", "AREAS VERDES, ACCESOS Y CASA CLUB", "ARMADOS", "BAJANTES PLUVIALES", "BARANDALES", "BARANDALES CRISTAL TEMPLADO", "BARDAS PERIMETRALES", "BARROTE", "BISAGRAS", "BLANCO ESPAÑA", "BLOCK", "BLOCKEROS", "BOMBA", "BOMBAS", "BOQUILLA", "BOVEDILLA", "CABLE", "CABLE DE COBRE", "CABLES", "CALVO Y MADERA", "CANCELERIA", "CANCELERIA - BARANDALES PARA ESCALERA", "CANCELERIA Y VENTANAS", "CANCELERIA- PUERTAS MULTIPANEL", "CANCELERIA- VENTANAS", "CANCELERIA-PUERTAS MULTIPANEL", "CANCELERIA-VENTANAS", "CANCELERÍA", "CANCELERÍA-PUERTAS CORREDIZAS DE VIDRIO TEMPLADO", "CANCELERÍA-PUERTAS MULTIPANEL", "CANCELERÍA-VENTANAS", "CANCELERÍA-VIDRIO TEMPLADO PARA BAÑOS", "CANCELERÍA-VIDRIO TEMPLADO PARA TERRAZAS", "CANCELES", "CANCELES DE BAÑO", "CANTERA", "CANTERAS, PIEDRAS, PLACAS Y CUBIERTAS", "CAPINTERIAS", "CARPINTEIRA- CLOSETS", "CARPINTERIA", "CARPINTERIA - PUERTAS", "CARPINTERIA -PUERTAS", "CARPINTERIA- COCINAS", "CARPINTERIA- PUERTAS", "CARPINTERIA- PURTAS", "CARPINTERIA-PUERTAS", "CARPINTERIAS", "CARPINTEROS", "CARPINTERÍA-CLOSETS", "CARPINTERÍA-PUERTAS", "CARPINTERÍA-TEMPLADO PARA BAÑOS", "CELOTEX", "CEMENMTANTES", "CEMENTANTE", "CEMENTANTES", "CEMENTO GRIS", "CEMENTO Y CASTILLOS", "CERRADURAS Y CERROJOS", "CIMBRA METALICA", "CIMENTACION-LOSA", "CIMENTACIÓN-LOSA DE CIMENTACIÓN", "CIMENTACIÓN-MUROS DE CONTENCIÓN", "CISTERNA", "CISTERNAS", "CLAVO", "CLAVO Y MADERA", "CLIMA", "COCINAS", "COLADOS", "COMBUSTIBLE", "CONCRETO", "CONCRETOS", "CONEXIONES HIDROSANITARIAS", "CONEXIÓN DE GAS", "CONSUMIBLES", "CONSUMIBLES VARIOS", "CONTENEDORES", "CONTRA INCENDIO", "CUARZO", "CUBIERTAS", "CUBIERTAS DE MARMOL", "CUBIERTAS-MARMOL", "CUÑAS Y CLIPS", "DESTAJO INFONAVIT", "DESTAJO RESIDENCIAL", "DESTAJOS VELAMAR", "DIESEL", "DISEÑO-FACHADAS", "DISPONIBLE/NO USAR", "DUCTOS", "ECOTECNOLOGIAS", "ELECTRICO", "ELECTRICO (CABLES,TUBERIA,CONEXIONES,VOZ Y DATOS)", "ELECTRICO Y PLOMERIA", "ELECTRIFICACIÓN", "ELECTRODOMESTICOS", "ELEVADOR", "ELEVADORES", "EPP", "EQ PROTECCION", "EQ SEGURIDAD", "EQUIPAMIENTO", "EQUIPO", "EQUIPO DE ALBERCA", "EQUIPO DE PROTECCION", "EQUIPO DE PROTECCION PERSONAL", "EQUIPO DE SEGURIDAD", "EQUIPO MENOR", "EQUIPO Y MUEBLE", "EQUIPO Y MUEBLES", "EQUIPOS", "EQUIPOS Y MUEBLES", "ESCANTILLONES", "ESPEJOS", "ESTAMPADO", "ESTRUCTURA-COLUMNAS", "ESTRUCTURA-LOSA DE AZOTEA", "ESTRUCTURA-LOSAS DE ENTREPISO", "ESTRUCTURA-TRABES", "ESTUCO", "ESTUQUEROS", "FACHADA", "FIERREROS", "FIJOS", "FLETE", "FLETE ARENA", "FLETE MEDRANO", "FLETES", "FONDO DE GARANTIA", "FONDO DE GARANTÍA", "GARGOLA", "GAS TUBERIA Y EQUIPO", "GAS, TUBERIA Y EQUIPO", "GASOLINA", "GASOLINAS Y ACEITES", "GASTO DE OBRA", "GASTO DE OBRA - RENTAS", "GASTO DE OBRA-RENTAS", "GASTOS DE LABORATORIO", "GASTOS DE OBRA", "GASTOS DE OBRA  - ANDAMIOS", "GASTOS DE OBRA -  FUMIGACION", "GASTOS DE OBRA - ANDAMIOS", "GASTOS DE OBRA - DESASOLVE", "GASTOS DE OBRA - DESAZOLVE", "GASTOS DE OBRA - DESAZOLVES", "GASTOS DE OBRA - DIESEL", "GASTOS DE OBRA - FUMIGACION", "GASTOS DE OBRA - FUMIGACIONES", "GASTOS DE OBRA - FUMIGACIÓN", "GASTOS DE OBRA - GENERADORES", "GASTOS DE OBRA - LABORATORIO", "GASTOS DE OBRA - LABORATORIOS", "GASTOS DE OBRA - MECANICA DE SUELOS", "GASTOS DE OBRA - RENTAS", "GASTOS DE OBRA - SANITARIO", "GASTOS DE OBRA - SANITARIOS", "GASTOS DE OBRA - SEGURIDAD PRIVADA", "GASTOS DE OBRA - SUMINISTRO AGUA", "GASTOS DE OBRA - SUMINISTRO DE AGUA", "GASTOS DE OBRA - SUMINISTROS", "GASTOS DE OBRA - SUMINISTROS AGUA", "GASTOS DE OBRA - SUMINISTROS DE AGUA", "GASTOS DE OBRA - SUMINISTROS DIESEL", "GASTOS DE OBRA - SUMINISTROS PIPAS", "GASTOS DE OBRA - TOPOGRAFIA", "GASTOS DE OBRA -RENTAS", "GASTOS DE OBRA . RENTAS", "GASTOS DE OBRA – LABORATORIOS", "GASTOS DE OBRA(SEGURIDAD PRIVADA, LABORATORIOS, PIPAS, ETC..", "GASTOS DE OBRA- ANDAMIOS", "GASTOS DE OBRA- FUMIGACION", "GASTOS DE OBRA- LABORATORIOS", "GASTOS DE OBRA- PIPAS", "GASTOS DE OBRA- RENTAS", "GASTOS DE OBRA- SANITARIOS", "GASTOS DE OBRA- SUMINISTRO AGUA", "GASTOS DE OBRA-ACABADOS", "GASTOS DE OBRA-AGUA PURIFICADA", "GASTOS DE OBRA-ANDAMIOS", "GASTOS DE OBRA-CANCELERIA", "GASTOS DE OBRA-FUMIGACION", "GASTOS DE OBRA-FUMIGACIONES", "GASTOS DE OBRA-FUMIGACIÓN", "GASTOS DE OBRA-PIPA", "GASTOS DE OBRA-PIPAS", "GASTOS DE OBRA-RENTAS", "GASTOS DE OBRA-SANITARIOS", "GASTOS DE OBRA-SUMINISTRO AGUA", "GASTOS DE OBRA-SUMINISTROS DE AGUA", "GASTOS DE OBRA-SUMINSITROS PIPA", "GASTOS DE ORBA - SUMINISTROS PIPAS", "GASTOS-RENTAS", "GIMNASIO", "GRAVA", "GRIFERIA", "GRIFERIA, LAVABOS Y SANITARIOS", "HERAMIENTA MENOR", "HERRAJES", "HERRAMIENTA", "HERRAMIENTA MENOR", "HERRAMIENTA MENOR, CONSUMIBLES Y EQ DE SEGURIDAD", "HERRAMIENTA MENOR, CONSUMIBLES Y EQ DE SEGURIDAD.", "HERRAMIENTA MENOR, CONSUMIBLES, Y EQ DE SEGURIDAD", "HERRAMIENTA Y CONSUMIBLES", "HERREIA", "HERRERIA", "HERRERIAS", "HERRERÍA", "HERRERÍAS", "HIDROSANITARIO Y GAS", "HTA MENOR", "ILUMINACION", "ILUMINASION", "IMPERMEABILIZANTE", "IMPERMEABILIZANTES", "INDIRECTO", "INDIRECTOS", "INDIRECTOS CPV", "INDIRECTOS MO", "INDIRECTOS-MAT", "INDIRECTOS-SUB", "INSTALACION DE TECHUMBRE", "INSTALACION ELECTRICA", "INSTALACION GAS", "INSTALACION HIDROSANITARIA", "INSTALACION MINISPLIT", "INSTALACIONES ELECTRICAS", "INSTALACIONES ESPECIALES", "INSTALACIONES-ELECTRICA ELECTRICO", "INSTALACIONES-ELECTRICAS", "INSTALACIONES-ELECTRICAS ELECTRICO", "INSTALACIONES-HIDRAULICAS", "INSTALACIONES-HIDRAULICAS PLOMERO", "INSTALACIONES-HIDROSANITARIAS Y PLUVIALES PLOMER", "INSTALACIONES-HIDROSANITARIAS Y PLUVIALES PLOMERO", "INSTALACIONES-HIROSANITARIAS Y PLUVIALES PLOMERO", "INSTALACIONES-PLOMERIA PLOMERO", "INSTALACIONES-PLOMERO", "INSTALACON HIDROSANITARIO", "INTALACIONES", "JARDINERIA", "JARDINERÍA", "JUAREZ", "LABORATORIO", "LABORATORIOS", "LAMBRINES", "LAMPARA", "LAVADEROS", "LUMINARIAS", "MA", "MADERA", "MADERA Y CLAVO", "MADERA Y CLAVOS", "MADERA, MATERIALES VARIOS", "MADERAS Y CLAVO", "MADERAS Y CLAVOS", "MADERAS Y HERRAJES", "MALLA", "MALLA FIBRA DE VIDRIO", "MALLA PLAFONERA", "MALLAS", "MANO DE OBRA", "MANO DE OBRA CIVIL", "MANO DE OBRA ESPECIALIZADA", "MAQUINARA", "MAQUINARIA", "MARCO", "MARCO Y CONTRA MARCO", "MARCOS", "MARCOS Y CHAMBRANAS", "MARCOS Y PUERTAS", "MARMOL", "MAT BANCO", "MAT DE BANCO", "MAT ELECTRICO", "MAT VARIOS", "MAT. ELÉCTRICO", "MATERAL DE BANCO", "MATERIAL", "MATERIAL DE BANCO", "MATERIAL DE GAS", "MATERIAL DE LIMPIEZA", "MATERIAL ELECTRICO", "MATERIAL ELECTRICO(CABLES,TUBERIA,CONEXIONES,VOZYD", "MATERIAL HIDROSANITARIO", "MATERIAL HIDROSANITARIO Y GAS", "MATERIAL HIDROSANITARIOS", "MATERIAL OBRA CIVIL", "MATERIAL PINTURA", "MATERIALES", "MATERIALES ELECTRICOS", "MATERIALES OBRA CIVIL", "MATERIALES VARIOS", "MATERILAES VARIOS", "MATETIALES VARIOS", "MEDRANO", "METERIAL ELCTRICO", "METERIAL ELECTRICO", "MOLDEROS", "MOLDES", "MUEBLES", "MUEBLES - COCINA INTEGRAL", "MUEBLES-COCINA INTEGRAL", "MUEBLES-DE BAÑOS", "MUEBLES-GRIFERIAS", "MUEBLES-VANITYS", "MUROS Y CANTERAS", "NSTALACIONES-ELECTRICAS ELECTRICO", "NUMEROS OFICIALES", "OBRA EXTERIOR", "PARRILLA", "PASTO", "PASTO EN ROLLO", "PEGAMENTOS", "PEGAMENTOS Y SOLVENTES", "PERGOLADO", "PIJAS Y TAQUETES", "PIJAS Y TORNILLOS", "PIJAS, TAQUETES", "PIJAS, TORNILLOS Y TAQUETES", "PINTORES", "PINTURA", "PINTURA EXTERIOR", "PINTURA INTERIOR", "PINTURA INTERIOR- EXTERIOR", "PINTURA, SOLVENTES Y SELLADORES", "PINTURA-EXTERIOR", "PINTURA-INTERIOR", "PINTURAS", "PINTURAS Y SOLVENTES", "PIPA Y AGUA PURIFICADA", "PIPAS", "PIPAS CON AGUA", "PISEROS", "PISO", "PISOS", "PISOS Y AZUEJOS", "PISOS Y AZULEJO", "PISOS Y AZULEJOS", "PISOS Y PISOS, AZULEJOS, ZOCLOS Y CENEFAS", "PISOS Y SOCLOS", "PISOS Y ZOCLOS", "PISOS, AZULEJOS, ZOCLOS Y CENEFAS", "PISOS, AZULEJSO Y ZOCLOS", "PISOS, PEGAZULEJOS", "PISOS, ZOCLOS Y AZULEJOS", "PLACAS Y CUBIERTAS", "PLANTAS Y OBRAS DE INFRAESTRUCTURA", "PLOMERIA", "PLOMERO", "POLIDICTO", "POLIETILENO", "PPINT", "PREELIMINARES - ACARREOS", "PRELIMINARE-ACARREOS", "PRELIMINARES - ACARREOS", "PRELIMINARES ACARREOS", "PRELIMINARES-ACARREOS", "PRELIMINARES-EXCAVACION", "PRELIMINARES-RELLENOS", "PTR", "PUBLICIDAD", "PUERTA", "PUERTAS", "PUERTAS  Y HERRAJES", "PUERTAS Y CEMENTANTES", "PUERTAS Y CERRAJES", "PUERTAS Y HERRAJES", "PUERTAS Y MARCOS", "PUERTAS Y VENTANAS", "RECOLECCION DE BASURA", "RECOLECCION DE ESCOMBRO", "REGLAS METALICAS", "RENTA DE MAQUINARIA", "RENTA EQUIPO MENOR", "RENTA MAQUINARIA", "RENTAS", "RETIRO DE BASURA", "RETIRO DE ESCOMBRO", "SANITARIO", "SANITARIO MOVIL", "SANITARIOS", "SELLADOR", "SILLAS", "SILLETA", "SILLETAS", "SIST. CONTRAINCENDIO", "SUBCONTRATO ESPECIALIZADO", "SUBCONTRATOS", "SUBCONTRATOS FRACC. COLINAS DEL SOL", "SUBCONTRATOS INFONAVIT", "SUBCONTRATOS RESIDENCIAL", "SUMINISTRO DE AGUA", "SUMINISTRO DE PASTO", "TABLAROCA", "TABLAROCA Y PAFLON", "TABLAROCA Y PLAFON", "TABLAROCA Y PLAFÓN", "TANCOL CONCRETO TRAD.", "TARIMAS", "TECHUMBHRES", "TECHUMBRE", "TECHUMBRES", "TEMPLADOS", "TINACOS", "TOPES", "TOPOGRAFIA", "TORNILLOS", "TORNILLOS PIJAS Y TAQUETES", "TORNILLOS Y TAQUETES", "TORNILLOS, PIJAS Y TAQUETES", "TRABAJOS ELECTRICOS", "TUBERIA Y CONEXIONES", "TUBERIA Y CONEXIONES HIDROSANITARIAS", "TUBERIA, CABLES Y CONEXIONES ELECTRICAS", "TUBERIAS Y CONEXIONES", "TUBO", "TUERCAS, TORNILLOS, PIJAS", "TUERCAS, TORNILLOS, PIJAS, TAQUETES", "URBANIZACION", "URBANIZACIÓN", "VARIOS", "VENTANAS", "VENTANAS CANCELERIAS Y ESPEJOS", "VENTANAS, CANCELERIA Y ESPEJOS", "VENTANERIA", "VENTANERIA TOTAL", "VENTANERÍA", "VIALIDAD PRINCIPAL", "VIGUETA", "WC Y ACCESORIOS", "YESEROS / MASILLEROS", "YESO"],
          semanaCorteMasiva: '',
          guardandoLote: false,
          archivosPendientesPorOC: {},
          documentosTemporalesPorProgramacion: {},
          mostrarModalAdjuntarOC: false,
          ocParaAdjuntar: null,
          archivosParaSubirOC: [],
          subiendoArchivosOC: false,
          mostrarModalVisorDocs: false,
          ocVisorDocs: null
        };
      },
      computed: {
        obrasSugeridasVivo() {
          const obras = this.catalogoObrasVivo.length ? this.catalogoObrasVivo : this.listaObrasVivo;
          const busqueda = String(this.filtroVivoObra || '').trim().toLocaleLowerCase();
          return obras.filter(obra => String(obra).toLocaleLowerCase().includes(busqueda)).slice(0, 100);
        },
        puedeVerConcentrado() {
          if (!this.usuarioActivo) return false;
          if (this.usuarioActivo === 'ADMIN') return true;
          if (this.perfilUsuario && (this.perfilUsuario.rol === 'ADMIN' || this.perfilUsuario.puede_ver_conciliacion || this.perfilUsuario.es_admin_funcional)) {
            return true;
          }
          
          const nom = (this.usuarioActivo || '').toUpperCase();
          const em = ((this.perfilUsuario && this.perfilUsuario.email) || '').toLowerCase();
          const empId = Number(this.perfilUsuario ? this.perfilUsuario.id : 0) || 0;
          
          // IDs de empleados autorizados
          if ([18, 120, 166, 3399, 9119].includes(empId)) return true;
          
          // Correos autorizados
          const emailsAutorizados = [
            'claudia.padilla@grupovelas.com', 'enkontrol2@grupovelas.com', 'enkontrol3@grupovelas.com',
            'avillanueva@grupovelas.com', 'pedro.ek@grupovelas.com', '',
            'santos.perez@grupovelas.com', 'supervision.auxiliares3@grupovelas.com', 'supervision.auxiliares@grupovelas.com'
          ];
          if (emailsAutorizados.includes(em)) return true;

          // Coincidencia exacta de los 8 nombres designados
          if (nom.includes('PADILLA') && (nom.includes('CLAUDIA') || nom.includes('SARAI'))) return true;
          if (nom.includes('SOTERO') && nom.includes('CRUZ')) return true;
          if (nom.includes('VILLANUEVA') && nom.includes('ARTURO')) return true;
          if ((nom.includes('PEDRO') && nom.includes('PEREZ')) || nom.includes('PEREZ GUZMAN')) return true;
          if (nom.includes('YEUDIEL') || (nom.includes('RICHARD') && nom.includes('PEREZ'))) return true;
          if (nom.includes('SANTOS') && (nom.includes('PEREZ') || nom.includes('TOLENTINO'))) return true;
          if (nom.includes('MOLINA') && (nom.includes('IRMA') || nom.includes('MIREYA'))) return true;
          if (nom.includes('PERNAS')) return true;
          
          return false;
        },
        puedeBorrarPagos() {
          return Boolean(this.puedeBorrarPagosSesion);
        },
        calculoDiferencia() {
          const imp = Number(this.formProgramar.importe) || 0;
          const pag = Number(this.formProgramar.pagando) || 0;
          return Math.max(0, imp - pag);
        },
        sumaTotalProgramadoVivo() {
          if (!this.ordenesVivo || !this.ordenesVivo.length) return 0;
          return this.ordenesVivo.reduce((acc, o) => acc + (Number(o.total_programado || o.pagando) || 0), 0);
        },
        listaObrasVivo() {
          const obras = new Set();
          for (const oc of (this.ordenesVivo || [])) {
            if (oc.centro_costos) obras.add(oc.centro_costos);
          }
          return Array.from(obras).sort();
        },
        ordenesFiltradasObra() {
          let lista = this.ordenesVivo || [];
          if (this.filtroVivoObra) {
            lista = lista.filter(o => o.centro_costos === this.filtroVivoObra);
          }
          // TABLA SUPERIOR: SOLO ÓRDENES QUE FALTAN POR PAGAR (SALDO > $0.00)
          return lista.filter(o => (o.saldo_disponible === undefined || o.saldo_disponible === null || o.saldo_disponible > 0.009));
        },
        ordenesPagadas100() {
          let lista = this.ordenesVivo || [];
          if (this.filtroVivoObra) {
            lista = lista.filter(o => o.centro_costos === this.filtroVivoObra);
          }
          // ÓRDENES LIQUIDADAS AL 100%
          return lista.filter(o => (o.saldo_disponible !== undefined && o.saldo_disponible !== null && o.saldo_disponible <= 0.009));
        },
        ordenesListasParaGuardar() {
          return (this.ordenesFiltradasObra || []).filter(o => Number(o.inputMonto) > 0);
        },
        totalMontoLote() {
          return this.ordenesListasParaGuardar.reduce((acc, o) => acc + Number(o.inputMonto || 0), 0);
        }
      },
      methods: {
        alternarAcordeonOC(oc) {
          if (this.ocExpandidaId === oc.id) {
            this.ocExpandidaId = null;
          } else {
            this.ocExpandidaId = oc.id;
            const saldo = (oc.saldo_disponible !== undefined && oc.saldo_disponible !== null) ? oc.saldo_disponible : oc.total;
            this.formAbonoRapido = {
              monto: saldo > 0 ? saldo : 0,
              rubro: oc.rubro || 'MAT',
              concepto: oc.comentarios || '',
              semana: this.semanaActualOficial ? this.semanaActualOficial.etiqueta : (this.listaSemanas[0] || 'SEM: 44-2026')
            };
          }
        },
        prepararInputsOrdenes() {
          const semDefecto = this.semanaCorteMasiva || (this.semanaActualOficial ? this.semanaActualOficial.etiqueta : (this.listaSemanas[0] || 'SEM: 44-2026'));
          this.semanaCorteMasiva = semDefecto;
          for (const oc of (this.ordenesVivo || [])) {
            if (oc.inputMonto === undefined) oc.inputMonto = null;
            // Autocalcular / precargar TIPO desde oc.tipo_sugerido
            if (!oc.inputTipo) {
              oc.inputTipo = oc.tipo_sugerido || (oc.tipo || '(1) MATERIALES');
            }
            // Autocalcular / precargar RUBRO desde oc.rubro_sugerido
            if (!oc.inputRubro) {
              oc.inputRubro = oc.rubro_sugerido || (oc.rubro || 'MATERIALES DIVERSOS');
            }
            if (oc.inputConcepto === undefined) oc.inputConcepto = '';
            if (!oc.inputSemana) oc.inputSemana = semDefecto;
          }
        },
        validarMontoMaximo(oc) {
          const saldo = (oc.saldo_disponible !== undefined && oc.saldo_disponible !== null) ? oc.saldo_disponible : oc.total;
          if (saldo <= 0.009) {
            oc.inputMonto = null;
            return;
          }
          if (oc.inputMonto > saldo) {
            oc.inputMonto = Number(saldo.toFixed(2));
            this.dispararToastAdvertencia(`El saldo máximo disponible para programar en la OC #${oc.numero_oc} es $${this.formatearNumero(saldo)}`);
          } else if (oc.inputMonto < 0) {
            oc.inputMonto = 0;
          }
        },
        aplicarPagarTodo(oc) {
          const saldo = (oc.saldo_disponible !== undefined && oc.saldo_disponible !== null) ? oc.saldo_disponible : oc.total;
          if (saldo <= 0.009) {
            this.dispararToastAdvertencia(`La OC #${oc.numero_oc} ya se encuentra liquidada al 100%`);
            oc.inputMonto = null;
            return;
          }
          oc.inputMonto = saldo > 0 ? Number(saldo.toFixed(2)) : 0;
        },
        limpiarInputMonto(oc) {
          oc.inputMonto = null;
        },
        llenarTodasConSaldo() {
          for (const oc of this.ordenesFiltradasObra) {
            const saldo = (oc.saldo_disponible !== undefined && oc.saldo_disponible !== null) ? oc.saldo_disponible : oc.total;
            if (saldo > 0.009) {
              oc.inputMonto = saldo;
              if (!oc.inputRubro) oc.inputRubro = '';
              if (!oc.inputSemana) oc.inputSemana = this.semanaCorteMasiva;
            }
          }
        },
        limpiarTodosLosInputs() {
          for (const oc of this.ordenesFiltradasObra) {
            oc.inputMonto = null;
            oc.inputConcepto = '';
          }
        },
        cambiarSemanaMasiva() {
          for (const oc of (this.ordenesVivo || [])) {
            oc.inputSemana = this.semanaCorteMasiva;
          }
          this.cargarProgramacionesSemana();
        },
        async obtenerTotalFacturaXmlEscalado(archivo) {
          const contenido = await archivo.text();
          const documento = new DOMParser().parseFromString(contenido, 'application/xml');
          if (documento.querySelector('parsererror')) {
            throw new Error(`El XML "${archivo.name}" no es válido.`);
          }

          const comprobante = documento.documentElement;
          const total = comprobante?.getAttribute('Total') || '';
          if (comprobante?.localName !== 'Comprobante' || !/^\d{1,18}(?:\.\d{1,6})?$/.test(total)) {
            throw new Error(`El XML "${archivo.name}" no contiene un total CFDI válido.`);
          }

          const [entero, decimales = ''] = total.split('.');
          return BigInt(entero) * 1000000n + BigInt(decimales.padEnd(6, '0') || '0');
        },
        formatearTotalXmlEscalado(totalEscalado) {
          const entero = totalEscalado / 1000000n;
          const decimales = (totalEscalado % 1000000n).toString().padStart(6, '0').replace(/0+$/, '');
          return decimales ? `${entero}.${decimales}` : String(entero);
        },
        async guardarLoteMasivo() {
          const lista = this.ordenesListasParaGuardar;
          if (lista.length === 0) {
            this.dispararToastError('No hay órdenes con monto capturado para programar');
            return;
          }
          this.guardandoLote = true;
          try {
            const items = lista.map(o => ({
              empresa: o.empresa || this.empresaActiva,
              centro_costos: o.centro_costos,
              numero_oc: o.numero_oc,
              id_ek10: o.id_ek10 || '',
              proveedor: o.proveedor || '',
              total_oc: Number(o.total) || 0,
              monto_programado: Number(o.inputMonto),
              semana: o.inputSemana || this.semanaCorteMasiva,
              tipo: o.inputTipo || o.tipo_sugerido || o.tipo || '',
                  rubro: o.inputRubro || '',
              concepto: o.inputConcepto || '',
              creado_por: this.usuarioActivo || 'USUARIO'
            }));

            for (let indice = 0; indice < items.length; indice++) {
              const archivos = this.archivosPendientesPorOC[this.claveDocumentosOC(items[indice])] || [];
              const xmls = archivos.filter(archivo => archivo.name.toLowerCase().endsWith('.xml'));
              if (xmls.length === 0) continue;

              let totalEscalado = 0n;
              for (const xml of xmls) {
                totalEscalado += await this.obtenerTotalFacturaXmlEscalado(xml);
              }
              const saldoRaw = lista[indice].saldo_disponible;
              const saldoDisponible = Number(saldoRaw);
              const importePago = saldoRaw !== null && saldoRaw !== undefined && Number.isFinite(saldoDisponible)
                ? Math.min(Number(items[indice].monto_programado), saldoDisponible)
                : Number(items[indice].monto_programado);
              const importeEscalado = BigInt(Math.round(importePago * 1000000));
              if (totalEscalado >= importeEscalado) {
                throw new Error(
                  `La suma de las facturas XML de la OC #${items[indice].numero_oc} ` +
                  `($${this.formatearTotalXmlEscalado(totalEscalado)}) debe ser menor ` +
                  `al importe del pago ($${this.formatearTotalXmlEscalado(importeEscalado)}).`
                );
              }
              items[indice].total_facturas_xml = this.formatearTotalXmlEscalado(totalEscalado);
            }

            const formData = new FormData();
            formData.append('items', JSON.stringify(items));
            let documentosEnLote = 0;
            if (this.documentosHabilitados) {
              items.forEach((item, indice) => {
                const archivos = this.archivosPendientesPorOC[this.claveDocumentosOC(item)] || [];
                documentosEnLote += archivos.length;
                archivos.forEach(archivo => formData.append(`archivos_${indice}`, archivo));
              });
            } else {
              documentosEnLote = items.reduce((total, item) =>
                total + (this.archivosPendientesPorOC[this.claveDocumentosOC(item)] || []).length, 0
              );
            }

            const res = await fetch('/api/ordenes/programar-lote', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') {
              throw new Error(data.mensaje || 'No se pudieron guardar los pagos pendientes');
            }
            if (!data.guardados) {
              await this.cargarOrdenesVivo(this.paginaVivo, false);
              throw new Error('No se guardó ningún pago. Verifica que las órdenes todavía tengan saldo disponible.');
            }

            const indicesGuardados = new Set(data.items_guardados || []);
            lista.forEach((oc, indice) => {
              if (!indicesGuardados.has(indice)) return;
              oc.inputMonto = null;
              oc.inputConcepto = '';
              delete this.archivosPendientesPorOC[this.claveDocumentosOC(items[indice])];
            });

            await this.cargarProgramacionesSemana(false);
            await this.cargarOrdenesVivo(this.paginaVivo, false);
            let mensaje = `${data.guardados} pago(s) guardados en la tabla. Aún no se enviaron a BI; usa el botón de envío de cada fila cuando estés listo.`;
            if (!this.documentosHabilitados && documentosEnLote > 0) {
              mensaje += ` Se omitieron ${documentosEnLote} documento(s) porque la carga está desactivada.`;
            }
            if (data.guardados < items.length) {
              mensaje += ` No se guardaron ${items.length - data.guardados} pago(s) porque ya no tenían saldo.`;
            }
            this.dispararToastExito(mensaje);
          } catch (e) {
            this.dispararToastError(e.message || 'Error al preparar el lote');
          } finally {
            this.guardandoLote = false;
          }
        },
        async enviarPagoPendiente(prog) {
          if (!prog?.es_pendiente || this.enviandoPendienteId !== null) return;
          this.enviandoPendienteId = prog.id;
          try {
            const res = await fetch(`/api/ordenes/programaciones/${encodeURIComponent(prog.id)}/enviar`, {
              method: 'POST'
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') {
              throw new Error(data.mensaje || 'No se pudo enviar el pago');
            }
            this.dispararToastExito(data.mensaje || `Pago de la OC #${prog.numero_oc} enviado a BI.`);
            await this.cargarProgramacionesSemana(false);
            await this.cargarOrdenesVivo(this.paginaVivo, false);
          } catch (error) {
            this.dispararToastError(error.message || 'Error al enviar el pago');
          } finally {
            this.enviandoPendienteId = null;
          }
        },
        async guardarAbonoRapido(oc) {
          if (!this.formAbonoRapido.monto || this.formAbonoRapido.monto <= 0) {
            this.dispararToastError('Ingresa un monto válido mayor a $0.00');
            return;
          }
          this.guardandoAbonoRapido = true;
          try {
            const payload = {
              empresa: oc.empresa || this.empresaActiva,
              centro_costos: oc.centro_costos,
              numero_oc: oc.numero_oc,
              id_ek10: oc.id_ek10 || '',
              proveedor: oc.proveedor || '',
              total_oc: Number(oc.total) || 0,
              monto_programado: Number(this.formAbonoRapido.monto),
              semana: this.formAbonoRapido.semana,
              rubro: this.formAbonoRapido.rubro,
              concepto: this.formAbonoRapido.concepto,
              creado_por: this.usuarioActivo || 'USUARIO'
            };
            const res = await fetch('/api/ordenes/programar-abono', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.status === 'ok') {
              oc.programaciones = data.programaciones;
              oc.total_programado = data.total_programado;
              oc.saldo_disponible = data.saldo_disponible;
              oc.pagando = data.total_programado;
              this.dispararToastExito(`Abono de $${this.formatearNumero(payload.monto_programado)} registrado. Saldo restante: $${this.formatearNumero(data.saldo_disponible)}`);
              
              // Si aún queda saldo, preparar el input para el siguiente abono
              if (data.saldo_disponible > 0) {
                this.formAbonoRapido.monto = data.saldo_disponible;
                this.formAbonoRapido.concepto = '';
              } else {
                this.formAbonoRapido.monto = 0;
              }
            } else {
              this.dispararToastError(data.mensaje || 'Error al registrar el abono');
            }
          } catch (e) {
            this.dispararToastError('Error de conexión al guardar el abono');
          } finally {
            this.guardandoAbonoRapido = false;
          }
        },
        async eliminarAbonoRapido(oc, idAbono) {
          if (!this.puedeBorrarPagos) {
            this.dispararToastError('No tienes permiso para borrar pagos programados');
            return;
          }
          if (!confirm('¿Deseas eliminar este abono programado?')) return;
          try {
            const res = await fetch('/api/ordenes/eliminar-abono', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                id: idAbono,
                empresa: oc.empresa || this.empresaActiva,
                centro_costos: oc.centro_costos,
                numero_oc: oc.numero_oc,
                total_oc: Number(oc.total) || 0
              })
            });
            const data = await res.json();
            if (data.status === 'ok') {
              oc.programaciones = data.programaciones;
              oc.total_programado = data.total_programado;
              oc.saldo_disponible = data.saldo_disponible;
              oc.pagando = data.total_programado;
              this.dispararToastExito(`Abono eliminado. Saldo restante actualizado: $${this.formatearNumero(data.saldo_disponible)}`);
              this.formAbonoRapido.monto = data.saldo_disponible;
              await this.cargarProgramacionesSemana();
            } else {
              this.dispararToastError(data.mensaje || 'Error al eliminar');
            }
          } catch (e) {
            this.dispararToastError('Error al eliminar abono');
          }
        },
        
        claveDocumentosOC(oc) {
          return [oc.empresa || this.empresaActiva, oc.centro_costos, oc.numero_oc]
            .map(valor => String(valor ?? '').trim().toUpperCase())
            .join('|');
        },
        claveOrdenPago(oc) {
          return [oc.empresa || this.empresaActiva, oc.centro_costos, oc.numero_oc]
            .map(valor => String(valor ?? '').trim().toUpperCase())
            .join('|');
        },
        esSemanaDeCorteActual(semana) {
          const normalizar = valor => String(valor || '')
            .trim()
            .replace(/^SEM(?:ANA)?[:.]?\s*/i, '')
            .toUpperCase();
          return Boolean(
            this.semanaActualOficial?.etiqueta &&
            normalizar(semana) &&
            normalizar(semana) === normalizar(this.semanaActualOficial.etiqueta)
          );
        },
        abrirModalAdjuntarOC(oc) {
          const clave = this.claveDocumentosOC(oc);
          if (oc.total_documentos > 0) {
            this.dispararToastAdvertencia(`La OC #${oc.numero_oc} ya cuenta con documentos adjuntados. No se permite duplicar.`);
            return;
          }
          this.ocParaAdjuntar = oc;
          // Si ya tenía archivos pausados, cargarlos
          this.archivosParaSubirOC = this.archivosPendientesPorOC[clave] ? [...this.archivosPendientesPorOC[clave]] : [];
          this.mostrarModalAdjuntarOC = true;
        },
        cerrarModalAdjuntarOC() {
          this.mostrarModalAdjuntarOC = false;
          this.ocParaAdjuntar = null;
          this.archivosParaSubirOC = [];
        },
        manejarSeleccionModalDocs(e) {
          const files = Array.from(e.target.files || []);
          if (files.length === 0) return;
          const totalCombinado = this.archivosParaSubirOC.length + files.length;
          if (totalCombinado > 4) {
            this.dispararToastError('Sólo se permiten entre 2 y 4 archivos en total por orden');
          }
          for (const f of files) {
            if (this.archivosParaSubirOC.length < 4 && !this.archivosParaSubirOC.some(a => a.name === f.name && a.size === f.size)) {
              this.archivosParaSubirOC.push(f);
            }
          }
          if (this.$refs.inputModalOCDocs) {
            this.$refs.inputModalOCDocs.value = '';
          }
        },
        quitarArchivoModal(idx) {
          this.archivosParaSubirOC.splice(idx, 1);
        },
        subirArchivosOCSeleccionada() {
          if (!this.ocParaAdjuntar) return;
          if (this.archivosParaSubirOC.length < 2 || this.archivosParaSubirOC.length > 4) {
            this.dispararToastError('Debes seleccionar entre 2 y 4 archivos para guardar');
            return;
          }
          const clave = this.claveDocumentosOC(this.ocParaAdjuntar);
          // Guardar archivos en pausa en la memoria de la aplicación
          this.archivosPendientesPorOC[clave] = [...this.archivosParaSubirOC];
          
          this.dispararToastExito(`${this.archivosParaSubirOC.length} archivos adjuntados en pausa. Pulsa 'GUARDAR TODO' para preparar el pago y luego envíalo desde GUARDADO.`);
          this.cerrarModalAdjuntarOC();
        },
        async abrirVisorDocumentos(oc) {
          try {
            const params = new URLSearchParams({
              empresa: oc.empresa || this.empresaActiva || 'ARYVE',
              centro_costos: oc.centro_costos,
              numero_oc: oc.numero_oc
            });
            const res = await fetch('/api/documentos-oc?' + params.toString());
            const data = await res.json();
            if (data.status === 'ok') {
              this.ocVisorDocs = { ...oc, documentos: data.documentos || [] };
            } else {
              this.ocVisorDocs = { ...oc, documentos: oc.documentos || [] };
            }
          } catch(e) {
            this.ocVisorDocs = { ...oc, documentos: oc.documentos || [] };
          }
          this.mostrarModalVisorDocs = true;
        },
async cargarProgramacionesSemana(silencioso = false) {
          // Solo mostrar spinner si es carga manual y no hay datos previos para evitar parpadeos
          if (!silencioso && this.programacionesSemana.length === 0) {
            this.cargandoProgSemana = true;
          }
          try {
            let sem = (this.semanaCorteMasiva || this.filtroVivoSemana || '').replace('SEM:', '').replace('SEM.', '').trim();
            if (!sem && this.semanaActualOficial && this.semanaActualOficial.etiqueta) {
              sem = this.semanaActualOficial.etiqueta.replace('SEM:', '').replace('SEM.', '').trim();
            }
            if (!sem) {
              sem = '43-2026';
            }
            const params = new URLSearchParams({
              empresa: this.empresaActiva || 'ARYVE',
              semana: sem
            });
            if (this.filtroVivoObra) {
              params.append('obra', this.filtroVivoObra);
            }
            const res = await fetch(`/api/ordenes/programaciones-semana?${params.toString()}`);
            const data = await res.json();
            if (data.status === 'ok') {
              const nuevos = data.datos || [];
              // Evitar mutar el array si los IDs y montos no cambiaron para eliminar micro-recargas visuales
              const strActual = (this.programacionesSemana || [])
                .map(p => `${p.id}_${p.monto_programado}_${p.estado_envio}`).join(',');
              const strNuevo = nuevos
                .map(p => `${p.id}_${p.monto_programado}_${p.estado_envio}`).join(',');
              if (strActual !== strNuevo) {
                this.programacionesSemana = nuevos;
              }
              this.totalMontoProgramadoSemana = data.total_monto || 0;
            } else {
              this.programacionesSemana = [];
              this.totalMontoProgramadoSemana = 0;
            }
          } catch (e) {
            console.error('Error cargando programaciones de la semana:', e);
          } finally {
            this.cargandoProgSemana = false;
          }
        },
        async eliminarAbonoSemana(prog) {
          if (!this.puedeBorrarPagos) {
            this.dispararToastError('No tienes permiso para borrar pagos programados');
            return;
          }
          const accion = prog.es_pendiente
            ? 'quitar de GUARDADO el pago pendiente'
            : 'eliminar el abono';
          if (!confirm(`¿Deseas ${accion} de $${Number(prog.monto_programado).toLocaleString('es-MX', {minimumFractionDigits: 2})} de la OC ${prog.numero_oc}?`)) return;
          try {
            const res = await fetch('/api/ordenes/eliminar-abono', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                id: prog.id,
                empresa: prog.empresa || this.empresaActiva,
                centro_costos: prog.centro_costos,
                numero_oc: prog.numero_oc,
                total_oc: Number(prog.total_oc) || 0
              })
            });
            const data = await res.json();
            if (data.status === 'ok') {
              delete this.documentosTemporalesPorProgramacion[prog.id];
              this.dispararToastExito(prog.es_pendiente
                ? 'Pago pendiente quitado de GUARDADO; no se envió a BI.'
                : 'Abono eliminado y saldo restablecido');
              await this.cargarProgramacionesSemana();
              await this.cargarOrdenesVivo(this.paginaVivo, false);
            } else {
              this.dispararToastError(data.mensaje || 'Error al eliminar abono');
            }
          } catch (err) {
            this.dispararToastError('Error de red al eliminar abono');
          }
        },
        obtenerRubroCorto(val) {
          if (!val) return 'MAT';
          const u = String(val).toUpperCase();
          if (u.includes('MO') || u.includes('MANO DE OBRA')) return 'MO';
          if (u.includes('SUB')) return 'SUB';
          if (u.includes('URB')) return 'URB';
          if (u.includes('ELEC')) return 'ELEC';
          if (u.includes('MAT')) return 'MAT';
          return 'MAT';
        },
        dispararToastExito(msg) {
          this.toastExitoMensaje = msg;
          this.mostrarToastExito = true;
          setTimeout(() => { this.mostrarToastExito = false; }, 6000);
        },
        abrirModalProgramar(oc) {
          const totalVal = Number(oc.total) || 0;
          const pagandoVal = (oc.pagando !== undefined && oc.pagando !== null && oc.pagando > 0) ? Number(oc.pagando) : totalVal;
          this.esProgramacionDesdeOC = true;
          this.formProgramar = {
            tipo: 'OC',
            folio: oc.id_ek10 ? `${oc.id_ek10} (#${oc.numero_oc})` : `${oc.numero_oc}`,
            obra: oc.centro_costos || '',
            proveedor: oc.proveedor || '',
            rubro: oc.rubro || 'MAT',
            importe: totalVal,
            pagando: pagandoVal,
            diferencia: Math.max(0, totalVal - pagandoVal),
            semana: this.semanaActualOficial ? this.semanaActualOficial.etiqueta : (this.listaSemanas[0] || ''),
            concepto: oc.comentarios || `Pago programado para OC #${oc.numero_oc}`
          };
          this.archivosAdjuntos = [];
          this.archivosSubidosExito = false;
          this.mensajeCargaArchivos = '';
          this.mostrarModalProgramar = true;
        },
        abrirModalProgramarManual() {
          this.esProgramacionDesdeOC = false;
          this.formProgramar = {
            tipo: 'OC',
            folio: '',
            obra: '',
            proveedor: '',
            rubro: '',
            importe: 0,
            pagando: 0,
            diferencia: 0,
            semana: this.semanaActualOficial ? this.semanaActualOficial.etiqueta : (this.listaSemanas[0] || ''),
            concepto: ''
          };
          this.mostrarModalProgramar = true;
        },
        cerrarModalProgramar() {
          this.mostrarModalProgramar = false;
        },
        manejarSeleccionArchivos(event) {
          const files = Array.from(event.target.files || []);
          for (const f of files) {
            if (!this.archivosAdjuntos.some(a => a.file.name === f.name && a.file.size === f.size)) {
              this.archivosAdjuntos.push({
                file: f,
                subido: false
              });
            }
          }
          this.archivosSubidosExito = false;
          if (this.$refs.inputArchivos) {
            this.$refs.inputArchivos.value = '';
          }
        },
        removerArchivoAdjunto(index) {
          this.archivosAdjuntos.splice(index, 1);
          if (this.archivosAdjuntos.length === 0) {
            this.archivosSubidosExito = false;
          }
        },
        formatearTamanoArchivo(bytes) {
          if (!bytes || bytes === 0) return '0 B';
          const k = 1024;
          const sizes = ['B', 'KB', 'MB', 'GB'];
          const i = Math.floor(Math.log(bytes) / Math.log(k));
          return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },
        obtenerIconoArchivo(nombre) {
          const ext = (nombre || '').split('.').pop().toLowerCase();
          if (ext === 'pdf') return 'fa-solid fa-file-pdf text-rose-400';
          if (['xls', 'xlsx', 'csv'].includes(ext)) return 'fa-solid fa-file-excel text-emerald-400';
          if (['doc', 'docx'].includes(ext)) return 'fa-solid fa-file-word text-blue-400';
          if (ext === 'xml') return 'fa-solid fa-file-code text-amber-400';
          if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return 'fa-solid fa-file-image text-cyan-400';
          return 'fa-solid fa-file text-slate-400';
        },
        async subirArchivosServidor() {
          if (!this.documentosHabilitados) {
            return false;
          }
          const pendientes = this.archivosAdjuntos.filter(a => !a.subido);
          if (pendientes.length === 0) {
            return true;
          }
          this.cargandoArchivos = true;
          try {
            const formData = new FormData();
            formData.append('folio', this.formProgramar.folio || 'SIN_FOLIO');
            formData.append('obra', this.formProgramar.obra || '');
            formData.append('proveedor', this.formProgramar.proveedor || '');
            formData.append('usuario', this.usuarioActivo || '');
            
            for (const item of pendientes) {
              formData.append('archivos', item.file);
            }

            const res = await fetch('/api/subir-documentos', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            if (data.status === 'ok') {
              pendientes.forEach(a => a.subido = true);
              this.archivosSubidosExito = true;
              this.mensajeCargaArchivos = `${data.guardados?.length || pendientes.length} archivo(s) guardado(s) en documentos_ejemplo`;
              return true;
            } else {
              this.dispararToastError(data.mensaje || 'Error al subir los archivos');
              return false;
            }
          } catch (err) {
            console.error('Error subiendo archivos:', err);
            this.dispararToastError('Error al subir los archivos al servidor');
            return false;
          } finally {
            this.cargandoArchivos = false;
          }
        },
        async guardarProgramacionPago() {
          if (!this.formProgramar.folio || this.formProgramar.pagando <= 0) {
            this.dispararToastError('Por favor verifica el folio y que el monto a programar sea mayor a $0.00');
            return;
          }
          this.guardandoProgramacion = true;

          // Si hay archivos adjuntos pendientes de subir, subirlos al servidor primero
          if (this.documentosHabilitados && this.archivosAdjuntos.length > 0 && this.archivosAdjuntos.some(a => !a.subido)) {
            const subidaExitosa = await this.subirArchivosServidor();
            if (!subidaExitosa) {
              this.guardandoProgramacion = false;
              return;
            }
          }

          setTimeout(() => {
            const montoTexto = this.formatearNumero(this.formProgramar.pagando);
            const folioTexto = this.formProgramar.folio;
            const rubroTexto = this.formProgramar.rubro || '-';
            
            // Reflejar de inmediato en la fila correspondiente de la tabla en vivo
            if (this.ordenesVivo && this.ordenesVivo.length) {
              const encontrada = this.ordenesVivo.find(o => 
                String(o.numero_oc) === String(folioTexto) || 
                String(folioTexto).includes(String(o.numero_oc)) ||
                (o.id_ek10 && String(folioTexto).includes(String(o.id_ek10)))
              );
              if (encontrada) {
                encontrada.pagando = this.formProgramar.pagando;
                encontrada.diferencia = Math.max(0, encontrada.total - this.formProgramar.pagando);
                encontrada.rubro = rubroTexto;
              }
            }

            this.guardandoProgramacion = false;
            const cantArchivos = this.archivosAdjuntos.length;
            const extraArchivos = cantArchivos > 0
              ? (this.documentosHabilitados
                ? ` con ${cantArchivos} documento(s) guardado(s) en documentos_ejemplo`
                : `; se omitieron ${cantArchivos} documento(s) porque la carga está desactivada para pruebas`)
              : '';
            this.cerrarModalProgramar();
            this.dispararToastExito(`Prueba exitosa: Pago de $${montoTexto} (${rubroTexto}) programado para ${folioTexto}${extraArchivos}.`);
          }, 350);
        },
        cambiarVista(vista) {
          if (vista === 'CONCILIACION' && !this.puedeVerConcentrado) {
            this.vistaActiva = 'ORDENES_VIVO';
            this.cargarOrdenesVivo(1);
          this.cargarProgramacionesSemana();
            return;
          }
          this.vistaActiva = vista;
          if (vista === 'ORDENES_VIVO') {
            this.cargarOrdenesVivo(1);
          } else {
            this.cargarDatos(1);
          }
        },
        timerBusquedaVivo: null,
          debounceBusquedaVivo() {
            clearTimeout(this.timerBusquedaVivo);
            this.timerBusquedaVivo = setTimeout(() => {
              this.cargarOrdenesVivo(1);
            }, 350);
          },
          async cargarCatalogoObras() {
          try {
            const res = await fetch('/api/ordenes/catalogo-obras?empresa=' + this.empresaActiva);
            const data = await res.json();
            if (data.status === 'ok' && data.obras) {
              this.catalogoObrasVivo = data.obras;
            }
          } catch(e) {
            console.error('Error cargando catalogo de obras:', e);
          }
        },
        onCambioSemanaCorteFiltro() {
          if (this.filtroVivoSemana) {
            this.filtroVivoTemporal = '';
            this.semanaCorteMasiva = this.filtroVivoSemana;
          }
          this.cargarOrdenesVivo(1);
          this.cargarProgramacionesSemana();
        },
        aplicarFiltroObra() {
          this.filtroVivoObra = String(this.filtroVivoObra || '').trim();
          this.mostrarSugerenciasObra = false;
          this.cargarOrdenesVivo(1);
          this.cargarProgramacionesSemana();
        },
        seleccionarObraVivo(obra) {
          this.filtroVivoObra = obra;
          this.aplicarFiltroObra();
        },
        async cargarOrdenesVivo(pagina = 1, silencioso = false) {
          if (!this.filtroVivoSemana) {
            this.ordenesVivo = [];
            this.totalFiltradoVivo = 0;
            this.totalPaginasVivo = 1;
            this.sumaTotalVivo = 0;
            this.cargandoVivo = false;
            return;
          }
          if (!silencioso) {
            this.cargandoVivo = true;
          }
          this.paginaVivo = pagina;
          try {
            const params = new URLSearchParams({
              pagina: pagina,
              limite: 50,
              empresa: this.empresaActiva,
              temporal: this.filtroVivoTemporal,
              semana: this.filtroVivoSemana,
              obra: this.filtroVivoObra,
              busqueda: this.filtroVivoBusqueda,
              mis_ordenes: (this.perfilUsuario && (this.perfilUsuario.rol === 'ADMIN' || this.perfilUsuario.es_admin_funcional) && !this.filtroVivoMisOrdenes) ? '0' : (this.filtroVivoMisOrdenes ? '1' : '0')
            });
            if (this.perfilUsuario && this.perfilUsuario.id) {
              params.append('empleado_id', this.perfilUsuario.id);
            }

            const res = await fetch('/api/ordenes-recientes?' + params.toString());
            const data = await res.json();
            if (data.status === 'ok') {
              const nuevosDatos = data.datos || [];
              for (const oc of nuevosDatos) {
                const saldoServidor = oc.saldo_disponible ?? oc.total ?? 0;
                oc.saldoDisponibleServidor = Number(saldoServidor) || 0;
              }
              // Si es refresco silencioso en background, detectar si hay cambios reales para no parpadear
              if (silencioso) {
                const idsActuales = this.ordenesVivo.map(o => o.id).join(',');
                const idsNuevos = nuevosDatos.map(o => o.id).join(',');
                if (idsActuales !== idsNuevos || this.kpisVivo.ordenes_hoy !== (data.kpis?.ordenes_hoy || 0)) {
                  this.ordenesVivo = nuevosDatos;
                  this.kpisVivo = data.kpis || this.kpisVivo;
                  this.totalPaginasVivo = data.total_paginas || 1;
                  this.totalFiltradoVivo = data.total_filtrado || 0;
                  this.sumaTotalVivo = data.suma_total_filtrado || 0;
                }
              } else {
                this.ordenesVivo = nuevosDatos;
                this.kpisVivo = data.kpis || { ordenes_hoy: 0, monto_hoy: 0, total_acumulado: 0, monto_acumulado: 0 };
                this.totalPaginasVivo = data.total_paginas || 1;
                this.totalFiltradoVivo = data.total_filtrado || 0;
                this.sumaTotalVivo = data.suma_total_filtrado || 0;
              }
              this.prepararInputsOrdenes();
              await this.cargarProgramacionesSemana();
            }
          } catch (e) {
            console.error('Error cargando órdenes en vivo:', e);
          } finally {
            this.cargandoVivo = false;
          }
        },
        dispararToastError(msg) {
          this.toastMensaje = msg;
          this.mostrarToastError = true;
          this.shakeCard = true;
          setTimeout(() => { this.shakeCard = false; }, 500);
          if (this.toastTimer) clearTimeout(this.toastTimer);
          this.toastTimer = setTimeout(() => {
            this.mostrarToastError = false;
          }, 6000);
        },
        async iniciarSesionUsuario() {
          if (!this.loginUsuario || !this.loginPassword) {
            const msg = 'Ingresa tu usuario y contraseña para continuar';
            this.errorLogin = msg;
            this.dispararToastError(msg);
            return;
          }
          this.cargandoLogin = true;
          this.errorLogin = '';
          try {
            const res = await fetch('/api/login', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                usuario: this.loginUsuario,
                password: this.loginPassword,
                empresa: this.loginEmpresa
              })
            });
            const data = await res.json();
            if (res.ok && data.status === 'ok') {
              this.perfilUsuario = data.usuario;
              this.usuarioActivo = data.usuario.nombre;
              this.empresaActiva = data.usuario.empresa;
              this.puedeBorrarPagosSesion = Boolean(data.usuario.puede_borrar_pagos);
              localStorage.setItem('usuario_activo_oc', data.usuario.nombre);
              localStorage.setItem('perfil_activo_oc', JSON.stringify(data.usuario));
              localStorage.setItem('empresa_activa_oc', data.usuario.empresa);

              if (data.usuario.rol === 'ADMIN' || data.usuario.es_admin_funcional) {
                this.filtroResponsable = '';
                this.filtroPlaza = '';
                this.filtroVivoMisOrdenes = false;
              } else {
                this.filtroResponsable = data.usuario.nombre;
                this.filtroPlaza = data.usuario.plaza; // Plaza fija asignada al usuario
                this.filtroVivoMisOrdenes = true;
              }
              await this.cargarFiltros();
              this.cambiarVista('ORDENES_VIVO');
            } else {
              const msg = data.mensaje || 'No se encuentra disponible. Revisa tu usuario o contraseña.';
              this.errorLogin = msg;
              this.dispararToastError(msg);
              this.loginPassword = '';
            }
          } catch (err) {
            const msg = 'Error conectando con el servicio de autenticación. No se encuentra disponible.';
            this.errorLogin = msg;
            this.dispararToastError(msg);
          } finally {
            this.cargandoLogin = false;
          }
        },
        cerrarSesion() {
          fetch('/api/logout', { method: 'POST' }).catch(err => {
            console.error('Error cerrando la sesión del servidor:', err);
          });
          this.usuarioActivo = '';
          this.perfilUsuario = null;
          localStorage.removeItem('usuario_activo_oc');
          localStorage.removeItem('perfil_activo_oc');
          localStorage.removeItem('empresa_activa_oc');
          this.loginUsuario = '';
          this.loginPassword = '';
          this.errorLogin = '';
          this.filtroResponsable = '';
          this.filtroPlaza = '';
          this.archivosPendientesPorOC = {};
          this.documentosTemporalesPorProgramacion = {};
        },
        formatearNumero(val) {
          if (val === undefined || val === null || isNaN(val)) return '0.00';
          return Number(val).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatearEntero(val) {
          if (val === undefined || val === null || isNaN(val)) return '0';
          return Number(val).toLocaleString('es-MX');
        },
        async cargarFiltros() {
          try {
            const res = await fetch('/api/filtros?empresa=' + encodeURIComponent(this.empresaActiva));
            const data = await res.json();
            if (data.status === 'ok') {
              this.listaResponsables = data.responsables;
              this.listaPlazas = data.plazas;
              this.listaFraccionamientos = data.fraccionamientos;
              this.listaRubros = data.rubros;
              this.listaSemanas = data.semanas;
              this.cargarCatalogoObras();
              this.semanaActualOficial = data.semana_actual || null;
              if (this.semanaActualOficial?.etiqueta) {
                this.filtroVivoSemana = this.semanaActualOficial.etiqueta;
                this.semanaCorteMasiva = this.semanaActualOficial.etiqueta;
                this.filtroVivoTemporal = '';
              } else if (this.listaSemanas.length) {
                this.filtroVivoSemana = this.listaSemanas[0];
                this.semanaCorteMasiva = this.listaSemanas[0];
                this.filtroVivoTemporal = '';
              } else {
                this.filtroVivoSemana = '';
                this.semanaCorteMasiva = '';
                this.filtroVivoTemporal = '';
              }
              this.kpisGlobales = data.kpis_globales;
            }
          } catch (e) {
            console.error('Error cargando filtros:', e);
          }
        },
        async cargarDatos(pagina = 1) {
          this.cargando = true;
          this.paginaActual = pagina;
          try {
            const params = new URLSearchParams({
              pagina: pagina,
              limite: this.limiteFilas,
              empresa: this.empresaActiva,
              q: this.filtroBusqueda,
              responsable: this.filtroResponsable,
              plaza: this.filtroPlaza,
              fraccionamiento: this.filtroFraccionamiento,
              rubro: this.filtroRubro,
              semana: this.filtroSemana
            });

            const res = await fetch('/api/pagos?' + params.toString());
            const data = await res.json();
            if (data.status === 'ok') {
              this.registros = data.datos;
              this.totalPaginas = data.total_paginas;
              this.totalFiltrado = data.total_filtrado;
              this.sumaImporte = data.suma_importe;
              this.sumaPagando = data.suma_pagando;
              this.sumaDiferencia = data.suma_diferencia;
            }
          } catch (e) {
            console.error('Error cargando datos paginados:', e);
          } finally {
            this.cargando = false;
          }
        },
        async guardarCambio(item) {
          try {
            await fetch('/api/pagos/actualizar', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                id_fila: item.id_fila,
                CONCILIADO: item.CONCILIADO
              })
            });
          } catch (e) {
            console.error('Error actualizando fila en tiempo real:', e);
          }
        },
        limpiarFiltros() {
          this.filtroBusqueda = '';
          this.filtroFraccionamiento = '';
          this.filtroRubro = '';
          this.filtroSemana = '';
          if (this.perfilUsuario && this.perfilUsuario.rol === 'ADMIN') {
            this.filtroPlaza = '';
            this.filtroResponsable = '';
          } else {
            this.filtroPlaza = this.perfilUsuario ? this.perfilUsuario.plaza : '';
            this.filtroResponsable = this.usuarioActivo;
          }
          this.cargarDatos(1);
        }
      },
      async mounted() {
        if (this.perfilUsuario) {
          this.empresaActiva = this.perfilUsuario.empresa || 'ARYVE';
          this.loginEmpresa = this.empresaActiva;
          if (this.perfilUsuario.rol === 'ADMIN' || this.perfilUsuario.es_admin_funcional) {
            this.filtroResponsable = '';
            this.filtroPlaza = '';
            this.filtroVivoMisOrdenes = false;
          } else {
            this.filtroResponsable = this.usuarioActivo;
            this.filtroPlaza = this.perfilUsuario.plaza || '';
            this.filtroVivoMisOrdenes = true;
          }
        }
        await this.cargarFiltros();
        if (this.usuarioActivo) {
          if (this.vistaActiva === 'ORDENES_VIVO') {
            await this.cargarOrdenesVivo(1);
            await this.cargarProgramacionesSemana();
          } else {
            await this.cargarDatos(1);
          }
        }
        // Auto-refresco en vivo cada 30s para capturar OCs nuevas de Enkontrol
        setInterval(() => {
          if (this.vistaActiva === 'ORDENES_VIVO' && this.paginaVivo === 1 && !this.filtroVivoBusqueda) {
            // Refresco silencioso: actualiza en segundo plano sin parpadeos ni spinners
            this.cargarOrdenesVivo(1, true);
          }
        }, 15000);
      }
    }).mount('#app');
