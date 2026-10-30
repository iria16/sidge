<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\InstitucionModel;
use App\Support\Instituciones;
use PDO;
use Throwable;

/**
 * InstitucionTrait
 *
 * Alta rápida de una institución desde cualquier wizard que la necesite
 * como catálogo de apoyo (institución de procedencia de un estudiante en
 * EnrollmentController, institución de formación académica en
 * EmployeeController, o cualquier módulo futuro que registre instituciones
 * al vuelo).
 *
 * Por qué un trait y no un servicio inyectado: todos los controladores del
 * sistema ya extienden BaseController y resuelven sus dependencias (PDO,
 * jsonResponse, capitalizarTitulo) a través de `$this`, no por constructor.
 * Un trait reutiliza ese mismo mecanismo sin introducir un patrón distinto
 * solo para este caso.
 *
 * Contrato con la clase que lo usa (ver los `abstract` al final): debe
 * extender BaseController, directa o indirectamente, para heredar
 * getModel(), jsonResponse(), jsonError() y capitalizarTitulo() (esta
 * última vía TextoTrait, que BaseController ya incluye). No hace falta
 * declarar nada más: los `abstract` solo documentan la dependencia y
 * permiten que el editor resuelva las llamadas dentro de este archivo.
 *
 * Lo que varía por módulo es la regla de qué tipo de institución es válido
 * en ese contexto. Esa regla no vive aquí: cada controlador la aporta como
 * closure ($validarTipo) y recibe el CÓDIGO del tipo. App\Support\
 * Instituciones es el enum con los tipos y sus reglas (TIPO_TITULO_ACADEMICO,
 * TIPO_PREESCOLAR, TIPO_ESCUELA) y `institucion.tipo` guarda ese código, así
 * que no hay nada que traducir: este trait solo resuelve lo que es común a
 * cualquier alta de institución (datos obligatorios, tipo válido, nombre
 * duplicado, inserción y respuesta JSON).
 *
 * @author Logística
 * @package App\Traits
 */
trait InstitucionTrait
{
    /**
     * Valida, crea y responde el alta de una institución.
     *
     * Flujo:
     *  1. Nombre y tipo son obligatorios (422 si falta alguno).
     *  2. El tipo tiene que ser uno del enum; si no, 422 (el <select> se
     *     puede manipular desde el navegador).
     *  3. $validarTipo decide si ese tipo es válido EN ESE CONTEXTO; recibe
     *     el código y debe devolver null si es válido o el mensaje de error
     *     si no lo es.
     *  4. Nombre duplicado (409): mismo criterio en todos los módulos, una
     *     institución es la misma entidad la registre quien la registre.
     *  5. Inserción; cualquier excepción del modelo se registra con
     *     jsonError() y corta la ejecución con un mensaje neutro.
     *
     * @param callable(string): ?string $validarTipo Regla de tipo propia del
     *        módulo que llama, sobre el código del enum. Devuelve el mensaje de
     *        error, o null si el tipo es válido en ese contexto.
     */
    private function guardarInstitucionComun(callable $validarTipo): void
    {
        // capitalizarTituloConSiglas(), no capitalizarTitulo(): una institución
        // llamada UNEFA debe conservarla en mayúsculas, y da igual que se cree
        // desde Catálogos o desde estos dos wizards.
        $nombre = $this->capitalizarTituloConSiglas((string) ($_POST['nombre'] ?? ''));
        $tipo   = trim((string) ($_POST['tipo'] ?? ''));

        // Procedencia. Son los mismos campos que envía el formulario de
        // Catálogos, por eso se leen aquí y no en cada controlador.
        $esExtranjera = ((string) ($_POST['es_extranjera'] ?? '0')) === '1';
        $idEstadoGeo  = (int) ($_POST['id_estado_geo'] ?? 0);
        $idMunicipio  = (int) ($_POST['id_municipio'] ?? 0);
        $idParroquia  = (int) ($_POST['id_parroquia'] ?? 0);
        $codigoDea    = mb_strtoupper(trim((string) ($_POST['codigo_dea'] ?? '')), 'UTF-8');

        if ($nombre === '') {
            $this->jsonResponse(
                false,
                ['nombre' => 'Escribe el nombre de la institución.'],
                'Faltan datos de la institución.',
                422
            );
        }

        if ($tipo === '') {
            $this->jsonResponse(
                false,
                ['tipo' => 'Selecciona el tipo de institución.'],
                'Faltan datos de la institución.',
                422
            );
        }

        if (!Instituciones::esValido($tipo)) {
            $this->jsonResponse(
                false,
                ['tipo' => 'El tipo de institución seleccionado no existe.'],
                'Tipo de institución no válido.',
                422
            );
        }

        // El id de la FK no se acepta del formulario: lo resuelve el enum. Sin
        // esto el INSERT mandaría NULL a una columna NOT NULL.
        if (Instituciones::idCatalogo($tipo) === null) {
            $this->jsonResponse(
                false,
                ['tipo' => 'El tipo de institución no tiene categoría asignada.'],
                'Tipo de institución sin categoría.',
                422
            );
        }

        $model = new InstitucionModel($this->conexion());

        // ---------- Procedencia ----------
        //
        // Dos caminos que no se mezclan, los mismos que aplica
        // CatalogController::validarUbicacion(). Que la petición venga de un
        // lado o del otro no cambia las reglas: si divergieran, una institución
        // creada por Catálogos y otra creada por el wizard acabarían con datos
        // distintos según por dónde se tocó.
        //
        //   Nacional    estado, municipio y parroquia, comprobando que cada hijo
        //              pertenezca a su padre. El código DEA solo en preescolar y
        //              escuela.
        //   Extranjera  se ignoran los datos nacionales aunque vengan. País,
        //              ciudad y dirección. La referencia del plantel es
        //              opcional. El DEA se ignora siempre.
        if ($esExtranjera) {
            // Para institución extranjera solo se exige nombre y tipo.
            // Se ignoran DEA y ubicación venezolana.
            $idEstadoGeo = 0;
            $idMunicipio = 0;
            $idParroquia = 0;
            $codigoDea   = '';
        } else {
            $errores = $this->validarCadenaNacional($model, $idEstadoGeo, $idMunicipio, $idParroquia);

            if ($errores !== []) {
                $this->jsonResponse(false, $errores, 'Falta la ubicación de la institución.', 422);
            }

            // El DEA es solo de preescolar y escuela. Las universidades no lo
            // usan: no se pide ni se valida.
            if (Instituciones::esDeEstudiante($tipo)) {
                if ($codigoDea === '') {
                    $this->jsonResponse(
                        false,
                        ['codigo_dea' => 'Escribe el código DEA de la institución.'],
                        'Falta el código DEA.',
                        422
                    );
                }

                if (!preg_match('/^[A-Z0-9][A-Z0-9\-]{0,19}$/', $codigoDea)) {
                    $this->jsonResponse(
                        false,
                        ['codigo_dea' => 'El código DEA solo admite letras, números y guion (máximo 20).'],
                        'Código DEA con formato inválido.',
                        422
                    );
                }

                if ($model->existeCodigoDea($codigoDea)) {
                    $this->jsonResponse(
                        false,
                        ['codigo_dea' => 'Ya existe una institución con ese código DEA.'],
                        'Código DEA repetido.',
                        409
                    );
                }
            }
        }

        $errorTipo = $validarTipo($tipo);
        if ($errorTipo !== null) {
            $this->jsonResponse(
                false,
                ['tipo' => $errorTipo],
                'Tipo de institución no válido.',
                422
            );
        }

        if ($model->existeNombre($nombre)) {
            $this->jsonResponse(
                false,
                ['nombre' => 'Ya existe una institución con ese nombre.'],
                'Esa institución ya está registrada.',
                409
            );
        }

        $idInstitucion = 0;
        try {
            $idInstitucion = $model->createAndGetId([
                'nombre'               => $nombre,
                'tipo'                 => $tipo,
                'es_extranjera'        => $esExtranjera ? 1 : 0,
                'id_estado_geo'        => $idEstadoGeo,
                'id_municipio'         => $idMunicipio,
                'id_parroquia'         => $idParroquia,
                'codigo_dea'           => $codigoDea,
            ]);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        if ($idInstitucion <= 0) {
            $this->jsonResponse(false, null, 'No se pudo guardar la institución.', 500);
        }

        $this->jsonResponse(true, [
            'id'     => $idInstitucion,
            'nombre' => $nombre,
        ], 'Institución registrada correctamente.');
    }

    /**
     * Cadena nacional: estado, municipio y parroquia, los tres obligatorios y
     * cada uno pertenecerle al de arriba. Devuelve todos los errores juntos, no
     * solo el primero, para que el formulario los muestre de una vez.
     *
     * @return array<string, string>
     */
    private function validarCadenaNacional(
        InstitucionModel $model,
        int $idEstado,
        int $idMunicipio,
        int $idParroquia
    ): array {
        $errores = [];

        if ($idEstado <= 0) {
            $errores['id_estado_geo'] = 'Selecciona el estado donde está la institución.';
        } elseif (!$model->existeEstadoGeo($idEstado)) {
            $errores['id_estado_geo'] = 'El estado seleccionado no existe.';
        }

        if ($idMunicipio <= 0) {
            $errores['id_municipio'] = 'Selecciona el municipio.';
        } elseif (!$model->municipioPerteneceAlEstado($idMunicipio, $idEstado)) {
            $errores['id_municipio'] = 'El municipio seleccionado no pertenece a ese estado.';
        }

        if ($idParroquia <= 0) {
            $errores['id_parroquia'] = 'Selecciona la parroquia.';
        } elseif (!$model->parroquiaPerteneceAlMunicipio($idParroquia, $idMunicipio)) {
            $errores['id_parroquia'] = 'La parroquia seleccionada no pertenece a ese municipio.';
        }

        return $errores;
    }


    // ---------- Contrato con BaseController ----------
    // Sin cuerpo a propósito: no reimplementan nada, solo declaran la firma
    // exacta que este trait necesita de la clase que lo use, para que el
    // editor resuelva las llamadas y para que PHP falle en tiempo de
    // declaración (no en producción) si algún día se usa en una clase que
    // no las tenga.

    abstract protected function conexion(): PDO;

    abstract protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never;

    abstract protected function jsonError(Throwable $e, string $seccion, int $code = 500): never;

    abstract protected function capitalizarTituloConSiglas(string $texto): string;
}