<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Support\Validator;
use PDO;
use Throwable;

use function App\Traits\sigde_rol_clave;

/**
 * Gestión de años escolares (configuracion_academica/anios_escolares), consumida por academic-years.js.
 * Las transiciones de estado son acciones explícitas: iniciar() y cerrar().
*/
class AcademicYearController extends CrudController
{
    protected string $viewPath  = 'configuracion_academica/anios_escolares';
    protected string $routeName = 'academicYear';

    /**
 * Devuelve el modelo con su tipo concreto.
*/
    private function modelo(): AcademicYearModel
    {
        /** @var AcademicYearModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new AcademicYearModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'estado' => trim($_GET['estado'] ?? ''),
            'q'      => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        return [
            'stats'         => $this->modelo()->getStats(),
            'anios'         => $items,
            'puedeReabrir'  => function_exists('App\Traits\sigde_rol_clave')
                && sigde_rol_clave($_SESSION['rol'] ?? '') === 'directivo',
        ];
    }

    /**
 * Campos del período tal como los manda el modal (contrato de CrudController::store()/update()).
*/
    protected function extractData(array $source): array
    {
        return [
            // Oración (no Título Case): el nombre es tipo "Año escolar 2026-2027".
            'nombre'       => $this->capitalizarOracion(trim($source['nombre'] ?? '')),
            'fecha_inicio' => trim($source['fecha_inicio'] ?? ''),
            'fecha_cierre' => trim($source['fecha_cierre'] ?? ''),
            // Quién crea el período: la bitácora del módulo lo necesita y no está en la tabla.
            'id_usuario'   => (int) ($this->idUsuarioActual() ?? 0),
        ];
    }

    public function store(): void
    {
        $source = $_POST;
        $data = $this->extractData($source);
        $errores = $this->validate($data);

        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, 'Corrige los errores antes de continuar.', 422);
            return;
        }

        try {
            $this->modelo()->create($data);
            $this->jsonResponse(true, null, 'Año escolar creado correctamente.');
        } catch (\PDOException $e) {
            $codigo = (int) ($e->errorInfo[1] ?? 0);
            if ($e->getCode() === '23000' || $codigo === 1062) {
                $this->jsonResponse(
                    false,
                    ['nombre' => 'Ya existe un año escolar con ese nombre.'],
                    'Registro duplicado.',
                    422
                );
                return;
            }
            $this->jsonError($e, static::class);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $data = $this->extractData($_POST);
        $errores = $this->validate($data, $id);
        if ($errores !== []) {
            $this->jsonResponse(false, $errores, 'Hay errores en el formulario.', 422);
        }

        try {
            if (!$this->modelo()->update($id, $data)) {
                $this->jsonResponse(false, null, 'No se puede editar un año escolar cerrado o inexistente.', 422);
            }
        } catch (\PDOException $e) {
            $codigo = (int) ($e->errorInfo[1] ?? 0);
            if ($e->getCode() === '23000' || $codigo === 1062) {
                $this->jsonResponse(
                    false,
                    ['nombre' => 'Ya existe un año escolar con ese nombre.'],
                    'Registro duplicado.',
                    422
                );
                return;
            }
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Año escolar actualizado correctamente.');
    }

    public function iniciar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $usuario = $this->idUsuarioActual();
        if ($id <= 0 || $usuario === null) {
            $this->jsonResponse(false, null, 'No se pudo identificar el año escolar o el usuario.', 422);
        }

        $resultado = $this->modelo()->iniciar($id, $usuario);
        $this->jsonResponse($resultado['ok'], null, $resultado['mensaje'], $resultado['ok'] ? 200 : 422);
    }

    public function cerrar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $usuario = $this->idUsuarioActual();
        if ($id <= 0 || $usuario === null) {
            $this->jsonResponse(false, null, 'No se pudo identificar el año escolar o el usuario.', 422);
        }

        $resultado = $this->modelo()->cerrar($id, $usuario);
        $this->jsonResponse($resultado['ok'], null, $resultado['mensaje'], $resultado['ok'] ? 200 : 422);
    }

    /**
     * Reabre un año escolar cerrado (solo directiva).
     *
     * A diferencia de iniciar y cerrar, el motivo es OBLIGATORIO: reabrir cambia el
     * período de referencia de matrículas y estructuras, así que sin una razón escrita el
     * registro no dice por qué se deshizo un cierre ni quién lo hizo.
     */
    public function reabrir(): void
    {
        $id       = (int) ($_POST['id'] ?? 0);
        $motivo   = trim((string) ($_POST['motivo'] ?? ''));
        $usuario  = $this->idUsuarioActual();

        if ($id <= 0 || $usuario === null) {
            $this->jsonResponse(false, null, 'No se pudo identificar el año escolar o el usuario.', 422);
        }

        $v = Validator::make(['motivo' => $motivo], [
            'motivo' => 'required|string|min_len:10|max_len:200',
        ]);

        if (!$v->passes()) {
            $this->jsonResponse(
                false,
                ['motivo' => $v->errors()['motivo'][0]],
                'Motivo no válido.',
                422
            );
        }

        $resultado = $this->modelo()->reabrir($id, $usuario, $this->capitalizarOracion($motivo));
        $this->jsonResponse($resultado['ok'], null, $resultado['mensaje'], $resultado['ok'] ? 200 : 422);
    }

    /**
     * Devuelve los errores por campo.
     * El nombre repetido (UNIQUE nombre_periodo) se le pregunta al modelo, que conoce la restricción.
     */
    protected function validate(array $data, ?int $id = null): array
    {
        $errores = [];

        $v = Validator::make($data, [
            'nombre'       => 'required|string|max_len:9|regex:/^\d{4}-\d{4}$/',
            'fecha_inicio' => 'required|date',
            'fecha_cierre' => 'required|date|date_after_or_equal:fecha_inicio',
        ]);

        if (!$v->passes()) {
            foreach ($v->errors() as $campo => $mensajes) {
                $errores[$campo] = $mensajes[0];
            }
        }
        if (empty($errores['nombre']) && !preg_match('/^\d{4}-\d{4}$/', (string) $data['nombre'])) {
            $errores['nombre'] = 'El nombre del período debe tener el formato AAAA-AAAA (ej. 2026-2027).';
        }

        // $id llega de update() y permite excluir la fila en edición de unicidad;
        // en store() CrudController lo pasa como null, y $id ?? 0 excluye nada.
        if ($errores === [] && $this->modelo()->existePorNombre($data['nombre'], $id ?? 0)) {
            $errores['nombre'] = 'Ya existe un año escolar con ese nombre.';
        }

        return $errores;
    }
}