<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CatalogModel;
use App\Models\InstitucionModel;
use App\Support\Instituciones;
use App\Support\Validator;
use App\Traits\UbicacionTrait;
use App\Interfaces\Findable;
use App\Interfaces\Listable;
use App\Interfaces\Updatable;
use PDO;
use Throwable;

/**
 * Gestión de Catálogos del Sistema: un controlador para todos los catálogos
 * maestros, cada uno con su modelo vía self::CATALOGOS. El catálogo activo
 * sale del parámetro `tipo`; para añadir uno, crea su modelo y una entrada ahí.
 *
 * @author Logística
 * @package App\Controllers
 */
class CatalogController extends CrudController
{
    /* Aporta getUbicacion(), el endpoint que devuelve estado, municipio y
       parroquia para la cascada. Lo usan los formularios de institución. */
    use UbicacionTrait;

    /** Carpeta de vistas del módulo (Views/configuracion/catalogo). */
    protected string $viewPath  = 'configuracion/catalogo';

    /** Segmento de ruta del controlador; lo usa redirect(). */
    protected string $routeName = 'catalogo';

    /**
     * Catálogos disponibles, por clave de `tipo`: nombre e icono para el
     * selector, clase del modelo y 'formato' de capitalización ('titulo' o
     * 'oracion', esta última para frases largas). También hace de whitelist.
     */
    private const CATALOGOS = [
        'grado'             => ['nombre' => 'Grado',              'icono'   => 'bi-mortarboard',       'modelClass' => 'GradoModel',          'formato' => 'titulo',  'maxLength' => 30],
        'seccion'           => ['nombre' => 'Sección',            'icono'   => 'bi-diagram-3',          'modelClass' => 'SeccionModel',        'formato' => 'titulo',  'maxLength' => 10],
        'turno'             => ['nombre' => 'Turno',              'icono'   => 'bi-clock',              'modelClass' => 'TurnoModel',          'formato' => 'titulo',  'maxLength' => 30],
        'tipo_documento'    => ['nombre' => 'Tipo de Documento',  'icono'   => 'bi-file-earmark-text',  'modelClass' => 'TipoDocumentoModel',  'formato' => 'oracion'],
        'grado_academico'   => ['nombre' => 'Grado Académico',    'icono'   => 'bi-award',              'modelClass' => 'GradoAcademicoModel', 'formato' => 'titulo'],
        'tipo_asignacion'   => ['nombre' => 'Tipo de Asignación', 'icono'   => 'bi-briefcase',          'modelClass' => 'TipoAsignacionModel', 'formato' => 'titulo'],
        'rol'               => ['nombre' => 'Rol',                'icono'   => 'bi-shield-lock',        'modelClass' => 'RolModel',            'formato' => 'titulo'],
        'parentesco'        => ['nombre' => 'Parentesco',         'icono'   => 'bi-people',              'modelClass' => 'ParentescoModel',     'formato' => 'titulo'],
        'motivo_retiro'     => ['nombre' => 'Motivo de Retiro',   'icono'   => 'bi-box-arrow-right',    'modelClass' => 'MotivoRetiroModel',   'formato' => 'oracion'],
        // Los pide el sub-modal de familiares para el representante legal.
        'ocupacion'         => ['nombre' => 'Profesión',           'icono'  => 'bi-briefcase',          'modelClass' => 'OcupacionModel',        'formato' => 'titulo'],
        'nacionalidad'      => ['nombre' => 'Nacionalidad',         'icono' => 'bi-globe-americas',     'modelClass' => 'NacionalidadModel',    'formato' => 'titulo'],
        // Único catálogo con un campo propio además del nombre: el tipo. No es
        // opcional ni decorativo: institucion.id_tipo_institucion es NOT NULL
        // sin valor por defecto, así que sin tipo el INSERT no entra.
        // 'maxLength' sube a 100, lo que admite la columna, porque los nombres
        // de institución son largos. Los otros once catálogos siguen en 50.
        // 'respetaSiglas': los nombres de institución traen siglas (UNEFA, UCV).
        'institucion'       => ['nombre' => 'Institución',          'icono' => 'bi-building',           'modelClass' => 'InstitucionModel',    'formato' => 'titulo', 'maxLength' => 100, 'pideTipo' => true, 'respetaSiglas' => true],
    ];

    /** Largo máximo del nombre para los catálogos que no fijan el suyo. */
    private const MAX_NOMBRE = 50;

    /** Clave del catálogo activo; se fija en createModel(). */
    private string $catalogoActivo;

    /**
     * Modelo con su tipo real (CatalogModel), para que el editor
     * resuelva sus métodos en vez de verlos sobre `object`.
     */
    private function modelo(): CatalogModel
    {
        /** @var CatalogModel $model */
        $model = $this->getModel();

        return $model;
    }

    /**
     * Fabrica el modelo del catálogo activo (gancho de getModel()) y
     * fija $catalogoActivo para el resto de los métodos.
     */
    protected function createModel(PDO $pdo): object
    {
        $this->catalogoActivo = $this->resolveTipoActivo();

        return $this->instantiateModel($this->catalogoActivo, $pdo);
    }

    /**
     * Catálogo pedido en `tipo` (GET o POST). Si no es una clave válida cae
     * al primero, así una URL manipulada nunca instancia una clase arbitraria.
     */
    private function resolveTipoActivo(): string
    {
        $tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? '';
        if ($tipo === 'profesion' || $tipo === 'profesión') {
            $tipo = 'ocupacion';
        }

        return array_key_exists($tipo, self::CATALOGOS) ? $tipo : array_key_first(self::CATALOGOS);
    }

    /**
     * Crea el modelo de un catálogo a partir de su clave en CATALOGOS.
     * Sin $pdo usa $this->pdo, que existe tras la primera llamada a getModel().
     */
    private function instantiateModel(string $tipo, ?PDO $pdo = null): object
    {
        $fqcn = 'App\\Models\\' . self::CATALOGOS[$tipo]['modelClass'];

        return new $fqcn($pdo ?? $this->pdo);
    }

    /**
     * Listado del catálogo activo. Ruta: catalogo/index (GET).
     * Sustituye al del padre porque normaliza la PK a 'id' y arma el selector
     * con todos los catálogos. La vista recibe $elementos, $total, $paginacion,
     * $filters, $catalogoActivo y $tiposCatalogo.
     */
    public function index(): void
    {
        try {
            $model   = $this->modelo();
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $elementos = $model instanceof Listable
                ? $this->normalizarPrimaryKey($model->getAll($filters, $page, $this->perPage))
                : [];
            $total = $model instanceof Listable ? $model->countAll($filters) : 0;

            $paginacion     = $this->buildPagination($total, $page, $this->perPage);
            $catalogoActivo = $this->catalogoActivo;
            $tiposCatalogo  = $this->buildTiposCatalogo();

            // Datos que los modales necesitan y que solo el controlador puede
            // resolver: el largo máximo del catálogo activo y, cuando el
            // catálogo pide tipo, la lista de categorías de institución.
            $catalogoMaxLength = self::CATALOGOS[$this->catalogoActivo]['maxLength'] ?? self::MAX_NOMBRE;
            $pideTipo           = self::CATALOGOS[$this->catalogoActivo]['pideTipo'] ?? false;
            $tiposInstitucion  = $pideTipo
                ? (new InstitucionModel($this->conexion()))->tiposFormulario(Instituciones::TIPOS)
                : [];
            // Ubicación: el <select> de estados solo lo necesitan institución y
            // los dos modales de alta que comparten sus reglas.
            $estadosGeo        = $pideTipo ? (new InstitucionModel($this->conexion()))->getEstadosGeo() : [];

            require __DIR__ . "/../Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
     * Formulario de edición. Ruta: catalogo/edit/{id}?tipo=... (GET).
     * Sustituye al del padre para normalizar la PK igual que index() y
     * conservar ?tipo= al redirigir si el id no existe.
     */
    public function edit(): void
    {
        try {
            $model = $this->modelo();
            $id    = (int)($_GET['id'] ?? 0);
            $item  = ($id > 0 && $model instanceof Findable) ? $model->getById($id) : null;

            if (!$item) {
                $this->setFlash('error', 'El registro solicitado no existe.');
                $this->redirect('index', ['tipo' => $this->catalogoActivo]);
            }

            // Misma normalización de PK que index(), para que no diverjan.
            [$item] = $this->normalizarPrimaryKey([$item]);

            $catalogoActivo = $this->catalogoActivo;

            require __DIR__ . "/../Views/{$this->viewPath}/edit.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
     * Agrega la clave 'id' (alias de la PK real: id_grado, id_turno, ...)
     * a cada fila, porque la vista y catalogo.js trabajan con 'id'.
     * La PK original se conserva.
     */
    private function normalizarPrimaryKey(array $elementos): array
    {
        $pk = $this->modelo()->getPrimaryKey();

        foreach ($elementos as &$el) {
            $el['id'] = $el[$pk] ?? null;
        }
        unset($el);

        return $elementos;
    }

    /**
     * Endpoint de la cascada estado -> municipio -> parroquia.
     * Ruta: catalogo/getUbicacion (GET, JSON). Lo consume
     * SIGDEWizardUI.initCascadaUbicacion() desde el formulario de institución.
     */
    public function getUbicacion(): void
    {
        $this->getUbicacionComun('Catálogos');
    }

    /**
     * Datos del selector de catálogos: clave, nombre, icono y total de
     * registros de cada uno. Reutiliza el modelo activo y crea los demás.
     *
     * @return array<int, array{clave: string, nombre: string, icono: string, total: int}>
     */
    private function buildTiposCatalogo(): array
    {
        $tipos = [];

        foreach (self::CATALOGOS as $clave => $info) {
            $model = $clave === $this->catalogoActivo
                ? $this->modelo()
                : $this->instantiateModel($clave);

            $tipos[] = [
                'clave'  => $clave,
                'nombre' => $info['nombre'],
                'icono'  => $info['icono'],
                'total'  => $model->countAll([]),
            ];
        }

        return $tipos;
    }

    /**
     * Filtros del listado desde la URL: texto (`q` o `search`) y `estado`.
     *
     * @return array{search: string, estado: string}
     */
    protected function getFiltersFromRequest(): array
    {
        return [
            'search' => trim($_GET['q'] ?? $_GET['search'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
        ];
    }

    /**
     * Extrae el nombre del formulario y lo capitaliza según el 'formato' del
     * catálogo: 'titulo' (cada palabra) u 'oracion' (solo la primera letra).
     * Cubre store() y update().
     *
     * @return array{nombre: string}
     */
    protected function extractData(array $source): array
    {
        $nombre  = trim($source['nombre'] ?? '');
        $formato = self::CATALOGOS[$this->catalogoActivo]['formato'] ?? 'titulo';

        $respetaSiglas = self::CATALOGOS[$this->catalogoActivo]['respetaSiglas'] ?? false;

        $data = [
            'nombre' => $respetaSiglas
                ? $this->capitalizarTituloConSiglas($nombre)
                : ($formato === 'oracion'
                    ? $this->capitalizarOracion($nombre)
                    : $this->capitalizarTitulo($nombre)),
        ];

        // Solo institución lleva tipo. Se lee de 'tipo_institucion' y NO de
        // 'tipo': ese nombre lo usa el campo oculto del catálogo activo, y
        // leerlo aquí haría que el catálogo se resolviera mal.
        if (self::CATALOGOS[$this->catalogoActivo]['pideTipo'] ?? false) {
            $data['tipo']          = trim((string) ($source['tipo_institucion'] ?? ''));
            $data['es_extranjera'] = ((string) ($source['es_extranjera'] ?? '0')) === '1' ? 1 : 0;
            $data['id_estado_geo']  = (int) ($source['id_estado_geo'] ?? 0);
            $data['id_municipio']   = (int) ($source['id_municipio'] ?? 0);
            $data['id_parroquia']   = (int) ($source['id_parroquia'] ?? 0);
            $data['codigo_dea']     = mb_strtoupper(trim((string) ($source['codigo_dea'] ?? '')), 'UTF-8');
        }

        return $data;
    }

    /**
     * Valida el nombre y devuelve el primer error: obligatorio, mínimo
     * (1 en 'seccion', 2 en el resto), máximo 50 y no repetido.
     * $excludeId omite al propio registro al editar.
     *
     * @return array<string, string>
     */
    protected function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        $model  = $this->modelo();

        $minimo = $this->catalogoActivo === 'seccion' ? 1 : 2;
        $maximo = self::CATALOGOS[$this->catalogoActivo]['maxLength'] ?? self::MAX_NOMBRE;

        $validator = Validator::make($data, [
            'nombre' => "required|string|min_len:{$minimo}|max_len:{$maximo}",
        ], [
            'nombre.required' => 'El nombre del elemento es obligatorio.',
            'nombre.min_len'  => "El nombre debe tener al menos {$minimo} caracter" . ($minimo > 1 ? 'es' : '') . '.',
            'nombre.max_len'  => "El nombre no puede superar los {$maximo} caracteres.",
        ]);

        $errors = $validator->validate();

        if (empty($errors['nombre']) && $model->existeNombre($data['nombre'], $excludeId)) {
            $errors['nombre'] = 'Ya existe un registro con este nombre en el catálogo activo.';
        }

        if (self::CATALOGOS[$this->catalogoActivo]['pideTipo'] ?? false) {
            $tipo = trim((string) ($data['tipo'] ?? ''));

            if ($tipo === '') {
                $errors['tipo'] = 'Selecciona el tipo de institución.';
            } elseif (!Instituciones::esValido($tipo)) {
                $errors['tipo'] = 'El tipo de institución seleccionado no existe.';
            } elseif (Instituciones::idCatalogo($tipo) === null) {
                $errors['tipo'] = 'El tipo de institución no tiene categoría asignada.';
            }

            $errores = $model->validarUbicacion($data, $excludeId);
            return array_merge($errors, $errores);
        }

        return $errors;
    }

    /**
     * Protege los registros con código estable.
     *
     * Los documentos y parentescos que usan las reglas de matrícula no se
     * pueden desactivar desde la interfaz: la regla dejaría de encontrar el
     * registro. Sí se pueden reactivar si ya estaban inactivos, y el nombre se
     * puede corregir porque el vínculo es el código, no el nombre.
     */
    protected function beforeToggleStatus(int $id): void
    {
        $item = $this->modelo()->getById($id);

        if ($item === null || empty($item['codigo'])) {
            return;
        }

        if (strtoupper((string) ($item['estado'] ?? '')) === 'ACTIVO') {
            $this->jsonResponse(
                false,
                null,
                'Este registro tiene un código obligatorio y no se puede desactivar.',
                422
            );
        }
    }

    /**
     * Actualiza un registro del catálogo activo. Ruta: catalogo/update (POST, JSON).
     * Sustituye al del padre para pasar $id como $excludeId a validate().
     * Responde 400, 405, 422 (validación o elemento inactivo), 500 o 200.
     */
    public function update(): void
    {
        $model = $this->modelo();

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Este catálogo no admite edición de registros.', 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $data   = $this->extractData($_POST);
        $errors = $this->validate($data, $id);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $itemActual = method_exists($model, 'getById') ? $model->getById($id) : null;
            if ($itemActual && isset($itemActual['estado']) && strtoupper((string)$itemActual['estado']) === 'INACTIVO') {
                $this->jsonResponse(false, null, 'No se puede modificar un elemento de catálogo inactivo. Debe activarlo primero.', 422);
            }

            $model->update($id, $data);
        } catch (\PDOException $e) {
            $codigo = (int) ($e->errorInfo[1] ?? 0);
            if ($e->getCode() === '23000' || $codigo === 1062) {
                $this->jsonResponse(false, ['nombre' => 'Ya existe un registro con este nombre en el catálogo.'], 'Registro duplicado.', 422);
            }
            $this->jsonError($e, static::class);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        // Se devuelve $data (ya normalizado) para que catalogo.js pinte la
        // fila con lo guardado, sin recargar la página.
        $this->jsonResponse(true, $data, 'Elemento actualizado correctamente.');
    }
}
