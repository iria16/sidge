<?php
declare(strict_types=1);

/**
 * Partial: docente/partials/table-body.php
 * Cuerpo de la tabla de nómina (con agrupación de autorizados).
 *
 * @var array  $filas
 * @var array  $columnas
 * @var string $vista
 * @var array  $meses
 * @var int    $mesActual
 * @var callable $e
 * @var callable $telefonoConWa
 * @var callable $telefonoAutConWa
 * @var callable $fmtFecha
 */
?>
        <tbody id="nominaCuerpo">
<?php if ($filas === []): ?>
          <tr>
            <td colspan="<?= count($columnas) ?>" class="text-center text-muted py-4">
              No hay estudiantes que coincidan con los filtros.
            </td>
          </tr>
<?php else: ?>
<?php /* GRUPOS: una celda de estudiante y una de representante por alumno,
                   con rowspan sobre el número de autorizados. El nombre del alumno
                   y el del representante salen una sola vez, arriba de su grupo. */ ?>
<?php foreach ($filas as $i => $f):
              $esAutorizados = ($vista === 'autorizados');
              $autorizados = $f['autorizados'] ?? [];
              $nFilas = count($autorizados) > 0 ? count($autorizados) : 1;
              /* Cada persona autorizada ocupa una fila; el alumno sin ninguno
                 ocupa una sola, que dice que solo el representante puede retirar. */
              $grupos = $autorizados !== [] ? $autorizados : [null];
              foreach ($grupos as $k => $aut):
                  $primera = ($k === 0);
              ?>
            <tr<?= $primera && $esAutorizados ? ' class="nomina-grupo-inicio"' : '' ?>>
<?php foreach ($columnas as $clave => $titulo):
                  /* Solo el estudiante se agrupa: la columna Representante legal ya
                     no está en esta vista. */
                  $celdaGrupo = $esAutorizados && $clave === 'estudiante';
                  $salta = $celdaGrupo && !$primera;
                  if ($salta) { continue; }
                  $atributos = $celdaGrupo
                      ? ' rowspan="' . $nFilas . '" class="nomina-col--' . $e($clave) . '"'
                      : ' class="nomina-col--' . $e($clave) . '"';
                  ?>
                <td<?= $atributos ?>>
<?php switch ($clave):
                    case 'n': ?>
                      <?= $i + 1 ?>
<?php break;
                    case 'cedula': ?>
                      <?= $f['documento'] !== '' ? $e($f['documento']) : '—' ?>
<?php break;
                    case 'estudiante': ?>
                      <?= $e($f['estudiante']) ?>
<?php break;
                    case 'sexo': ?>
                      <?= $f['sexo'] === 'F' ? 'F' : 'M' ?>
<?php break;
                    case 'fecha_nac': ?>
                      <?= $e($fmtFecha($f['fecha_nacimiento'])) ?>
<?php break;
                    case 'edad': ?>
                      <?= (int) $f['edad'] ?>
<?php break;
                    case 'representante': ?>
<?php if (!empty($f['representante'])): ?>
                        <div><?= $e($f['representante']) ?></div>
<?php if (!empty($f['parentesco'])): ?>
                          <div class="text-muted small">(<?= $e($f['parentesco']) ?>)</div>
<?php endif; ?>
<?php else: ?>
                        <span class="text-muted">Sin representante</span>
<?php endif; ?>
<?php break;
                    case 'cedula_rep': ?>
                      <?= $f['rep_documento'] !== '' ? $e($f['rep_documento']) : '—' ?>
<?php break;
                    case 'firma': ?>
                      <span class="nomina-firma" aria-label="Espacio para firmar"></span>
<?php break;
                    case 'telefonos_rep': ?>
<?php if ($f['rep_telefonos'] === []): ?>
                        <span class="text-muted">Sin teléfono</span>
<?php else: ?>
<?php foreach ($f['rep_telefonos'] as $tel): ?>
                          <div><?= $telefonoConWa($tel, true) ?></div>
<?php endforeach; ?>
<?php endif; ?>
<?php break;
                    case 'direccion':
                    case 'direccion_rep': ?>
<?php if (($f['rep_direccion'] ?? '') === ''): ?>
                        <span class="text-muted">Sin dirección</span>
<?php else: ?>
                        <div class="celda-wrap"><?= $e($f['rep_direccion']) ?></div>
<?php endif; ?>
<?php break;
                    case 'autorizados':
                    case 'autorizado': ?>
<?php
                      $auts = $f['autorizados'] ?? [];
                      if ($aut !== null) {
                          $auts = [$aut];
                      }
                      if ($auts === []): ?>
                        <span class="text-muted small">Solo el representante</span>
<?php else: ?>
                        <div class="d-flex flex-column gap-1 celda-wrap">
<?php foreach ($auts as $a): ?>
                            <div class="small">
                              <strong><?= $e($a['nombre']) ?></strong>
<?php if (!empty($a['parentesco'])): ?>
                                <span class="text-muted">(<?= $e($a['parentesco']) ?>)</span>
<?php endif; ?>
<?php if (!empty($a['telefonos'])): ?>
                                <div class="text-secondary small">
                                  <?= $e(implode(' · ', $a['telefonos'])) ?>
                                </div>
<?php endif; ?>
                            </div>
<?php endforeach; ?>
                        </div>
<?php endif; ?>
<?php break;
                    case 'parentesco_aut': ?>
<?php if ($aut === null): ?>
                        <span class="text-muted">—</span>
<?php elseif ($aut['parentesco'] !== ''): ?>
                        <?= $e($aut['parentesco']) ?>
<?php else: ?>
                        <span class="text-muted">—</span>
<?php endif; ?>
<?php break;
                    case 'telefono_aut': ?>
<?php if ($aut === null || $aut['telefonos'] === []): ?>
                        <div class="nomina-tel-linea">
                          <span class="nomina-tel-num text-muted">Sin teléfono</span>
                          <span class="nomina-tel-ico"></span>
                        </div>
<?php else: ?>
<?php foreach ($aut['telefonos'] as $tel): ?>
                          <?= $telefonoAutConWa($tel) ?>
<?php endforeach; ?>
<?php endif; ?>
<?php break;
                    case 'dia': ?>
                      <?= (int) $f['dia_cumple'] ?>
<?php break;
                    case 'edad_cumple': ?>
                      <?= (int) $f['edad_cumple'] ?> años
<?php /* Si el mes ya pasó, el cumpleaños también: se marca para
                               que el listado no insinúe que está por llegar. */ ?>
<?php if (!empty($f['cumple_ya_paso'])): ?>
                        <span class="nomina-cumple-ok">Ya cumplió</span>
<?php endif; ?>
<?php break;
                    case 'boleta': ?>
                      <?php if (!empty($f['literal'])): ?>
                        <?php
                        $urlBoletaInd = BASE_URL . 'certificadoCalificaciones/generarIndividual?id_estudiante=' . (int) $f['id_estudiante'] . '&id_estructura=' . $idEstructura;
                        $nomEstudiante = trim(($f['primer_apellido'] ?? '') . ' ' . ($f['segundo_apellido'] ?? '') . ', ' . ($f['primer_nombre'] ?? '') . ' ' . ($f['segundo_nombre'] ?? ''));
                        ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-preview-boleta-individual"
                                data-url-pdf="<?= $e($urlBoletaInd) ?>"
                                data-estudiante="<?= $e($nomEstudiante) ?>"
                                title="Visualizar Certificado de Calificaciones">
                          <i class="bi bi-eye" aria-hidden="true"></i>
                          <span>Visualizar certificado</span>
                        </button>
                      <?php else: ?>
                        <span class="text-muted small">Requiere literal</span>
                      <?php endif; ?>
<?php break;

                  endswitch; ?>
                </td>
<?php endforeach; ?>
            </tr>
<?php endforeach; /* una fila por persona autorizada */ ?>
<?php endforeach; /* un grupo por estudiante */ ?>
<?php endif; ?>
        </tbody>
      </table>
