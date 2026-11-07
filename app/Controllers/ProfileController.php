<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProfileModel;
use App\Traits\FotoSubidaTrait;
use PDO;

/**
 * Controlador de Perfil de Usuario
 *
 * Permite a cualquier usuario autenticado de SIGDE (admin, directivo,
 * coordinador, docente, secretaria) consultar su propia ficha de identidad,
 * datos de cuenta, información laboral y canales de contacto en modo solo lectura.
 */
class ProfileController extends BaseController
{
    use FotoSubidaTrait;

    protected string $viewPath  = 'perfil';
    protected string $routeName = 'perfil';

    private const FOTO_USUARIO = [
        'campo'     => 'foto',
        'carpeta'   => 'usuarios',
        'prefijo'   => 'user_',
        'requerida' => false,
    ];

    /**
     * Instancia tipada del modelo de perfil.
     */
    private function modelo(): ProfileModel
    {
        /** @var ProfileModel $model */
        $model = $this->getModel();
        return $model;
    }

    /**
     * Crea la instancia de ProfileModel para BaseController.
     */
    protected function createModel(PDO $pdo): object
    {
        return new ProfileModel($pdo);
    }

    /**
     * Muestra la vista principal del perfil de usuario en modo solo lectura.
     */
    public function index(): void
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($idUsuario <= 0) {
            $this->redirect('auth/login');
            return;
        }

        $perfil = $this->modelo()->getPerfilUsuario($idUsuario);
        if ($perfil === null) {
            $this->handleError(
                new \RuntimeException('No se pudo encontrar la información del perfil de usuario.'),
                'Perfil de Usuario'
            );
            return;
        }

        $pageTitle       = 'Mi Perfil';
        $pageDescription = 'Información institucional, credenciales de cuenta y datos de contacto en modo solo lectura.';
        $currentNav      = 'perfil';

        $breadcrumbs = [
            ['label' => 'Inicio',    'href' => BASE_URL . 'dashboard/index'],
            ['label' => 'Mi Perfil', 'href' => null],
        ];

        require __DIR__ . '/../Views/perfil/index.php';
    }
/**
     * Actualiza o elimina la foto de perfil del usuario en sesión.
     */
    public function actualizarFoto(): void
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($idUsuario <= 0) {
            $this->jsonResponse(false, null, 'Sesión no válida.', 401);
            return;
        }

        $persona = $this->modelo()->getPersonaUsuario($idUsuario);
        if ($persona === null || empty($persona['id_persona'])) {
            $this->jsonResponse(false, null, 'No se encontró la ficha personal vinculada a este usuario.', 404);
            return;
        }

        $idPersona    = (int) $persona['id_persona'];
        $fotoAnterior = (string) ($persona['foto'] ?? '');
        $quitarFoto   = (string) ($_POST['eliminar_foto'] ?? '0') === '1';
        $fotoNueva    = null;

        if ($quitarFoto) {
            $fotoNueva = null;
        } elseif (!empty($_FILES['foto']['name'])) {
            $fotoNueva = $this->procesarFotoSubida(self::FOTO_USUARIO);
        } else {
            $this->jsonResponse(false, null, 'No se seleccionó ninguna imagen.', 422);
            return;
        }

        $ok = $this->modelo()->actualizarFotoPersona($idPersona, $fotoNueva);
        if (!$ok) {
            if ($fotoNueva !== null) {
                $this->borrarFoto($fotoNueva);
            }
            $this->jsonResponse(false, null, 'No se pudo guardar la foto de perfil en la base de datos.', 500);
            return;
        }

        // Si se reemplazó o quitó la foto anterior, se borra el archivo previo del disco
        if ($fotoAnterior !== '' && ($fotoNueva !== null || $quitarFoto) && $fotoNueva !== $fotoAnterior) {
            $this->borrarFoto($fotoAnterior);
        }

        // Actualizar sesión para que el avatar de la cabecera refleje el cambio
        $_SESSION['foto'] = $fotoNueva ? ltrim($fotoNueva, '/') : null;

        $fotoUrl = $fotoNueva ? BASE_URL . ltrim($fotoNueva, '/') : '';
        $mensaje = $quitarFoto ? 'Foto de perfil eliminada correctamente.' : 'Foto de perfil actualizada con éxito.';

        $this->jsonResponse(true, [
            'foto_url'   => $fotoUrl,
            'tiene_foto' => $fotoNueva !== null,
        ], $mensaje);
    }
}
