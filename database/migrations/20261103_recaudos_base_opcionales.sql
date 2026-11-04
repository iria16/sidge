-- Los únicos recaudos obligatorios son los condicionales (prosecución, permiso LOPNNA,
-- autorización de retiro y cédula de la persona autorizada). Foto del alumno, partida,
-- vacuna, foto y cédula del representante pasan a opcionales también en lo ya guardado.
UPDATE estudiante_documento ed
  JOIN tipo_documento td ON td.id_tipo_documento = ed.id_tipo_documento
   SET ed.requerido = 0
 WHERE td.codigo IN ('foto_alumno', 'partida_nacimiento', 'constancia_vacuna',
                     'foto_representante', 'cedula_representante')
   AND ed.requerido = 1;
