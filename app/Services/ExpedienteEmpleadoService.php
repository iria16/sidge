<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmployeeModel;
use App\Models\InstitucionModel;
use App\Models\ReposoEmployeeModel;
use App\Support\Configuracion;
use App\Support\FichaEmpleadoPdf;
use App\Support\Instituciones;

final class ExpedienteEmpleadoService
{
    private EmployeeModel $employees;
    private ReposoEmployeeModel $reposos;
    private InstitucionModel $instituciones;

    public function __construct(
        EmployeeModel $employees,
        ReposoEmployeeModel $reposos,
        InstitucionModel $instituciones
    ) {
        $this->employees = $employees;
        $this->reposos = $reposos;
        $this->instituciones = $instituciones;
    }

    /**
     * Carga la información del expediente y los catálogos que necesitan sus modales.
     *
     * @return array<string, mixed>|null
     */
    public function getExpediente(int $id): ?array
    {
        $empleado = $this->employees->getById($id);
        if ($empleado === null) {
            return null;
        }

        $catalogos = $this->employees->getCatalogosFormulario();

        return [
            'empleado'        => $empleado,
            'formacion'       => $this->employees->getFormacionAcademica($id),
            'asignaciones'    => $this->employees->getAsignacionesDocente($id),
            'historial'       => $this->employees->getHistorialEstados($id),
            'reposos'         => $this->reposos->getPorEmpleado($id),
            'cargos'          => $this->employees->getCargos(),
            'gradosAcademicos'=> $catalogos['grados_academicos'] ?? [],
            'titulos'         => $catalogos['titulos'] ?? [],
            'instituciones'   => $catalogos['instituciones'] ?? [],
            'tiposInstitucion'=> $this->instituciones
                ->tiposFormulario([Instituciones::TIPO_TITULO_ACADEMICO]),
            'estadosGeo'      => $this->instituciones->getEstadosGeo(),
            'estados'         => $catalogos['estados'] ?? [],
            'turnos'          => $catalogos['turnos'] ?? [],
            'nacionalidades'  => $this->employees->getNacionalidades(),
        ];
    }

    /**
     * Genera el PDF integral del expediente; la respuesta HTTP permanece en el controlador.
     */
    public function generarPdf(int $id): ?array
    {
        $empleado = $this->employees->getById($id);
        if ($empleado === null) {
            return null;
        }

        $fotoRuta = (string) ($empleado['avatar_foto'] ?? $empleado['foto'] ?? '');
        $fotoDataUri = $fotoRuta !== '' ? FichaEmpleadoPdf::imagenADataUri($fotoRuta) : null;

        return [
            'empleado' => $empleado,
            'contenido' => FichaEmpleadoPdf::generar(
                Configuracion::getAll(),
                $empleado,
                $this->employees->getFormacionAcademica($id),
                $this->employees->getAsignacionesDocente($id),
                $this->reposos->getPorEmpleado($id),
                $this->employees->getHistorialEstados($id),
                null,
                $fotoDataUri
            ),
        ];
    }
}
