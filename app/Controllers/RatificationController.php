<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RatificationModel;
use App\Support\CedulaEscolar;
use App\Support\Documento;
use App\Support\DocumentosObligatorios;
use App\Support\Tallas;
use App\Traits\ContactoValidacionTrait;
use App\Traits\FotoSubidaTrait;
use App\Traits\FamiliaresValidacionTrait;
use App\Traits\UbicacionTrait;
use App\Traits\PersonaValidacionTrait;
use App\Traits\ValidacionFechaTrait;
use PDO;
use Throwable;

/**
 * Ratificación de matrícula (gestion_estudiante/ratificacion).
 *
 * Endpoints:
 * - index GET listado con filtros
 * - ver GET detalle de una ratificación (HTML o cuerpo de modal por AJAX)
 * - datosEstudiante GET datos del estudiante para el modal de edición (JSON)
 * - ratificar POST registra la ratificación (JSON si es AJAX)
 * - updateEstudiante POST guarda los datos del estudiante desde el modal (JSON)
 *
 * La ratificación es siempre individual: no hay acción masiva. Lo que toca una matrícula
 * pasa por el modelo, que es donde vive el bloqueo de cupo; este controlador valida y
 * traduce las excepciones de dominio: 409 para un conflicto de estado, 422 para un campo.
*/
class RatificationController extends CrudController
{
    /* PersonaValidacionTrait usa fechaValida() y fechaEnRango(), que viven en
 ValidacionFechaTrait: sin ese trait, validarFechaNacimiento() provoca un error
 "Call to undefined method". EnrollmentController usa ambos por el mismo motivo.*/
    /* validarFamiliares() (FamiliaresValidacionTrait) delega la validación del
 teléfono y el correo en ContactoValidacionTrait. Es el mismo trait que usa
 la inscripción, y por eso las dos pantallas rechazan los mismos números.*/
    use ContactoValidacionTrait;
    use FotoSubidaTrait;
    use PersonaValidacionTrait;
    use ValidacionFechaTrait;
    /* Familiares y cascada de dirección: la misma lectura, validación y escritura
 que usa la inscripción. Ver FamiliaresValidacionTrait y UbicacionTrait:
 no pueden ser dos copias porque las reglas son de negocio.*/
    use FamiliaresValidacionTrait;
    use UbicacionTrait;

    /* La foto va por la misma configuración que en la inscripción: la misma
 carpeta y el mismo prefijo, para que un archivo se lea igual desde los dos
 módulos.*/
    private const FOTO_ESTUDIANTE = [
        'campo'   => 'foto',
        'carpeta' => 'estudiantes',
        'prefijo' => 'estudiante_',
    ];

    protected string $viewPath  = 'gestion_estudiante/ratificacion';
    protected string $routeName = 'ratificacion';

    /** Tipos de sangre admitidos; los mismos del ENUM de ficha_medica.tipo_sangre.*/
    private const TIPOS_SANGRE = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
 * Devuelve el modelo con su tipo concreto.
*/
    private function modelo(): RatificationModel
    {
        /** @var RatificationModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new RatificationModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        $filters = [
            // Casting a (string) para cumplir estrictamente con strict_types
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'grado'   => trim((string) ($_GET['grado'] ?? '')),
            'seccion' => trim((string) ($_GET['seccion'] ?? '')),
            'turno'   => trim((string) ($_GET['turno'] ?? '')),
        ];
        $turno = $this->turnoSecretariaActual();
        if ($turno !== null) {
            $filters['id_turno'] = $turno;
            if ($turno > 0) {
                $turnosAsignados = $this->modelo()->getTurnos($turno);
                $filters['turno'] = $turnosAsignados[0] ?? '';
            }
        }
        return $filters;
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model        = $this->modelo();
        $catFamiliares = $model->getCatalogosFamiliar();
        $anioDestino  = $model->getAnioDestino();

        return [
            'stats'          => $model->getStats($filters['id_turno'] ?? null),
            'ratificaciones' => $items,
            'grados'         => $model->getGrados(),
            'secciones'      => $model->getSecciones(),
            'turnos'         => $model->getTurnos($filters['id_turno'] ?? null),
            /* El año al que se ratifica, con su id: el modal lo muestra fijo (no hay
 nada que elegir) y lo manda en un campo oculto para que el backend sepa
 a qué período se está ratificando en vez de deducirlo otra vez.*/
            'anioEnCurso'    => $anioDestino['nombre'],
            'anioDestino'    => $anioDestino,
            // Las combinaciones del año destino, una sola vez para todos los <select>
            // del modal: el navegador filtra por grado y sección con esta lista, y las
            // secciones que falten se piden por AJAX a ratificacion/secciones.
            'combinaciones'  => $model->getCombinacionesAnioEnCurso($filters['id_turno'] ?? null),

            /* El sub-modal de familiares es el mismo archivo que usa la inscripción
 (inscripcion/modal-agregar-familiar.php) y lee estos catálogos del
 scope de la vista. Se piden aquí y no por AJAX porque son cuatro listas
 cortas que se necesitan antes de abrir el modal: si se pidieran por
 AJAX habría que esperar a que lleguen, y el sub-modal se abre
 enseguida. El catálogo que sí es grande (municipios y parroquias, más de
 mil filas) sigue viniendo por ratificacion/getUbicacion.*/
            'catFamiliares'  => $catFamiliares,
            // El sub-modal lee los estados del scope con la misma forma de lista
            // que usa getEstados() en inscripción: id + nombre.
            'estados'        => $catFamiliares['estados'],
            // El modal reutiliza el paso 1 del estudiante, así que necesita su catálogo.
            'nacionalidades' => $model->getNacionalidades(),
            'minDateEstudiante' => (((int) ($anioDestino['anio_inicio'] ?? date('Y')) ?: (int) date('Y')) - 14) . '-01-01',
            'maxDateEstudiante' => (((int) ($anioDestino['anio_inicio'] ?? date('Y')) ?: (int) date('Y')) - 6) . '-12-31',
        ];
    }

    /**
 * Detalle (solo lectura) de una ratificación ya registrada.
 *
 * Sirve para dos cosas con la misma vista, igual que inscripción: abierta en
 * el navegador es una página, y con la cabecera X-Requested-With devuelve solo
 * el cuerpo del modal (lo decide ver.php). Así el detalle no se escribe dos
 * veces, y el botón de "ver" del listado y un enlace directo muestran lo mismo.
*/
    public function ver(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlash('error', 'Estudiante no válido.');
            $this->redirect('index');
        }
        if (!$this->secretariaTieneAccesoAEstudiante($id)) {
            $this->setFlash('error', 'El estudiante no pertenece a su turno asignado.');
            $this->redirect('index');
        }

        $item = $this->modelo()->getDetalle($id);
        if (!$item) {
            $this->setFlash('error', 'El estudiante solicitado no existe.');
            $this->redirect('index');
        }

        require __DIR__ . "/../Views/{$this->viewPath}/ver.php";
    }

    /**
 * Procesa la ratificación de un estudiante.
 * SOLO acepta peticiones POST por seguridad (previene CSRF).
 *
 * Responde como el resto del sistema: JSON cuando es AJAX (SweetAlert2 desde
 * ratificacion.js) y redirect con flash cuando no lo es, para que el
 * formulario siga sirviendo si el JS no carga.
 *
 * El destino (grado, sección y turno) sí lo elige la secretaria en el modal.
 * El año NO se recibe: es el año escolar en curso, que no se puede cambiar.
*/
    public function ratificar(): void
    {
        $esAjax = esPeticionAjax();

        /* Los filtros del listado solo se arrastran en el camino sin JS. Con AJAX
 no hacen falta: main.js ya los tiene en la URL y refresca el panel.*/
        $filtros = [];
        if (!$esAjax) {
            foreach (['q', 'grado', 'seccion', 'turno', 'page'] as $campo) {
                $valor = trim((string) ($_POST[$campo] ?? ''));
                if ($valor !== '') {
                    $filtros[$campo] = $valor;
                }
            }
        }

        $fallar = function (string $mensaje, int $codigo) use ($esAjax, $filtros): never {
            if ($esAjax) {
                $this->jsonResponse(false, null, $mensaje, $codigo);
            }
            $this->setFlash('error', $mensaje);
            $this->redirect('index', $filtros);
        };

        // 1. Bloqueo de seguridad: Solo aceptar POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $fallar('Acción no permitida por esta vía.', 405);
        }

        // 2. Leer desde $_POST en lugar de $_GET
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $fallar('Estudiante no válido.', 400);
        }
        if (!$this->secretariaTieneAccesoAEstudiante($id)) {
            $fallar('El estudiante no pertenece a su turno asignado.', 403);
        }

        // 3. Manejo de excepciones (try...catch)
        try {
            $destino = [
                'id_grado'        => (int) ($_POST['id_grado'] ?? 0),
                'id_seccion'      => (int) ($_POST['id_seccion'] ?? 0),
                'id_turno'        => (int) ($_POST['id_turno'] ?? 0),
                /* El año al que se ratifica. Viaja en un campo oculto porque el modal
                   lo muestra bloqueado, y un input readonly que además fuera el único
                   campo no se puede leer en el servidor: readonly deshabilita la
                   edición pero no impide que se falsee en el POST. El modelo lo
                   contrasta con el año destino que él resuelve y lo usa para elegir la
                   estructura, así que un año manipulado no llega a escribir nada.*/
                'id_anio_escolar' => (int) ($_POST['id_anio_escolar'] ?? 0),
                // Justificación del salto de grado. El modelo decide si hace falta: solo es
                // obligatoria cuando se adelante más de un nivel.
                'motivo'          => trim((string) ($_POST['motivo'] ?? '')),
            ];
            $turnoSecretaria = $this->turnoSecretariaActual();
            if ($turnoSecretaria !== null && $destino['id_turno'] !== $turnoSecretaria) {
                $fallar('Solo puede ratificar estudiantes en su turno asignado.', 403);
            }
            $resultado = $this->modelo()->ratificar($id, $destino, (int) ($this->idUsuarioActual() ?? 0));
        } catch (Throwable $e) {
            // El mensaje al usuario nunca es el detalle técnico: ese va al log.
            error_log('Error en RatificationController::ratificar: ' . $e->getMessage());
            $fallar('Ocurrió un error interno al intentar ratificar al estudiante.', 500);
        }

        if (!$resultado['ok']) {
            /* 409: el modelo responde así cuando el estado ya cambió (alguien lo
 ratificó antes, o se le aprobó el retiro). No es un fallo del
 proceso, es que la pantalla quedó desfasada.*/
            $fallar($resultado['mensaje'], 409);
        }

        if ($esAjax) {
            $this->jsonResponse(true, null, $resultado['mensaje']);
        }

        $this->setFlash('success', $resultado['mensaje']);
        $this->redirect('index', $filtros);
    }

    /**
 * Datos del estudiante para el modal de edición (ratificacion/datosEstudiante,
 * GET, secretaria). Responde JSON: lo llena ratificacion.js.
 *
 * Se llama antes de abrir el modal porque el formulario trae los pasos del
 * wizard de inscripción, que salen vacíos: sin esto se editaría sobre campos
 * en blanco y el primer guardado borraría el nombre.
 *
 * Incluye los familiares y el contacto/domicilio del representante legal, que
 * son los otros dos bloques editables de esta pantalla.
*/
    public function datosEstudiante(): void
    {
        $idEstudiante = (int) ($_GET['id'] ?? 0);
        if ($idEstudiante <= 0) {
            $this->jsonResponse(false, null, 'Estudiante no válido.', 400);
        }
        if (!$this->secretariaTieneAccesoAEstudiante($idEstudiante)) {
            $this->jsonResponse(false, null, 'El estudiante no pertenece a su turno asignado.', 403);
        }

        try {
            $datos = $this->modelo()->getEstudianteEditable($idEstudiante);
            if ($datos === null) {
                $this->jsonResponse(false, null, 'El estudiante no existe.', 404);
            }

            $this->jsonResponse(true, [
                'estudiante'      => $datos,
                'familiares'      => $this->modelo()->getFamiliares($idEstudiante),
                'representante'   => $this->modelo()->getRepresentanteLegal($idEstudiante),
                'catalogos'       => $this->modelo()->getCatalogosFamiliar(),
                /* Los recaudos vienen en la misma llamada y no en una aparte: el modal de
 ratificar ya pide los datos de este estudiante, y pedir los documentos por
 otro lado duplicaría el control de turno y abriría la puerta a que la
 tabla mostrara los recaudos de un alumno y el nombre de otro.*/
                'recaudos'        => (new \App\Models\EnrollmentModel($this->conexion()))
                                        ->getRecaudosDeEstudiante($idEstudiante),
            ]);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Datos del estudiante');
        }
    }

    /**
 * Secciones disponibles para un grado en el año escolar destino.
 * Ruta: ratificacion/secciones?grado=&anio= (GET, JSON). La recargan el selector de sección
 * del modal cuando la secretaria cambia el grado.
 *
 * Va por AJAX y no se resuelve todo en el navegador porque el año es fijo pero el grado
 * no, y el conjunto de secciones depende de los dos: filtrar la lista de combinaciones que
 * ya está en la página obligaría a traerla entera para usar una parte.
*/
    public function secciones(): void
    {
        $anioDestino = $this->modelo()->getAnioDestino();
        $anioPedido = (int) ($_GET['anio'] ?? 0);

        if ($anioPedido > 0 && $anioPedido !== $anioDestino['id']) {
            $this->jsonResponse(
                false,
                null,
                'El año del formulario no es el año escolar de destino.',
                409
            );
        }

        $idGrado = (int) ($_GET['grado'] ?? 0);
        if ($idGrado <= 0) {
            $this->jsonResponse(false, null, 'Seleccione un grado.', 422);
        }

        $this->jsonResponse(true, $this->modelo()->getSeccionesPorGrado($idGrado, $anioDestino['id']));
    }

    /* Busca una persona por cédula para precargar al familiar en el sub-modal. Si el documento ya
 está en otra persona, el servidor lo dice y el JS limpia lo cargado: son datos ajenos.*/
    public function buscarPersona(): void
    {
        $documento = Documento::normalizar(trim((string) ($_GET['documento'] ?? '')));

        if ($documento === '') {
            $this->jsonResponse(false, null, 'Indique la cédula a buscar.', 422);
        }

        if (!Documento::esCedula($documento)) {
            $this->jsonResponse(false, null, 'La cédula debe tener entre 7 y 8 dígitos.', 422);
        }

        try {
            /* El estudiante SÍ está contextuando la búsqueda: el nivel de instrucción
 y la ocupación de un familiar se leen de la relación con ese menor, no
 de la persona.*/
            $idEstudiante = (int) ($_GET['id_estudiante'] ?? 0);
            $this->jsonResponse(true, $this->modelo()->buscarPersonaPorDocumento(
                $documento,
                $idEstudiante > 0 ? $idEstudiante : null
            ));
        } catch (Throwable $e) {
            $this->jsonError($e, 'Búsqueda de familiar');
        }
    }

    /**
 * Municipios y parroquias de un estado, para la cascada de dirección del
 * representante (ratificacion/getUbicacion?tipo=municipio|parroquia).
 *
 * Es la misma ruta que usa la inscripción (inscripciones/getUbicacion): el
 * trait UbicacionTrait arma la respuesta, así que los dos módulos preguntan
 * exactamente por los mismos municipios y parroquias.
*/
    public function getUbicacion(): void
    {
        $this->getUbicacionComun('Ratificación');
    }

    /* Actualiza los datos del estudiante editados desde el modal. Solo llega lo que la secretaria
 puede cambiar; la identidad y el grado no viajan porque van bloqueados.*/
    public function updateEstudiante(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, null, 'Método no permitido.', 405);
        }

        $idEstudiante = (int) ($_POST['id_estudiante'] ?? 0);
        if ($idEstudiante <= 0) {
            $this->jsonResponse(false, null, 'Estudiante no válido.', 400);
        }
        if (!$this->secretariaTieneAccesoAEstudiante($idEstudiante)) {
            $this->jsonResponse(false, null, 'El estudiante no pertenece a su turno asignado.', 403);
        }

        $actual = $this->modelo()->getEstudianteEditable($idEstudiante);
        if ($actual === null) {
            $this->jsonResponse(false, null, 'El estudiante no existe.', 404);
        }

/* El documento NO se regenera: el número ya está asignado y es UNIQUE.
 Por eso no se vuelve a pasar por la cédula escolar, que le asignaría
 uno nuevo; solo se normaliza.*/
        $crudo = fn (string $clave): string => $this->normalizarEspacios((string) ($_POST[$clave] ?? ''));

        $tipo = $this->normalizarEspacios((string) ($_POST['tipo_documento'] ?? ''));
        $documento = $crudo('numero_documento');

    /* El limpiado depende del tipo, como en App\Support\Documento: en una cédula escolar la V es
 parte del número y quitarla haría que el guardado no encontrara la ficha.*/
        if ($tipo === 'CE') {
            $documento = mb_strtoupper((string) preg_replace('/\s+/', '', $documento), 'UTF-8');
        } else {
            $documento = Documento::normalizar($documento);
        }

        $data = [
            'persona' => [
                'tipo_documento'   => $tipo,
                'numero_documento' => $documento,
                'fecha_nacimiento' => $crudo('fecha_nacimiento'),
                'primer_nombre'    => $this->capitalizarTitulo($crudo('primer_nombre')),
                'segundo_nombre'   => $this->capitalizarTitulo($crudo('segundo_nombre')),
                'primer_apellido'  => $this->capitalizarTitulo($crudo('primer_apellido')),
                'segundo_apellido' => $this->capitalizarTitulo($crudo('segundo_apellido')),
                'sexo'             => $crudo('sexo'),
                /* Esta ruta edita al estudiante, no a un familiar: es el único caso en que
                   se guardan los tres niveles. El municipio se deduce de la parroquia y no
                   se persiste. */
                'id_estado_nacimiento'    => ($crudo('nacimiento_extranjero') !== '') ? null : (((int) $crudo('id_estado_nacimiento')) ?: null),
                'id_nacionalidad'         => ((int) $crudo('id_nacionalidad')) ?: null,
                'id_parroquia_nacimiento' => ($crudo('nacimiento_extranjero') !== '') ? null : (((int) $crudo('id_parroquia_nacimiento')) ?: null),
                'lugar_nacimiento_extranjero' => $this->vacioComoNull($crudo('lugar_nacimiento_extranjero')),
                'nacimiento_extranjero' => ($crudo('nacimiento_extranjero') !== '') ? 1 : 0,
            ],
            'talla_uniforme' => [
                'camisa'        => $this->aMayusculas($crudo('talla_camisa')),
                'pantalon'      => $this->aMayusculas($crudo('talla_pantalon')),
                'zapato'        => $this->aMayusculas($crudo('talla_zapato')),
                'observaciones' => $this->vacioComoNull($this->capitalizarOracion($crudo('talla_observaciones'))),
            ],
            'ficha_medica' => [
                'condiciones_especiales' => $this->vacioComoNull($this->capitalizarOracion($crudo('condiciones_especiales'))),
                'medicamentos'            => $this->vacioComoNull($this->capitalizarOracion($crudo('medicamentos'))),
                'observaciones'           => $this->vacioComoNull($this->capitalizarOracion($crudo('observaciones_medicas'))),
                'alergias'                => $this->vacioComoNull($this->capitalizarOracion($crudo('alergias'))),
                'enfermedades'            => $this->vacioComoNull($this->capitalizarOracion($crudo('enfermedades'))),
                'tipo_sangre'             => $this->vacioComoNull($this->aMayusculas($crudo('tipo_sangre'))),
            ],
            /* Los familiares llegan en el mismo `familiares_json` que usa la
 inscripción, y se leen con el mismo decodificador. La validación
 va con exigirRepresentante = false: el alumno ya está inscripto y
 su representante ya existe en la base, así que en una edición no
 tiene sentido exigir que se vuelva a declarar quién es.*/
            'familiares'   => $this->prepararFamiliares(
                $idEstudiante,
                $this->decodificarFamiliares((string) ($_POST['familiares_json'] ?? '[]'))
            ),
            /* Los recaudos van en la pestaña "Recaudos" del mismo modal y salen con el
 mismo botón Guardar Cambios: son datos de la matrícula que se corrigen
 juntas, no un trámite aparte con su propio guardado.*/
            'recaudos'     => $this->decodificarRecaudos($_POST['recaudos'] ?? []),
            // Solo lo necesita la bitácora de los recaudos; no se guarda en ningún sitio.
            'id_usuario'   => (int) ($this->idUsuarioActual() ?? 0),
        ];

        // Regla institucional: cambio de fecha_nacimiento con Cédula Escolar (CE)
        $fechaActual = (string) ($actual['fecha_nacimiento'] ?? '');
        $fechaNueva  = (string) ($data['persona']['fecha_nacimiento'] ?? '');
        $anioActual  = (int) substr($fechaActual, 0, 4);
        $anioNuevo   = (int) substr($fechaNueva, 0, 4);
        $esCE        = ($actual['tipo_documento'] === 'CE');
        $cambiaAnio  = ($esCE && $anioActual > 0 && $anioNuevo > 0 && $anioActual !== $anioNuevo);

        if ($cambiaAnio) {
            $rolSesion   = sigde_rol_clave($_SESSION['rol'] ?? '');
            $esDireccion = in_array($rolSesion, ['directivo', 'admin'], true);

            if (!$esDireccion) {
                $this->jsonResponse(
                    false,
                    ['fecha_nacimiento' => 'Solo los usuarios de Dirección pueden autorizar el cambio de año de nacimiento para un estudiante con Cédula Escolar.'],
                    'Acción no autorizada.',
                    422
                );
            }

            $motivo = trim((string) ($_POST['motivo_cambio_anio'] ?? ''));
            if ($motivo === '') {
                $this->jsonResponse(
                    false,
                    ['motivo_cambio_anio' => 'Debe indicar el motivo del cambio de año de nacimiento.'],
                    'Motivo obligatorio.',
                    422
                );
            }
            if (mb_strlen($motivo, 'UTF-8') > 40) {
                $this->jsonResponse(
                    false,
                    ['motivo_cambio_anio' => 'El motivo no puede exceder 40 caracteres.'],
                    'Motivo demasiado largo.',
                    422
                );
            }

            $familiaresActuales = $this->modelo()->getFamiliares($idEstudiante);
            $rep = $this->representanteLegal($data['familiares']) ?? $this->representanteLegal($familiaresActuales);
            $cedulaRep = (string) ($rep['numero_documento'] ?? '');
            $letraRep  = (string) ($rep['tipo_documento'] ?? 'V');

            if ($cedulaRep === '') {
                $this->jsonResponse(
                    false,
                    ['fecha_nacimiento' => 'No se puede regenerar la Cédula Escolar porque el estudiante no tiene un representante legal con documento válido.'],
                    'Representante no válido.',
                    422
                );
            }

            $nuevoOrden = $this->modelo()->proximoOrdenHermano($cedulaRep, $fechaNueva, $idEstudiante);
            if ($nuevoOrden > 9) {
                $this->jsonResponse(
                    false,
                    ['fecha_nacimiento' => 'No es posible asignar una Cédula Escolar: se superó el límite máximo de 9 hermanos con Cédula Escolar para este año de nacimiento.'],
                    'Límite de hermanos alcanzado.',
                    422
                );
            }

            try {
                $nuevaCE = CedulaEscolar::generar($nuevoOrden, $fechaNueva, $cedulaRep, $letraRep);
            } catch (\InvalidArgumentException $e) {
                $this->jsonResponse(false, ['fecha_nacimiento' => $e->getMessage()], 'Error en cédula escolar.', 422);
            }

            $data['persona']['numero_documento'] = $nuevaCE;
            $data['cambio_ce'] = [
                'ce_anterior' => $actual['numero_documento'],
                'ce_nueva'    => $nuevaCE,
                'motivo'      => $motivo,
            ];
        }

        $errores = $this->validarDatosEstudiante($data, $idEstudiante);
        $this->validarFamiliares($data['familiares'], $errores, false);

        /* Regla 2, no retroactiva: solo se evalúa si cambió el representante o su
           parentesco. Un expediente viejo con representante distinto de madre/padre
           y sin permiso LOPNNA no se bloquea al guardar cualquier otro dato. */
        $servicioDocumentos = new DocumentosObligatorios($this->conexion());
        $familiaresActuales = $this->modelo()->getFamiliares($idEstudiante);
        $representanteActual = $this->representanteLegal($familiaresActuales);
        $representanteNuevo = $this->representanteLegal($data['familiares']);
        $recaudosParaValidar = $data['recaudos'];
        foreach ($data['recaudos'] as $k => $est) {
            if (preg_match('/^tipo_([1-9]\d*)(?:_.*)?$/', (string) $k, $m)) {
                $claveBase = 'tipo_' . $m[1];
                if (!isset($recaudosParaValidar[$claveBase]) || !empty($est['consignado'])) {
                    $recaudosParaValidar[$claveBase] = $est;
                }
            }
        }
        $recaudosGuardados = (new \App\Models\EnrollmentModel($this->conexion()))
            ->getRecaudosDeEstudiante($idEstudiante);
        foreach ($recaudosGuardados as $recaudoGuardado) {
            $clave = (string) ($recaudoGuardado['id'] ?? '');
            $estado = $data['recaudos'][$clave] ?? null;
            if (!is_array($estado)) {
                continue;
            }
            $claveTipo = 'tipo_' . (int) $recaudoGuardado['id_tipo_documento'];
            if (!isset($recaudosParaValidar[$claveTipo])
                || !empty($estado['consignado'])) {
                $recaudosParaValidar[$claveTipo] = $estado;
            }
        }

        $cambioRepresentante = $this->cambioElRepresentante($representanteActual, $representanteNuevo, $servicioDocumentos);
        if ($cambioRepresentante) {
            // El permiso LOPNNA guardado era del representante anterior: el nuevo debe consignar el suyo si aplica
            $estadoLopnnaEnviado = $data['recaudos']['tipo_12'] ?? null;
            if ($estadoLopnnaEnviado === null) {
                foreach ($data['recaudos'] as $claveRec => $valRec) {
                    if (str_starts_with((string) $claveRec, 'tipo_12')) {
                        $estadoLopnnaEnviado = $valRec;
                        break;
                    }
                }
            }
            $recaudosParaValidar['tipo_12'] = $estadoLopnnaEnviado ?? ['consignado' => 0];

            $faltantes = $servicioDocumentos->faltantesPermisoLopnna(
                $representanteNuevo,
                $recaudosParaValidar
            );

            if ($faltantes !== []) {
                $errores['recaudos'] = implode(' ', array_column($faltantes, 'mensaje'));
            }
        }

        /* Regla 3, no retroactiva: solo se exige a las personas que se autorizan
           para retirar en este guardado. Una persona que ya estaba autorizada no
           se bloquea si su expediente viejo no tiene los documentos. Los
           documentos se marcan en la pestaña de recaudos (`estudiante_documento`). */
        $faltantesRetiro = $this->faltantesRetiroNuevos(
            $data['familiares'],
            $familiaresActuales,
            $recaudosParaValidar,
            $servicioDocumentos
        );

        if ($faltantesRetiro !== []) {
            $previo = trim((string) ($errores['recaudos'] ?? ''));
            $errores['recaudos'] = trim(
                $previo . ' ' . implode(' ', array_column($faltantesRetiro, 'mensaje'))
            );
        }

        if ($errores !== []) {
            $this->jsonResponse(false, $errores, 'Hay errores en el formulario.', 422);
        }

        /* La foto se procesa DESPUÉS de validar, no antes: si el formulario tiene
           errores no se quiere dejar un archivo subido que nadie va a usar. */
        $fotoAnterior = (string) ($actual['foto'] ?? '');
        $fotoNueva    = $this->procesarFotoSubida(self::FOTO_ESTUDIANTE);
        $quitarFoto   = (string) ($_POST['eliminar_foto'] ?? '') === '1';

        if ($fotoNueva !== null) {
            $data['persona']['foto'] = $fotoNueva;
        } elseif ($quitarFoto) {
            $data['persona']['foto'] = null;
        }

        try {
            // Los familiares van dentro de la misma transacción que la persona:
            // guardar medio formulario es peor que no guardar nada.
            $this->modelo()->actualizarEstudiante($idEstudiante, $data, $data['familiares']);
        } catch (\PDOException $e) {
            if ($fotoNueva !== null) {
                $this->borrarFoto($fotoNueva);
            }
            $codigo = (int) ($e->errorInfo[1] ?? 0);
            if ($e->getCode() === '23000' || $codigo === 1062) {
                $this->jsonResponse(
                    false,
                    ['numero_documento' => 'La Cédula Escolar generada ya se encuentra registrada para otro estudiante.'],
                    'Conflicto de documento.',
                    422
                );
            }
            $this->jsonError($e, 'Actualización del estudiante');
        } catch (Throwable $e) {
            // Si se subió una foto y el guardado falló, se borra: si no queda
            // apuntando a un archivo huérfano.
            if ($fotoNueva !== null) {
                $this->borrarFoto($fotoNueva);
            }
            $this->jsonError($e, 'Actualización del estudiante');
        }

        if (($fotoNueva !== null || $quitarFoto) && $fotoAnterior !== '') {
            $this->borrarFoto($fotoAnterior);
        }

        $this->jsonResponse(true, null, 'Datos del estudiante actualizados correctamente.');
    }

    /**
 * Normaliza los recaudos que llegan en la pestaña del modal.
 *
 * El mismo criterio que en la inscripción: aquí se limpian los tipos, pero decidir si
 * un id es válido lo hace el modelo contra el catálogo. El texto pasa por
 * normalizarEspacios(), que quita HTML: es un campo que escribe la secretaria y se
 * vuelve a mostrar en la ficha, y sin limpiar un "<script>" quedaría guardado.
 *
 * @return array<int|string, array{consignado: int, observacion: string}>
*/
    private function decodificarRecaudos($crudo): array
    {
        if (!is_array($crudo)) {
            return [];
        }

        $recaudos = [];
        foreach ($crudo as $id => $estado) {
            $id = (string) $id;
            if (!preg_match('/^(?:[1-9]\d*|tipo_[1-9]\d*(?:_[A-Za-z0-9]+)?)$/', $id) || !is_array($estado)) {
                continue;
            }
            $consignado = $estado['consignado'] ?? 0;
            $recaudos[$id] = [
                'consignado'  => in_array($consignado, [1, '1', true], true) ? 1 : 0,
                'observacion' => mb_substr(
                    $this->normalizarEspacios((string) ($estado['observacion'] ?? '')),
                    0,
                    255
                ),
            ];
        }

        return $recaudos;
    }

    /**
 * Validaciones de los datos editables. Reutiliza PersonaValidacionTrait para
 * el documento (con $ignorarId = id_persona, sin lo cual el alumno se
 * detectaría como duplicado de sí mismo) y la fecha, que en un estudiante es
 * de 5 a 14 años.
 *
 * @return array<string, string> campo => mensaje
*/
    private function validarDatosEstudiante(array $data, int $idEstudiante): array
    {
        $errores = [];
        $p = $data['persona'];

        $estudianteActual = $this->modelo()->getEstudianteEditable($idEstudiante);
        $documentoOriginal = (string) ($estudianteActual['numero_documento'] ?? '');

        $this->validarDocumento(
            ['tipo_documento' => $p['tipo_documento'], 'numero_documento' => $p['numero_documento']],
            $errores,
            ['V', 'E', 'CE'],
            (int) ($estudianteActual['id_persona'] ?? 0),
            'Ya existe un estudiante con ese número de documento.',
            $documentoOriginal
        );

        $anioDestino = $this->modelo()->getAnioDestino();
        $inicioAnio = (int) ($anioDestino['anio_inicio'] ?? 0);
        if ($inicioAnio <= 0) {
            $inicioAnio = (int) date('Y');
        }
        // Regla no retroactiva: si la fecha no cambió, no se bloquea a un alumno existente al editar otros campos
        if ($p['fecha_nacimiento'] !== ($estudianteActual['fecha_nacimiento'] ?? '')) {
            $this->validarFechaNacimientoEstudiante($p, $errores, $inicioAnio);
        }
        /* Este método solo se llama al editar un alumno existente. Los registros
        anteriores pueden tener el lugar de nacimiento y la nacionalidad en NULL, por
        lo que solo se validan cuando llegan informados. */
        $this->validarLugarNacimiento($p, $errores, true, true);
        $this->validarNacionalidad($p, $errores, true);

        $this->validarNombresApellidos($p, $errores);

        if (!in_array($p['sexo'], ['F', 'M'], true)) {
            $errores['sexo'] = 'El sexo seleccionado no es válido.';
        }

        $errores = array_merge($errores, Tallas::errores($data['talla_uniforme'] ?? []));

        /* Mismo control que en la inscripción: ficha_medica.tipo_sangre es un ENUM y un valor
         fuera de la lista lo trunca la base en silencio dejando un 500 sin explicación.*/
        if ($data['ficha_medica']['tipo_sangre'] !== null
            && !in_array($data['ficha_medica']['tipo_sangre'], self::TIPOS_SANGRE, true)) {
            $errores['tipo_sangre'] = 'Seleccione un tipo de sangre de la lista.';
        }

        return $errores;
    }

    /**
 * Deja el contacto y el domicilio de los familiares que NO son el representante
 * legal como estaban guardados, y también los de quien tiene ficha propia en el
 * plantel.
 *
 * Por qué hace falta esto, si el JS ya apaga esos campos: porque el navegador no
 * es una frontera. Un formulario se puede mandar con `disabled` o con el
 * inspector abierto, y con un `curl` a mano. Si el servidor se limitara a validar
 * lo que le llega, cualquiera podría cambiar el teléfono de un familiar cualquiera
 * desde esta pantalla, y quedaría un teléfono que nadie registró.
 *
 * La segunda mitad es más delicate. `contacto` y `direccion` tienen
 * UNIQUE(id_persona): hay una sola fila por persona. Quien es a la vez empleado
 * y representante comparte teléfono y domicilio con su expediente laboral, así
 * que corregir el teléfono del representante desde aquí modificaba el registro de
 * personal de un empleado. En la base actual es el caso normal, no la excepción:
 * 11 de las 12 relaciones pertenecen a personas empleadas, tres con cuenta de
 * usuario (dos administradores y una directiva).
 *
 * Por eso el dato de una persona se considera único y se edita en su propio
 * módulo, no en la pantalla de matrícula. (Crear una tabla de contactos por rol
 * resolvería el caso de "teléfono del trabajo distinto del celular", pero el
 * negocio todavía no distingue esos dos datos, así que no se paga una migración
 * por una distinción que nadie ha pedido.)
 *
 * Solo se toca lo que YA EXISTE: un familiar nuevo (cédula que no está en la
 * base) conserva el contacto que la secretaria escribió, porque no hay nada
 * guardado que preservar y esa persona sí se registra con sus datos.
 *
 * @param array<int, array<string, mixed>> $familiares Ya decodificados.
 * @return array<int, array<string, mixed>>
*/
    private function prepararFamiliares(int $idEstudiante, array $familiares): array
    {
        if ($familiares === []) {
            return [];
        }

        /* Indexados por dígitos de documento, que es como el modelo identifica a la
 persona (FamiliaresModelTrait::buscarOCrearPersonaFamiliar). El payload no
 lleva id_persona y no debe empezar a depender de él: el número es lo que el
 formulario muestra y lo que la secretaria conoce.*/
        $guardados = [];
        foreach ($this->modelo()->getFamiliares($idEstudiante) as $familiar) {
            $documento = preg_replace('/\D/', '', (string) ($familiar['numero_documento'] ?? ''));
            if ($documento !== '') {
                $guardados[$documento] = $familiar;
            }
        }

        /* Las personas con ficha propia (empleado o alumno del mismo plantel).*/
        $conPropiaFicha = $this->modelo()->personasConPropiaFicha(
            array_map(static fn (array $f): int => (int) ($f['id_persona'] ?? 0), $guardados)
        );

        foreach ($familiares as $i => $familiar) {
            $documento = preg_replace('/\D/', '', (string) ($familiar['numero_documento'] ?? ''));
            $existe = $documento !== '' && isset($guardados[$documento]);

            if (!$existe) {
                continue;   // familiar nuevo: no hay nada que preservar
            }

            /* Si no se enviaron datos de contacto o vienen vacíos pero ya existían guardados, preservar los guardados.
               Si se enviaron nuevos datos de teléfono o dirección, se respetan para guardarlos. */
            $telEnviado = trim((string) ($familiar['contacto']['numero'] ?? ''));
            if ($telEnviado === '' && !empty($guardados[$documento]['contacto']['numero'])) {
                $familiares[$i]['contacto'] = $guardados[$documento]['contacto'];
            }

            $parroquiaEnviada = (int) ($familiar['direccion']['id_parroquia'] ?? 0);
            if ($parroquiaEnviada <= 0 && !empty($guardados[$documento]['direccion']['id_parroquia'])) {
                $familiares[$i]['direccion'] = $guardados[$documento]['direccion'];
            }

            $ocuEnviada = trim((string) ($familiar['ocupacion'] ?? ($familiar['profesion'] ?? '')));
            if ($ocuEnviada === '' && !empty($guardados[$documento]['ocupacion'])) {
                $familiares[$i]['ocupacion'] = $guardados[$documento]['ocupacion'];
                $familiares[$i]['profesion'] = $guardados[$documento]['ocupacion'];
            }
        }

        return $familiares;
    }

    /**
     * Regla 3 para una edición: solo valida a quien se autoriza ahora.
     *
     * Si la persona ya estaba autorizada, no se le exigen los documentos: son
     * expedientes anteriores a la regla y no se pueden bloquear hacia atrás. Si es
     * nueva o pasa de no autorizada a autorizada, sí se valida.
     *
     * @param array<int, array<string, mixed>> $nuevos
     * @param array<int, array<string, mixed>> $actuales
     * @return array<int, array{codigo: string, mensaje: string}>
     */
    private function faltantesRetiroNuevos(
        array $nuevos,
        array $actuales,
        array $recaudos,
        DocumentosObligatorios $servicio
    ): array {
        $porDocumento = [];
        foreach ($actuales as $actual) {
            $documento = (string) preg_replace('/\D/', '', (string) ($actual['numero_documento'] ?? ''));
            if ($documento !== '') {
                $porDocumento[$documento] = $actual;
            }
        }

        foreach ($nuevos as $nuevo) {
            if (!$servicio->exigeDocumentosRetiro($nuevo)) {
                continue;
            }

            $doc = (string) preg_replace('/\D/', '', (string) ($nuevo['numero_documento'] ?? ''));
            // Si la persona ya estaba autorizada en el expediente previo, no se bloquea (regla no retroactiva)
            if ($doc !== '' && isset($porDocumento[$doc]) && $servicio->exigeDocumentosRetiro($porDocumento[$doc])) {
                continue;
            }
            $claveRetiro = 'tipo_10_' . $doc;
            $claveCedula = 'tipo_11_' . $doc;

            $consignadoRetiro = false;
            $consignadoCedula = false;

            if (isset($recaudos[$claveRetiro]) && is_array($recaudos[$claveRetiro])) {
                $consignadoRetiro = !empty($recaudos[$claveRetiro]['consignado']);
            } elseif (isset($recaudos['tipo_10']) && is_array($recaudos['tipo_10'])) {
                $consignadoRetiro = !empty($recaudos['tipo_10']['consignado']);
            } elseif (isset($recaudos[10]) && is_array($recaudos[10])) {
                $consignadoRetiro = !empty($recaudos[10]['consignado']);
            }

            if (isset($recaudos[$claveCedula]) && is_array($recaudos[$claveCedula])) {
                $consignadoCedula = !empty($recaudos[$claveCedula]['consignado']);
            } elseif (isset($recaudos['tipo_11']) && is_array($recaudos['tipo_11'])) {
                $consignadoCedula = !empty($recaudos['tipo_11']['consignado']);
            } elseif (isset($recaudos[11]) && is_array($recaudos[11])) {
                $consignadoCedula = !empty($recaudos[11]['consignado']);
            }

            $nombrePersona = trim(($nuevo['primer_nombre'] ?? '') . ' ' . ($nuevo['primer_apellido'] ?? ''));
            $faltantes = [];
            if (!$consignadoRetiro) {
                $faltantes[] = [
                    'codigo'  => 'autorizacion_retiro',
                    'mensaje' => 'Falta la autorización de retiro de ' . $nombrePersona . '.',
                ];
            }
            if (!$consignadoCedula) {
                $faltantes[] = [
                    'codigo'  => 'cedula_autorizada',
                    'mensaje' => 'Falta la cédula de la persona autorizada (' . $nombrePersona . ').',
                ];
            }
            if ($faltantes !== []) {
                return $faltantes;
            }
        }

        return [];
    }

    /**
     * ¿Cambió el representante legal o su parentesco?
     *
     * Se compara por documento y por código de parentesco, no por nombre: el
     * nombre del catálogo se puede corregir sin que cambie el vínculo. Si el
     * payload no trae representante, la validación de familiares ya reporta la
     * falta y aquí no se inventa un cambio.
     */
    private function cambioElRepresentante(
        ?array $actual,
        ?array $nuevo,
        DocumentosObligatorios $servicio
    ): bool {
        if ($nuevo === null) {
            return false;
        }
        if ($actual === null) {
            return true;
        }

        $documento = static fn (array $familiar): string =>
            (string) preg_replace('/\D/', '', (string) ($familiar['numero_documento'] ?? ''));

        if ($documento($actual) !== $documento($nuevo)) {
            return true;
        }

        return $servicio->codigoParentesco((string) ($actual['parentesco'] ?? ''))
            !== $servicio->codigoParentesco((string) ($nuevo['parentesco'] ?? ''));
    }

    /** Vacío a null, como en EnrollmentController (texto libre opcional).*/
    private function vacioComoNull(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }
}