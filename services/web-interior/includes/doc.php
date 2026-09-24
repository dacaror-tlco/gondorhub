<?php
/**
 * Piezas comunes de los documentos imprimibles (presupuestos, balances,
 * fichas…). El estilo imita las tablas verdes de los Excel de la Zona.
 */

/** Barra superior de los documentos (no sale al imprimir). */
function barra_documento(string $volver, string $textoVolver, string $extra = ''): void
{ ?>
  <div class="barra-doc no-imprimir">
    <a href="<?= h($volver) ?>" class="boton boton--fantasma boton--pequeno">← <?= h($textoVolver) ?></a>
    <?= $extra ?>
    <button type="button" class="boton boton--primario" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>
<?php }

/** Texto de comentario: párrafos y **negrita** (sale en verde). */
function texto_comentario(string $s): string
{
    $html = h(trim($s));
    $html = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $html);
    return nl2br($html);
}

/**
 * Tabla CONCEPTO / CANTIDAD / PRECIO / TOTAL de un bloque (ingresos o gastos).
 * $filas: [[concepto, cantidad, precio, total], …]
 */
function filas_bloque(string $titulo, float $total, array $filas): void
{ ?>
  <tr class="dt-bloque"><th colspan="3"><?= h(mayusculas($titulo)) ?></th><th class="num"><?= formato_euros($total) ?></th></tr>
  <tr class="dt-cab"><th>CONCEPTO</th><th>CANTIDAD</th><th>PRECIO</th><th>TOTAL</th></tr>
  <?php foreach ($filas as [$c, $cant, $precio, $tot]): ?>
    <tr class="dt-fila">
      <td><?= h(mayusculas($c)) ?></td>
      <td class="centro"><?= $cant === null ? '' : formato_num($cant) ?></td>
      <td class="centro"><?= $precio === null ? '' : formato_euros($precio) ?></td>
      <td class="centro"><?= formato_euros($tot) ?></td>
    </tr>
  <?php endforeach; ?>
<?php }
