<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Updatable;
use App\Models\UserAccountModel;
use App\Support\Validator;
use App\Traits\DetalleJsonTrait;
use PDO;
use Throwable;

/**
 * Gestión de cuentas de usuario: listado, alta desde un empleado, edición
 * (usuario y rol), detalle y activación. Todo va por modales AJAX (vistas en
 * Views/usuarios/cuentas/).
 *
 * @author Logística
 * @package App\Controllers
*/
class UserAccountController extends CrudController
{
    use DetalleJsonTrait;

    protected string $viewPath  = 'usuarios/cuentas';
    protected string $routeName = 'cuentaUsuario';

    private function modelo(): UserAccountModel
    {
        /** @var UserAccountModel $model*/
        $model = $this->getModel();
        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new UserAccountModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'id_rol' => (int) ($_GET['id_rol'] ?? 0),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();

        return [
            'usuarios'             => $items,
            'stats'                => $model->getStats(),
            'roles'                => $model->getRoles(),
            'empleadosDisponibles' => $model->getEmpleadosDisponibles(),
            'usuarioActualId'      => $this->idUsuarioActual(),
        ];
    }

    /**
 * Reemplaza extraData para coincidir con el gancho del CrudController.
*/
    protected function extractData(array $source): array
    {
        $rawUsuario = (string) ($source['nombre_usuario'] ?? $source['usuario'] ?? '');

        return [
            'nombre_usuario' => $this->sanitizarNombreUsuario($rawUsuario),
            'persona_id'     => (int) ($source['empleado_id'] ?? $source['persona_id'] ?? 0),
            'rol_id'         => (int) ($source['rol_id'] ?? 0),
        ];
    }

    /**
     * Validación centralizada: chequea formato, unicidad y reglas de negocio.
     */
    protected function validate(array $data, ?int $id = null): array
    {
        $errores = [];
        $model   = $this->modelo();

        $rules = [
            'nombre_usuario' => 'required|string|min_len:3|max_len:50|regex:/^[a-z0-9._]+$/',
            'rol_id'         => 'required|integer|min_value:1',
        ];

        $messages = [
            'nombre_usuario.required' => 'El nombre de usuario es obligatorio.',
            'nombre_usuario.min_len'  => 'El nombre de usuario debe tener entre 3 y 50 caracteres.',
            'nombre_usuario.max_len'  => 'El nombre de usuario debe tener entre 3 y 50 caracteres.',
            'nombre_usuario.regex'    => 'El nombre de usuario solo puede contener letras minúsculas, números, puntos y guiones bajos.',
            'rol_id.required'         => 'Debe seleccionar un rol.',
            'rol_id.min_value'        => 'Debe seleccionar un rol.',
        ];

        if ($id === null) {
            $rules['persona_id'] = 'required|integer|min_value:1';
            $messages['persona_id.required']  = 'Debe seleccionar un empleado.';
            $messages['persona_id.min_value'] = 'Debe seleccionar un empleado.';
        }

        $validator = Validator::make($data, $rules, $messages);
        if (!$validator->passes()) {
            $errores = $validator->errors();
        }

        // 1. Validaciones dependientes del contexto (Crear vs Actualizar)
        if ($id !== null) {
            if ($id <= 0) {
                $errores['id'] = 'ID de usuario inválido.';
            } else {
                // Reglas de negocio para edición
                $usuarioActual = $model->getDetalleById($id);
                if (!$usuarioActual) {
                    $errores['id'] = 'Usuario no encontrado.';
                } elseif (($usuarioActual['estado'] ?? '') === 'inactivo') {
                    $errores['estado'] = 'No se puede modificar una cuenta de usuario inactiva. Debe activarla primero.';
                }
            }
        } else {
            // Reglas de negocio para creación
            $personaId = (int) ($data['persona_id'] ?? 0);
            if ($personaId > 0 && $model->personaTieneUsuario($personaId)) {
                $errores['persona_id'] = 'El empleado seleccionado ya posee una cuenta de usuario.';
            }
        }

        // 2. Unicidad del nombre de usuario (Aprovecha el $id)
        if (empty($errores['nombre_usuario'])) {
            $usuario = (string) ($data['nombre_usuario'] ?? '');
            if ($usuario !== '' && $model->existeNombreUsuario($usuario, $id ?? 0)) {
                $errores['nombre_usuario'] = "El nombre de usuario \"{$usuario}\" ya está en uso.";
            }
        }

        return $errores;
    }

    public function getDetail(): void
    {
        $this->responderDetalle(
            'getDetalleCompletoById',
            'ID de usuario inválido.',
            'Usuario no encontrado.',
            'Cuentas de usuario'
        );
    }

    public function create(): void
    {
        try {
            $model                = $this->modelo();
            $empleadosDisponibles = $model->getEmpleadosDisponibles();
            $roles                = $model->getRoles();

            $modalPath = __DIR__ . "/../Views/{$this->viewPath}/modals/create-modal.php";
            if (!file_exists($modalPath)) {
                throw new \RuntimeException("No se encontró el modal en: {$modalPath}");
            }

            require $modalPath;
        } catch (Throwable $e) {
            $this->handleError($e, 'Cuentas de usuario');
        }
    }

    public function edit(): void
    {
        $this->setFlash('error', 'Esta sección no tiene una página de edición independiente.');
        $this->redirect('index');
    }

    /**
 * Se sobreescribe porque `crearUsuario` genera una contraseña inicial y
 * devuelve una respuesta en formato RAW para el frontend.
*/
    public function store(): void
    {
        $data = $this->extractData($_POST);

        $errores = $this->validate($data);
        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, implode(' ', $errores), 422);
        }

        try {
            $resultado = $this->modelo()->crearUsuario(
                $data['persona_id'],
                $data['rol_id'],
                $data['nombre_usuario']
            );
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) === 1062) {
                $this->jsonResponse(false, ['nombre_usuario' => 'El nombre de usuario o empleado ya posee una cuenta registrada.'], 'El nombre de usuario ya está en uso.', 422);
            }
            $this->jsonError($e, 'Cuentas de usuario');
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (empty($resultado['ok'])) {
            $status = ($resultado['codigo'] ?? 0) === 422 ? 422 : 400;
            $this->jsonResponse(false, ['nombre_usuario' => $resultado['mensaje'] ?? 'No se pudo crear el usuario.'], $resultado['mensaje'] ?? 'No se pudo crear el usuario.', $status);
        }

        $this->jsonRaw($resultado);
    }

    /**
     * Se sobreescribe porque al guardar devuelve todo el detalle del usuario
     * para actualizar la fila de la tabla en tiempo real.
     */
    public function update(): void
    {
        $model = $this->modelo();

        $idActual = $this->idUsuarioActual();
        if ($idActual !== null && $id === $idActual) {
            $this->jsonResponse(false, null, 'No puedes modificar tu propia cuenta de usuario.', 403);
        }

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite edición de cuentas.', 405);
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $data = $this->extractData($_POST);

        $errores = $this->validate($data, $id);
        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, implode(' ', $errores), 422);
        }

        try {
            // Toda la validación de unicidad e inactividad ya se hizo arriba
            $ok = $model->update($id, [
                'rol_id'         => $data['rol_id'],
                'nombre_usuario' => $data['nombre_usuario'],
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) === 1062) {
                $this->jsonResponse(false, ['nombre_usuario' => 'El nombre de usuario ya está registrado por otra cuenta.'], 'El nombre de usuario ya está en uso.', 422);
            }
            $this->jsonError($e, 'Cuentas de usuario');
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (!$ok) {
            $this->jsonResponse(false, null, 'No se pudo actualizar el usuario.', 400);
        }

        $datos = $model->getDetalleById($id);
        $this->jsonResponse(true, $datos, 'Usuario actualizado correctamente.');
    }

    protected function beforeToggleStatus(int $id): void
    {
        $idActual = $this->idUsuarioActual();

        if ($idActual === null || $id === $idActual) {
            $this->jsonResponse(false, null, 'No puedes desactivar tu propia cuenta.', 403);
        }
    }

    private function sanitizarNombreUsuario(string $nombreUsuario): string
    {
        return trim(mb_strtolower($nombreUsuario, 'UTF-8'));
    }
}
