<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\DocumentoDuplicadoException;
use App\Http\BinaryResponse;
use App\Interfaces\Creatable;
use App\Interfaces\Updatable;
use App\Models\AuditLogModel;
use App\Models\EmpleadoModel;
use App\Models\InstitucionModel;
use App\Models\ReposoEmpleadoModel;
use App\Models\TituloModel;
use App\Support\Configuracion;
use App\Support\FichaEmpleadoPdf;
use App\Support\Instituciones;
use App\Support\Documento;
use App\Support\Validator;
use App\Traits\ContactoValidacionTrait;
use App\Traits\DireccionValidacionTrait;
use App\Traits\FotoSubidaTrait;
use App\Traits\InstitucionTrait;
use App\Traits\PersonaValidacionTrait;
use App\Traits\ReposoEmpleadoTrait;
use App\Traits\UbicacionTrait;
use App\Traits\TextoTrait;
use App\Traits\ValidacionFechaTrait;
use PDO;
use RuntimeException;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * Controlador de Personal > Empleados (todo el CRUD va por modales).
 * Hereda index(); no hay delete (405): se desactiva.
 * Sobrescribe toggleStatus() para que todo cambio pase por cambiarEstado().
 * Sobrescribe store()/update() para distinguir la cédula duplicada.
*/
class EmpleadoController extends CrudController
{
    use PersonaValidacionTrait;
    use ValidacionFechaTrait;
    use ContactoValidacionTrait;
    use DireccionValidacionTrait;
    use FotoSubidaTrait;
    use TextoTrait;
    use InstitucionTrait;
    use UbicacionTrait;
    /* Reposos (registro, edición e historial). Va aparte y no como código suelto aquí porque
     son tres acciones con su propia transacción, su bitácora y su validación de solapamiento:
     mezcladas entre store() y update() taparían el flujo de alta de empleados, que es lo que
     no hay que tocar. Ver ReposoEmpleadoTrait. */
    use ReposoEmpleadoTrait;

    /** Opciones de FotoSubidaTrait para las fotos de empleados.*/
    private const FOTO_EMPLEADO = [
        'campo'   => 'foto',
        'carpeta' => 'empleados',
        'prefijo' => 'empleado_',
    ];

    protected string $viewPath  = 'empleados';
    protected string $routeName = 'empleado';

    /** Crea el modelo del módulo.*/
    protected function createModel(PDO $pdo): object
    {
        return new EmpleadoModel($pdo);
    }

    /**
 * Filtros del listado (q, cargo, estado) leídos de la URL.
 * Solo deja pasar cargo numérico y estado activo/inactivo.
 *
 * @return array{q: string, cargo: string, estado: string}
*/
    protected function getFiltersFromRequest(): array
    {
        $cargo  = (string) ($_GET['cargo'] ?? '');
        $estado = (string) ($_GET['estado'] ?? '');

        return [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'cargo'  => ctype_digit($cargo) ? $cargo : '',
            'estado' => in_array($estado, ['activo', 'inactivo'], true) ? $estado : '',
        ];
    }

    /**
 * Variables extra de la vista: filas como $empleados, stats, cargos
 * y catálogos de los modales.
*/
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model     = $this->modelo();
        $catalogos = $model->getCatalogosFormulario();

        return [
            'empleados'        => $items,
            'stats'            => $model->getEstadisticas(),
            'cargos'           => $model->getCargos(),
            'gradosAcademicos' => $catalogos['grados_academicos'] ?? [],
            'titulos'          => $catalogos['titulos'] ?? [],
            'instituciones'    => $catalogos['instituciones'] ?? [],
            /* Antes venía de la tabla tipo_institucion, que trae
               id_tipo_institucion + nombre, mientras el modal lee $tipo['tipo']:
               por eso las opciones salían con value vacío. El enum es quien
               sabe qué tipos hay y en qué formato los espera el modal. */
            'tiposInstitucion' => (new InstitucionModel($this->conexion()))
                ->tiposFormulario([Instituciones::TIPO_TITULO_ACADEMICO]),
            'estadosGeo'       => (new InstitucionModel($this->conexion()))->getEstadosGeo(),
            'estados'          => $catalogos['estados'] ?? [],
            'turnos'           => $catalogos['turnos'] ?? [],
            /* El <select> de nacionalidad del paso 1. */
            'nacionalidades'   => $model->getNacionalidades(),
            'puedeGestionar'   => sigde_rol_clave($_SESSION['rol'] ?? '') === 'directivo',
        ];
    }

    /**
 * Sin página de creación: el alta es el wizard del listado.
 * Ruta: empleados/create (GET).
*/
    public function create(): void
    {
        $this->redirect('index');
    }

    /**
 * Datos de un empleado en JSON (modales Ver y Editar).
 * Ruta: empleados/show?id=... (GET). Responde 400, 404 o 200.
*/
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        try {
            $empleado = $this->modelo()->getById($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if (!$empleado) {
            $this->jsonResponse(false, null, 'El empleado solicitado no existe.', 404);
        }

        $this->jsonResponse(true, $empleado);
    }

    /**
 * Editar carga los mismos datos que Ver, así que reutiliza show().
 * Ruta: empleados/edit?id=... (GET).
*/
    public function edit(): void
    {
        $this->show();
    }

    /**
 * Opciones de la cascada estado > municipio > parroquia (paso 3).
 * Ruta: empleados/getUbicacion?tipo=&id= (GET, JSON).
*/
    public function getUbicacion(): void
    {
        $this->getUbicacionComun('Empleados');
    }

    /**
 * Alta rápida de un título desde el paso 5 del wizard (catálogo `titulo`).
 * Ruta: empleados/guardarTitulo (POST, JSON).
 * Responde 422 si faltan datos, 409 si ya existe, 500 si falla.
*/
    public function guardarTitulo(): void
    {
        $nombre  = $this->capitalizarTitulo((string) ($_POST['nombre'] ?? ''));
        $grado   = (int) ($_POST['id_grado_academico'] ?? 0);

        $validator = \App\Support\Validator::make([
            'nombre'             => $nombre,
            'id_grado_academico' => $grado,
        ], [
            'nombre'             => 'required|string|min_len:2|max_len:100',
            'id_grado_academico' => 'required|integer|min_value:1',
        ], [
            'nombre.required'             => 'Escribe el nombre del título.',
            'nombre.min_len'              => 'El nombre del título debe tener al menos 2 caracteres.',
            'nombre.max_len'              => 'El nombre del título no puede superar los 100 caracteres.',
            'id_grado_academico.required'  => 'Selecciona el grado académico.',
            'id_grado_academico.min_value' => 'Selecciona el grado académico.',
        ]);
        $errores = $validator->validate();
        if (empty($errores['id_grado_academico']) && !$this->modelo()->existeGradoAcademico($grado)) {
            $errores['id_grado_academico'] = 'El grado académico seleccionado no existe.';
        }

        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, 'Faltan datos del título.', 422);
        }

        $model = new TituloModel($this->conexion());

        if ($model->existeTituloEnGrado($nombre, $grado)) {
            $this->jsonResponse(
                false,
                ['nombre' => 'Ya existe un título con ese nombre en el grado seleccionado.'],
                'Ese título ya está registrado.',
                409
            );
        }

        $idTitulo = 0;
        try {
            $idTitulo = $model->createAndGetId(['nombre' => $nombre, 'id_grado_academico' => $grado]);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if ($idTitulo <= 0) {
            $this->jsonResponse(false, null, 'No se pudo guardar el título.', 500);
        }

        $this->jsonResponse(true, [
            'id'     => $idTitulo,
            'nombre' => $nombre,
        ], 'Título registrado correctamente.');
    }

    /**
 * Alta rápida de una institución desde el paso 5 del wizard.
 * Ruta: empleados/guardarInstitucion (POST, JSON).
 * Solo se acepta el tipo TIPO_TITULO_ACADEMICO.
*/
    public function guardarInstitucion(): void
    {
        $this->guardarInstitucionComun(
            static fn(string $tipo): ?string => $tipo === Instituciones::TIPO_TITULO_ACADEMICO
                ? null
                : 'Solo se registran instituciones donde se obtuvo un título.'
        );
    }

    /**
 * Registra un reposo del empleado (o del docente, que es un empleado).
 * Ruta: empleado/registrarReposo (POST, JSON).
 * Solo directo. Ver ReposoEmpleadoTrait para el flujo completo.
*/
    public function registrarReposo(): void
    {
        $this->registrarReposoDeEmpleado();
    }

    /**
 * Edita un reposo, o lo cierra escribiendo solo la fecha de fin.
 * Ruta: empleado/updateReposo (POST, JSON).
*/
    public function updateReposo(): void
    {
        $this->updateReposoDeEmpleado();
    }

    /**
 * Historial de reposos de un empleado, para el modal de detalle y el de edición.
 * Ruta: empleado/getReposos?id_empleado= (GET, JSON).
*/
    public function getReposos(): void
    {
        $this->getRepososDeEmpleado();
    }

    /**
     * Expediente del empleado: datos personales, laborales, formación,
     * asignaciones, reposos e historial de estados.
     * Ruta: empleado/expediente?id=... (GET).
     */
    public function expediente(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->setFlash('error', 'El empleado solicitado no existe.');
            $this->redirect('index');
        }

        try {
            $model    = $this->modelo();
            $empleado = $model->getById($id);

            if ($empleado === null) {
                $this->setFlash('error', 'El empleado solicitado no existe.');
                $this->redirect('index');
            }

            $formacion    = $model->getFormacionAcademica($id);
            $asignaciones = $model->getAsignacionesDocente($id);
            $historial    = $model->getHistorialEstados($id);
            $reposos      = (new ReposoEmpleadoModel($this->conexion()))->getPorEmpleado($id);

            $catalogos        = $model->getCatalogosFormulario();
            $cargos           = $model->getCargos();
            $gradosAcademicos = $catalogos['grados_academicos'] ?? [];
            $titulos          = $catalogos['titulos'] ?? [];
            $instituciones    = $catalogos['instituciones'] ?? [];
            $tiposInstitucion = (new InstitucionModel($this->conexion()))
                ->tiposFormulario([Instituciones::TIPO_TITULO_ACADEMICO]);
            $estadosGeo       = (new InstitucionModel($this->conexion()))->getEstadosGeo();
            $estados          = $catalogos['estados'] ?? [];
            $turnos           = $catalogos['turnos'] ?? [];
            $nacionalidades   = $model->getNacionalidades();
        } catch (Throwable $e) {
            $this->handleError($e, 'Empleados');
        }

        $puedeGestionar = sigde_rol_clave($_SESSION['rol'] ?? '') === 'directivo';

        require __DIR__ . '/../Views/empleados/expediente.php';
    }

    /**
     * Exporta la Ficha Integral del Empleado en formato PDF ministerial.
     * Ruta: empleado/exportar?id=... (GET).
     */
    public function exportar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->renderHttpError(404, '404 · Empleado no encontrado', 'ID de empleado inválido.');
        }

        try {
            $model    = $this->modelo();
            $empleado = $model->getById($id);

            if ($empleado === null) {
                $this->renderHttpError(404, '404 · Empleado no encontrado', 'El empleado solicitado no existe.');
            }

            $formacion    = $model->getFormacionAcademica($id);
            $asignaciones = $model->getAsignacionesDocente($id);
            $historial    = $model->getHistorialEstados($id);
            $reposos      = (new ReposoEmpleadoModel($this->conexion()))->getPorEmpleado($id);

            $config = Configuracion::getAll();

            $fotoRuta    = (string) ($empleado['avatar_foto'] ?? $empleado['foto'] ?? '');
            $fotoDataUri = $fotoRuta !== '' ? FichaEmpleadoPdf::imagenADataUri($fotoRuta) : null;

            $pdfContent = FichaEmpleadoPdf::generar(
                $config,
                $empleado,
                $formacion,
                $asignaciones,
                $reposos,
                $historial,
                null,
                $fotoDataUri
            );

            $docLimpio     = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($empleado['numero_documento'] ?? 'personal'));
            $nombreArchivo = 'ficha_empleado_' . $docLimpio . '.pdf';
            $descargar     = ($_GET['descargar'] ?? '') === '1';

            BinaryResponse::pdf($pdfContent, $nombreArchivo, !$descargar)->send();
        } catch (Throwable $e) {
            $this->handleError($e, 'Empleados');
        }
    }

    /**
     * Impacto de cambiar el estado (advertencia previa).
     * Ruta: empleado/estadoInfo?id=...&nuevo_estado=...&fecha_efectiva=... (GET, JSON).
     */
    public function estadoInfo(): void
    {
        $id            = (int) ($_GET['id'] ?? 0);
        $nuevoEstado   = strtoupper(trim((string) ($_GET['nuevo_estado'] ?? '')));
        $fechaEfectiva = trim((string) ($_GET['fecha_efectiva'] ?? date('Y-m-d')));

        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        if (!in_array($nuevoEstado, ['ACTIVO', 'INACTIVO'], true)) {
            $this->jsonResponse(false, null, 'Estado no válido.', 422);
        }

        if (!$this->fechaValida($fechaEfectiva) || $fechaEfectiva > date('Y-m-d')) {
            $this->jsonResponse(false, ['fecha_efectiva' => 'Indica una fecha efectiva válida (no futura).'], 'Revisa la fecha efectiva.', 422);
        }

        try {
            $data = $this->modelo()->getImpactoCambioEstado($id, $nuevoEstado, $fechaEfectiva);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, null, $e->getMessage(), 404);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        $this->jsonResponse(true, $data);
    }

    /**
     * Cambia el estado con motivo, fecha efectiva e historial.
     * Ruta: empleado/cambiarEstado y empleado/toggleStatus (POST, JSON).
     */
    public function cambiarEstado(): void
    {
        $id            = (int) ($_POST['id'] ?? 0);
        $nuevoEstado   = strtoupper(trim((string) ($_POST['nuevo_estado'] ?? '')));
        $motivo        = trim((string) ($_POST['motivo'] ?? ''));
        $fechaEfectiva = trim((string) ($_POST['fecha_efectiva'] ?? date('Y-m-d')));
        $confirmar     = ($_POST['confirmar'] ?? '') === '1';

        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        try {
            $empleado = $this->modelo()->getById($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if ($empleado === null) {
            $this->jsonResponse(false, null, 'El empleado solicitado no existe.', 404);
        }

        if (!in_array($nuevoEstado, ['ACTIVO', 'INACTIVO'], true)) {
            $nuevoEstado = strtoupper((string) $empleado['estado']) === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        }

        if ($motivo === '') {
            $this->jsonResponse(false, ['motivo' => 'Indica el motivo del cambio de estado.'], 'Falta el motivo.', 422);
        }

        if (mb_strlen($motivo, 'UTF-8') > 200) {
            $this->jsonResponse(false, ['motivo' => 'El motivo no puede pasar de 200 caracteres.'], 'El motivo es demasiado largo.', 422);
        }

        if (!$this->fechaValida($fechaEfectiva) || $fechaEfectiva > date('Y-m-d')) {
            $this->jsonResponse(false, ['fecha_efectiva' => 'Indica una fecha efectiva válida (no futura).'], 'Revisa la fecha efectiva.', 422);
        }

        try {
            $resultado = $this->modelo()->cambiarEstado(
                $id,
                $nuevoEstado,
                $motivo,
                $fechaEfectiva,
                (int) ($this->idUsuarioActual() ?? 0),
                $confirmar
            );
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, null, $e->getMessage(), 404);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if (!empty($resultado['requiere_confirmacion'])) {
            $this->jsonResponse(
                false,
                [
                    'requiere_confirmacion' => true,
                    'asignaciones'          => $resultado['asignaciones'] ?? [],
                    'reposo_vigente'        => $resultado['reposo_vigente'] ?? null,
                ],
                'Confirma que deseas continuar: se cerrarán las asignaciones activas.',
                409
            );
        }

        $idUsuario = (int) ($this->idUsuarioActual() ?? 0);
        $cambioReal = $resultado['estado_anterior'] !== $resultado['estado_nuevo'];

        if ($cambioReal && $idUsuario > 0) {
            (new AuditLogModel($this->conexion()))->registrar(
                $idUsuario,
                'CAMBIO ESTADO ' . (string) $empleado['nombre_completo'] . ' · ' . $resultado['estado_anterior'] . ' → ' . $resultado['estado_nuevo'],
                'Empleados'
            );
        }

        if (!$cambioReal) {
            $this->jsonResponse(true, $resultado, 'El empleado ya estaba en ese estado.');
        }

        $cerradas = (int) ($resultado['asignaciones_cerradas'] ?? 0);
        $this->jsonResponse(
            true,
            $resultado,
            $cerradas > 0
                ? 'Estado actualizado. Se cerraron ' . $cerradas . ' asignación(es).'
                : 'Estado actualizado correctamente.'
        );
    }

    /** Alias heredado: todo cambio de estado pasa por el historial. */
    public function toggleStatus(): void
    {
        $this->cambiarEstado();
    }

    /** Cierra anticipadamente un reposo. Ruta: empleado/cerrarReposo (POST, JSON). */
    public function cerrarReposo(): void
    {
        $this->cerrarReposoDeEmpleado();
    }

    /** Anula un reposo. Ruta: empleado/anularReposo (POST, JSON). */
    public function anularReposo(): void
    {
        $this->anularReposoDeEmpleado();
    }

    /**
 * Actualiza un empleado (modal de edición).
 * Ruta: empleados/update (POST, JSON).
 * Responde 400, 404, 405, 422 (validación o cédula duplicada) o 500.
*/
    public function update(): void
    {
        $model = $this->modelo();

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite edición de registros.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $actual = $model->getById($id);
        if ($actual === null) {
            $this->jsonResponse(false, null, 'El empleado seleccionado ya no existe.', 404);
        }

        if (strtoupper((string) ($actual['estado'] ?? '')) !== 'ACTIVO') {
            $this->jsonResponse(false, null, 'No se puede modificar un empleado inactivo. Debe activarlo primero.', 422);
        }

        $data = $this->extractData($_POST);

        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para actualizar.', 422);
        }

        // Se pasa el id para que la cédula duplicada ignore al propio empleado.
        $errors = $this->validate($data, $id);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        // Foto: solo se toca si suben una nueva o piden quitarla (eliminar_foto=1).
        $fotoAnterior = $actual['foto'] ?? null;
        $fotoNueva    = null;
        $quitarFoto   = ($_POST['eliminar_foto'] ?? '') === '1';

        if (!empty($_FILES['foto']['name'])) {
            $fotoNueva = $this->procesarFotoSubida(self::FOTO_EMPLEADO);
            $data['foto'] = $fotoNueva;
        } elseif ($quitarFoto) {
            $data['foto'] = null;
        }

        try {
            $model->update($id, $data);
        } catch (\PDOException $e) {
            if ($fotoNueva !== null) {
                $this->borrarFoto($fotoNueva);
            }
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) === 1062) {
                $this->jsonResponse(false, ['numero_documento' => 'Ya existe un empleado registrado con esa cédula.'], 'Hay errores en el formulario.', 422);
            }
            $this->jsonError($e, 'Empleados');
        } catch (DocumentoDuplicadoException | Throwable $e) {
            if ($fotoNueva !== null) {
                $this->borrarFoto($fotoNueva);
            }
            if ($e instanceof DocumentoDuplicadoException) {
                $this->jsonResponse(false, ['numero_documento' => $e->getMessage()], 'Hay errores en el formulario.', 422);
            }
            $this->jsonError($e, 'Empleados');
        }

        // Borra del disco la foto anterior si se reemplazó o se quitó.
        if (
            $fotoAnterior !== null
            && $fotoNueva !== $fotoAnterior
            && ($fotoNueva !== null || $quitarFoto)
        ) {
            $this->borrarFoto($fotoAnterior);
        }

        $this->jsonResponse(true, null, 'Empleado actualizado correctamente.');
    }

    /**
 * Crea un empleado (wizard).
 * Ruta: empleados/store (POST, JSON).
 * Si create() falla, borra la foto subida; la cédula repetida da 422.
*/
    public function store(): void
    {
        $model = $this->modelo();

        if (!$model instanceof Creatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite creación de registros.', 405);
        }

        $data = $this->extractData($_POST);

        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para guardar.', 422);
        }

        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        $data['foto'] = $this->procesarFotoSubida(self::FOTO_EMPLEADO);

        try {
            $model->create($data);
        } catch (\PDOException $e) {
            if ($data['foto'] !== null) {
                $this->borrarFoto($data['foto']);
            }
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) === 1062) {
                $this->jsonResponse(false, ['numero_documento' => 'Ya existe un empleado registrado con esa cédula.'], 'Hay errores en el formulario.', 422);
            }
            $this->jsonError($e, 'Empleados');
        } catch (DocumentoDuplicadoException | Throwable $e) {
            if ($data['foto'] !== null) {
                $this->borrarFoto($data['foto']);
            }
            if ($e instanceof DocumentoDuplicadoException) {
                $this->jsonResponse(false, ['numero_documento' => $e->getMessage()], 'Hay errores en el formulario.', 422);
            }
            $this->jsonError($e, 'Empleados');
        }

        $this->jsonResponse(true, null, 'Empleado registrado correctamente.');
    }

    /**
 * Modelo con su tipo real (EmpleadoModel), para que el editor
 * resuelva sus métodos en vez de verlos sobre `object`.
*/
    private function modelo(): EmpleadoModel
    {
        /** @var EmpleadoModel $model*/
        $model = $this->getModel();

        return $model;
    }

    /**
 * Convierte el $_POST del wizard o del modal de edición en el arreglo
 * de EmpleadoModel: persona, laboral, contacto, dirección y formación.
 * Los nombres se normalizan; el documento se guarda solo con dígitos.
*/
protected function extractData(array $source): array
    {
        // Pequeño helper solo para extraer el valor crudo de forma segura
        $raw = static fn(string$clave): string => (string) ($source[$clave] ?? '');

        /* Las filas de formación incompletas se anotan aparte y se saltan, para no
 guardarlas a medias: una fila a la que le falta un id produciría un registro con
 campos en cero. Antes se descartaban sin decir nada y el alta salía con "Empleado
 registrado correctamente" aunque el título que se había elegido no estuviera en
 ninguna parte.*/
        $erroresFormacion = [];

        $data = [
            'tipo_documento'   => $this->normalizarEspacios($raw('tipo_documento')),
            'numero_documento' => Documento::normalizar($this->normalizarEspacios($raw('numero_documento'))),
            'fecha_nacimiento' => $this->normalizarEspacios($raw('fecha_nacimiento')),
            'primer_nombre'    => $this->capitalizarTitulo($raw('primer_nombre')),
            'segundo_nombre'   => $this->capitalizarTitulo($raw('segundo_nombre')),
            'primer_apellido'  => $this->capitalizarTitulo($raw('primer_apellido')),
            'segundo_apellido' => $this->capitalizarTitulo($raw('segundo_apellido')),
            'sexo'             => $this->normalizarEspacios($raw('sexo')),
            /* Al empleado solo se le pregunta la entidad federal de nacimiento. La parroquia
               no se guarda para él: es un dato que el sistema solo necesita del estudiante. */
            'id_estado_nacimiento' => (int) ($raw('id_estado_nacimiento') ?? '0'),
            'id_cargo'         => (int) ($source['id_cargo'] ?? 0),
            'id_turno'         => (int) ($source['id_turno'] ?? 0) ?: null,
            'fecha_ingreso'    => $this->normalizarEspacios($raw('fecha_ingreso')),

            'contacto' => [
                'codigo_telefono_principal'   => $this->normalizarEspacios($raw('codigo_telefono_principal')),
                'numero_telefono_principal'   => $this->normalizarEspacios($raw('numero_telefono_principal')),
                'codigo_telefono_alternativo' => $this->normalizarEspacios($raw('codigo_telefono_alternativo')),
                'numero_telefono_alternativo' => $this->normalizarEspacios($raw('numero_telefono_alternativo')),
                'correo_electronico'          => $this->aMinusculas($raw('correo_electronico')),
            ],

            // Direcciones con capitalización inteligente
            'direccion' => [
                'id_estado'           => (int) ($source['id_estado_ui'] ?? 0),
                'id_municipio'        => (int) ($source['id_municipio_ui'] ?? 0),
                'id_parroquia'        => (int) ($source['id_parroquia'] ?? 0),
                'sector_urbanizacion' => $this->capitalizarTitulo($raw('sector_urbanizacion')),
                'calle_avenida'       => $this->capitalizarTitulo($raw('calle_avenida')),
                'nro_casa_apto'       => $this->aMayusculas($raw('nro_casa_apto')),
                'punto_referencia'    => $this->capitalizarOracion($raw('punto_referencia')),
            ],

            'formacion'         => $this->formacionDe($source, $erroresFormacion),
            'errores_formacion' => $erroresFormacion,
        ];

        /* La nacionalidad se le pregunta a toda persona, empleado incluido. Este bloque
        antes decía que la columna tenía default en la base y solo la enviaba si venía
        con valor, pero la columna no existía: PersonaModel la descartaba en silencio y
        el dato se perdía. Ahora sí existe y se manda siempre, como cualquier otro dato
        obligatorio del formulario. */
        $data['id_nacionalidad'] = (int) ($raw('id_nacionalidad') ?? '0');

        /* Solo el modal de edición manda esto: qué fila de formación se está tocando y si
 se pidió quitarla. Van fuera del array solo si vienen, para que el alta desde el
 asistente no los arrastre y el modelo no tenga que adivinar de qué pantalla
 viene la llamada.*/
        if (isset($source['id_formacion_academica'])) {
            $data['id_formacion_academica'] = (int) $source['id_formacion_academica'];
        }
        if (!empty($source['eliminar_formacion'])) {
            $data['eliminar_formacion'] = true;
        }

        return $data;
    }

    /**
 * Formación que llega al alta (JSON `titulos_json`) o a la edición
 * (una fila con campos planos). Solo devuelve filas completas.
 *
 * @return array<int, array{id_titulo:int,id_grado_academico:int,id_institucion:int,fecha_obtencion:?string}>
*/
    private function formacionDe(array $source, array &$errores = []): array
    {
        /* El JSON solo cuenta si trae filas. Antes el valor por defecto era '[]', que no
 está vacío: json_decode('[]') devuelve un array vacío y la función retornaba
 ahí, sin llegar nunca a los campos sueltos. Como el modal de edición
 (edit-modal.php) manda título, grado e institución en <input> sueltos y NO
 manda titulos_json, lo que elegías en ese modal se descartaba siempre: se
 guardaba el empleado con "actualizado correctamente" y el título no cambiaba.*/
        $json = trim((string) ($source['titulos_json'] ?? ''));

        if ($json !== '' && $json !== '[]') {
            $filas = json_decode($json, true);
            if (!is_array($filas)) {
                // No se distingue un JSON mal formado de que no haya datos, y un
                // json_last_error() silencioso convertiría un fallo del navegador en un
                // empleado sin formación.
                $errores['formacion'] = 'No se pudo leer la formación del empleado. '
                    . 'Recargue la pantalla e intente de nuevo.';

                return [];
            }

            $salida = [];
            foreach ($filas as $entrada) {
                $entrada = is_array($entrada) ? $entrada : [];
                $fila = $this->normalizarFormacion($entrada);

                if ($fila !== null) {
                    $salida[] = $fila;
                    continue;
                }

                /* El motivo se arma con $entrada (la fila tal como llegó), no con $fila:
 $fila aquí es null, que es justamente el resultado de "incompleta", y
 pasárselo al mensaje hacía que siempre saliera "sin título" aunque lo que
 faltara fuera la institución.*/
                if (!isset($errores['formacion'])) {
                    /* Se avisa solo de la primera fila incompleta: si hay varias, varios
 mensajes en el mismo campo se pisan y la secretaria vería uno solo
 sin saber a qué documento corresponde.*/
                    $errores['formacion'] = $this->motivoFilaIncompleta($entrada);
                }
            }

            return $salida;
        }

        // Modal de edición: una fila con los inputs sueltos.
        $fila = $this->normalizarFormacion($source);

        if ($fila !== null) {
            return [$fila];
        }

        /* Solo se acusa recibo si el formulario traía algo de formación. En el alta desde
 cero, entrar sin tocar nada en el paso 5 es lo normal y no es un error.*/
        if (($source['id_titulo'] ?? 0) || ($source['id_institucion'] ?? 0) || ($source['id_grado_academico'] ?? 0)) {
            $errores['formacion'] = $this->motivoFilaIncompleta($source);
        }

        return [];
    }

    /**
 * Dice qué le falta a una fila de formación, en vez de descartarla en silencio.
 * Se lee como un problema de quien lo va a corregir, no como un error del sistema.
*/
    private function motivoFilaIncompleta(array $fila): string
    {
        $titulo = trim((string) ($fila['titulo'] ?? ''));

        if ((int) ($fila['id_titulo'] ?? 0) <= 0) {
            return 'Hay una fila de formación sin título. Elige el título o bórrala.';
        }
        if ((int) ($fila['id_grado_academico'] ?? 0) <= 0) {
            return 'Selecciona el grado académico del título.';
        }

        return ($titulo !== '' ? 'El título «' . $titulo . '»' : 'El título')
            . ' necesita la institución donde se obtuvo. Elige la institución o bórralo.';
    }

    /**
 * Fila de formación completa, o null si le falta algún id.
 * La fecha de obtención se conserva tal cual (vacía = null): su
 * validación se hace en validate() para poder avisar al usuario.
*/
    private function normalizarFormacion(array $fila): ?array
    {
        $idTitulo      = (int) ($fila['id_titulo'] ?? 0);
        $idGrado       = (int) ($fila['id_grado_academico'] ?? 0);
        $idInstitucion = (int) ($fila['id_institucion'] ?? 0);

        if ($idTitulo <= 0 || $idGrado <= 0 || $idInstitucion <= 0) {
            return null;
        }

        $fecha = trim((string) ($fila['fecha_obtencion'] ?? ''));

        return [
            'id_titulo'          => $idTitulo,
            'id_grado_academico' => $idGrado,
            'id_institucion'     => $idInstitucion,
            'fecha_obtencion'    => $fecha !== '' ? $fecha : null,
        ];
    }

    /**
 * Valida los datos de extractData() y devuelve los errores por campo.
 * $ignorarId excluye al propio empleado de la búsqueda de cédula repetida.
 * Las reglas de persona, contacto y dirección viven en los traits.
 *
 * @return array<string, string>
*/
    protected function validate(array $data, ?int $ignorarId = null): array
    {
        $errores = [];

        // ----- Persona -----
        $this->validarDocumento(
            $data,
            $errores,
            ['V', 'E'],
            $ignorarId,
            'Ya existe un empleado registrado con esa cédula.'
        );

        $this->validarNombresApellidos($data, $errores);

        if (!in_array($data['sexo'], ['F', 'M'], true)) {
            $errores['sexo'] = 'Selecciona el sexo.';
        }

        $this->validarFechaNacimiento($data, $errores, 'fecha_nacimiento', 18, 100);
        /* $ignorarId no es null cuando viene de update(): el empleado ya existía y se
        registra antes de que estos dos campos existieran, así que los tiene en NULL y no
        pueden exigirse. En el alta (store) sí se exigen. Si vienen informados, se
        comprueba que sean válidos igual. */
        $exigir = true;
        $this->validarLugarNacimiento($data, $errores, false, $exigir);
        $this->validarNacionalidad($data, $errores, $exigir);

        // ----- Laboral -----
        $validatorLaboral = Validator::make([
            'id_cargo'      => $data['id_cargo'],
            'fecha_ingreso' => $data['fecha_ingreso'],
        ], [
            'id_cargo'      => 'required|integer|min_value:1',
            'fecha_ingreso' => 'required|date|date_not_future',
        ], [
            'id_cargo.required'             => 'Selecciona un cargo.',
            'id_cargo.min_value'            => 'Selecciona un cargo.',
            'fecha_ingreso.required'        => 'Indica una fecha de ingreso válida.',
            'fecha_ingreso.date'            => 'Indica una fecha de ingreso válida.',
            'fecha_ingreso.date_not_future' => 'La fecha de ingreso no puede ser futura.',
        ]);
        if (!$validatorLaboral->passes()) {
            $errores = array_merge($errores, $validatorLaboral->errors());
        }

        if (empty($errores['fecha_ingreso']) && !empty($data['fecha_nacimiento']) && $this->fechaValida($data['fecha_nacimiento'])) {
            $fechaMinimaIngreso = date('Y-m-d', strtotime($data['fecha_nacimiento'] . ' +18 years'));
            if ($data['fecha_ingreso'] < $fechaMinimaIngreso) {
                $errores['fecha_ingreso'] = 'La fecha de ingreso debe ser posterior a la mayoría de edad (18 años).';
            }
        }

        if ($data['id_turno'] !== null && !$this->modelo()->existeTurno((int) $data['id_turno'])) {
            $errores['id_turno'] = 'Selecciona un turno activo válido.';
        }
        if ($ignorarId !== null
            && !$this->modelo()->turnoCompatibleConAsignaciones($ignorarId, $data['id_turno'])) {
            $errores['id_turno'] = 'No se puede cambiar el turno: el docente tiene asignaciones activas en otro turno.';
        }

        // ----- Formación -----
        /* La fecha de obtención es OBLIGATORIA si hay un título registrado:
 formacion_academica.fecha_obtencion es NOT NULL. El comentario anterior decía que
 era opcional y solo se validaba "si viene con valor", lo que nunca se notó porque
 el asistente la pide siempre en el paso 5. Editando sí se notaba: el modal la
 deja en blanco, se guardaba NULL, y la base devolvía
 "Column 'fecha_obtencion' cannot be null": un 500 con el mensaje genérico de
 "ocurrió un problema", sin decir qué corregir.*/
        foreach ($data['formacion'] as $fila) {
            if (empty($fila['fecha_obtencion'])) {
                $errores['fecha_obtencion'] = 'Indica la fecha de obtención del título.';
                break;
            }

            if (!$this->fechaEnRango($fila['fecha_obtencion'])) {
                $errores['fecha_obtencion'] = 'Indica una fecha de obtención válida (no puede ser futura).';
                break;
            }
        }

        // Errores que ya recogió extractData() al descartar filas incompletas: llegan aquí
        // porque la fila incompleta no es un dato que validar campo por campo, sino una fila
        // que el usuario tiene que corregir o borrar.
        foreach (($data['errores_formacion'] ?? []) as $mensaje) {
            $errores['formacion'] = $mensaje;
        }

        // ----- Contacto -----
        $this->validarContacto($data, $errores, true);

        // ----- Dirección -----
        $this->validarDireccion($data, $errores);

        return $errores;
    }
}
