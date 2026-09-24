<?php
/**
 * Partidas contables de la Zona Interior (hoja LISTA PARTIDAS de BALANCE 2026.xlsx).
 *
 * Cada tipo (ingreso / gasto) tiene grupos, y cada grupo sus partidas.
 * El código de la partida (I1.1, G6.3…) es lo que se guarda en cada movimiento,
 * así que NO cambies códigos que ya estén en uso; los nombres sí se pueden retocar.
 */

const PARTIDAS = [
    'ingreso' => [
        'I1' => ['Actividades', [
            'I1.1' => 'Campamentos',
            'I1.2' => 'Acampadas',
            'I1.3' => 'Convivencia',
            'I1.4' => 'Festival',
            'I1.5' => 'Día Juniors',
            'I1.6' => 'Día Padres/Familia',
            'I1.7' => 'Fomento Asociacionismo',
            'I1.8' => 'Fomento Identidad',
        ]],
        'I2' => ['Cuota zona', [
            'I2.1' => 'Cuota Zona',
        ]],
        'I3' => ['Aportaciones externas', [
            'I3.1' => 'Subvenciones Oficiales',
            'I3.2' => 'Otras subvenciones',
        ]],
        'I4' => ['Materiales propios de la actividad', [
            'I4.1' => 'Materiales propios de la actividad',
        ]],
        'I5' => ['Aportaciones internas', [
            'I5.1' => 'Parroquia',
            'I5.2' => 'Reducción Cuota',
            'I5.3' => 'Ayudas campamento',
            'I5.4' => 'Ayudas asociacionismo',
            'I5.5' => 'Ayudas identidad',
        ]],
        'I6' => ['Otros ingresos', [
            'I6.1' => 'Otros ingresos',
            'I6.2' => 'Lotería y Rifa',
            'I6.3' => 'Merchandising',
            'I6.4' => 'Donaciones',
        ]],
    ],
    'gasto' => [
        'G1' => ['Actividades', [
            'G1.1' => 'Campamentos',
            'G1.2' => 'Acampadas',
            'G1.3' => 'Convivencia',
            'G1.4' => 'Festival',
            'G1.5' => 'Día Juniors',
            'G1.6' => 'Día Padres/Familia',
            'G1.7' => 'Fomento Asociacionismo',
            'G1.8' => 'Fomento Identidad',
            'G1.9' => 'Aniversarios',
        ]],
        'G2' => ['Cuota diocesana', [
            'G2.1' => 'Cuota diocesana',
        ]],
        'G3' => ['Transporte', [
            'G3.1' => 'Transporte',
        ]],
        'G4' => ['Alimentación', [
            'G4.1' => 'Alimentación',
        ]],
        'G5' => ['Difusión', [
            'G5.1' => 'Redes sociales',
            'G5.2' => 'Materiales',
            'G5.3' => 'Merchandising',
        ]],
        'G6' => ['Material fungible', [
            'G6.1' => 'Material oficina',
            'G6.2' => 'Material talleres',
            'G6.3' => 'Material imprenta',
            'G6.4' => 'Material multimedia',
            'G6.5' => 'Bazar',
            'G6.6' => 'Otros gastos materiales',
        ]],
        'G7' => ['Material inventariable', [
            'G7.1' => 'Material informático',
            'G7.2' => 'Equipamiento',
            'G7.3' => 'Materiales identitarios',
            'G7.4' => 'Biblioteca',
        ]],
        'G8' => ['Sociales', [
            'G8.1' => 'Cursos educadores',
            'G8.2' => 'Becas a miembros',
            'G8.3' => 'Equipo coordinador',
        ]],
        'G9' => ['Corrientes', [
            'G9.1' => 'Alquiler',
            'G9.2' => 'Consumo agua',
            'G9.3' => 'Consumo electricidad',
            'G9.4' => 'Teléfono e internet',
            'G9.5' => 'Correo',
        ]],
        'G10' => ['Reposición y conservación', [
            'G10.1' => 'Mantenimiento',
            'G10.2' => 'Limpieza',
            'G10.3' => 'Reparaciones y conservación',
        ]],
        'G11' => ['Financieros', [
            'G11.1' => 'Financieros (comisiones)',
        ]],
        'G12' => ['Otros', [
            'G12.1' => 'Otros',
            'G12.2' => 'Botiquín',
            'G12.3' => 'Aportación Parroquia',
        ]],
    ],
];

const TIPOS_MOVIMIENTO = [
    'ingreso' => 'Ingreso',
    'gasto'   => 'Gasto',
];

/** Partidas que usan otras secciones al apuntar movimientos automáticamente. */
const PARTIDA_CUOTA_ZONA = 'I2.1';
const PARTIDA_TRANSPORTE = 'G3.1';

/** Busca una partida por código. Devuelve [tipo, grupo, nombre] o null. */
function partida_info(string $codigo): ?array
{
    foreach (PARTIDAS as $tipo => $grupos) {
        foreach ($grupos as $g => [, $partidas]) {
            if (isset($partidas[$codigo])) {
                return [$tipo, $g, $partidas[$codigo]];
            }
        }
    }
    return null;
}
