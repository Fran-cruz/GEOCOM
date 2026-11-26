<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Arma el libro de Excel a partir de los arrays que ya devuelve ReporteController.
// No consulta la base de datos: solo da formato.
class ReporteExcel
{
    private const MONTO = '#,##0.00';
    private const ENTERO = '#,##0';
    private const FECHA = 'dd/mm/yyyy hh:mm';

    private const TITULOS = [
        'resumen' => 'Resumen general',
        'conductor' => 'Detalle por conductor',
        'gasolinera' => 'Detalle por gasolinera',
    ];

    private const ESTADOS = [
        'completada' => 'Completada',
        'completada_tarde' => 'Completada tarde',
    ];

    public function generar(string $tipo, array $datos, array $filtros): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('GEOCOM')->setTitle('Reporte GEOCOM: ' . self::TITULOS[$tipo]);

        $this->hojaResumen($libro->getActiveSheet()->setTitle('Resumen'), $tipo, $datos, $filtros);
        $this->hojaPorMes($libro->createSheet()->setTitle('Por mes'), $datos['por_mes'] ?? []);
        $this->hojaPorCombustible($libro->createSheet()->setTitle('Por combustible'), $datos['por_combustible'] ?? []);
        $this->hojaRanking($libro->createSheet()->setTitle('Ranking'), $tipo, $datos);
        $this->hojaOrdenes($libro->createSheet()->setTitle('Ordenes'), $datos['ordenes'] ?? []);

        foreach ($libro->getAllSheets() as $hoja) {
            $ultima = Coordinate::columnIndexFromString($hoja->getHighestColumn());
            for ($col = 1; $col <= $ultima; $col++) {
                $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
            }
        }

        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    private function hojaResumen(Worksheet $hoja, string $tipo, array $datos, array $filtros): void
    {
        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;

        $periodo = match (true) {
            (bool) ($desde && $hasta) => Carbon::parse($desde)->format('d/m/Y') . ' al ' . Carbon::parse($hasta)->format('d/m/Y'),
            (bool) $desde => 'Desde el ' . Carbon::parse($desde)->format('d/m/Y'),
            (bool) $hasta => 'Hasta el ' . Carbon::parse($hasta)->format('d/m/Y'),
            default => 'Todo el historial',
        };

        $aplicados = [];
        if (isset($datos['conductor'])) {
            $aplicados[] = 'Conductor: ' . $datos['conductor']['nombre'];
        }
        if (isset($datos['gasolinera'])) {
            $aplicados[] = 'Gasolinera: ' . $datos['gasolinera']['nombre'];
        }
        if (! empty($filtros['conductor_id']) && $tipo === 'resumen') {
            $aplicados[] = 'Conductor (id): ' . $filtros['conductor_id'];
        }
        if (! empty($filtros['gasolinera_id']) && $tipo !== 'gasolinera') {
            $aplicados[] = 'Gasolinera (id): ' . $filtros['gasolinera_id'];
        }

        $cabecera = [
            ['Reporte', self::TITULOS[$tipo]],
            ['Periodo', $periodo],
            ['Filtros aplicados', $aplicados ? implode(' | ', $aplicados) : 'Sin filtros'],
        ];
        foreach ($cabecera as $i => [$etiqueta, $valor]) {
            $this->escribir($hoja, 1, $i + 1, $etiqueta);
            $this->escribir($hoja, 2, $i + 1, $valor);
        }
        $hoja->getStyle('A1:A3')->getFont()->setBold(true);
        $this->escribir($hoja, 1, 4, 'Generado');
        $this->escribir($hoja, 2, 4, Carbon::now());
        $hoja->getStyle('A4')->getFont()->setBold(true);
        $hoja->getStyle('B4')->getNumberFormat()->setFormatCode(self::FECHA);
        $hoja->getStyle('B1:B4')->getAlignment()->setHorizontal('left');

        // Indicadores: los mismos que devuelve indicadores() en el reporte
        $r = $datos['resumen'];
        $filas = [
            ['Monto total (L)', $r['monto_total'] ?? null, self::MONTO],
            ['Órdenes completadas', $r['cantidad_ordenes'] ?? null, self::ENTERO],
            ['Promedio por orden (L)', $r['promedio_orden'] ?? null, self::MONTO],
            ['Completadas tarde', $r['completadas_tarde'] ?? null, self::ENTERO],
            ['Órdenes vencidas', $r['vencidas'] ?? null, self::ENTERO],
            ['Cumplimiento (%)', $r['cumplimiento_pct'] ?? null, '0.0'],
            ['Galones estimados', $r['galones_total'] ?? null, self::MONTO],
            ['Km estimados', $r['km_total'] ?? null, self::MONTO],
            ['Precio promedio por galón (L)', $r['precio_promedio_galon'] ?? null, self::MONTO],
        ];

        $this->escribir($hoja, 1, 6, 'Indicador');
        $this->escribir($hoja, 2, 6, 'Valor');
        $this->encabezado($hoja, 'A6:B6');
        foreach ($filas as $i => [$etiqueta, $valor, $formato]) {
            $fila = 7 + $i;
            $this->escribir($hoja, 1, $fila, $etiqueta);
            $this->escribir($hoja, 2, $fila, $valor);
            $hoja->getStyle("B{$fila}")->getNumberFormat()->setFormatCode($formato);
            $hoja->getStyle("B{$fila}")->getAlignment()->setHorizontal('right');
        }
    }

    private function hojaPorMes(Worksheet $hoja, array $meses): void
    {
        $this->tabla(
            $hoja,
            1,
            ['Mes', 'Monto total (L)'],
            array_map(fn ($m) => [$m['mes'], $m['monto_total']], $meses),
            [1 => self::MONTO]
        );
    }

    private function hojaPorCombustible(Worksheet $hoja, array $combustibles): void
    {
        $this->tabla(
            $hoja,
            1,
            ['Combustible', 'Órdenes', 'Monto total (L)', 'Galones estimados', 'Km estimados'],
            array_map(fn ($c) => [
                ucfirst((string) $c['tipo_combustible']),
                $c['cantidad_ordenes'],
                $c['monto_total'],
                $c['galones_total'],
                $c['km_total'],
            ], $combustibles),
            [1 => self::ENTERO, 2 => self::MONTO, 3 => self::MONTO, 4 => self::MONTO]
        );
    }

    // Cada tipo de reporte trae sus propios rankings
    private function hojaRanking(Worksheet $hoja, string $tipo, array $datos): void
    {
        $secciones = match ($tipo) {
            'conductor' => [
                'Gasolineras más usadas' => $datos['por_gasolinera'] ?? [],
                'Vehículos' => $datos['por_vehiculo'] ?? [],
            ],
            'gasolinera' => [
                'Conductores' => $datos['por_conductor'] ?? [],
            ],
            default => [
                'Top conductores' => $datos['top_conductores'] ?? [],
                'Top gasolineras' => $datos['top_gasolineras'] ?? [],
            ],
        };

        $fila = 1;
        foreach ($secciones as $titulo => $items) {
            $this->escribir($hoja, 1, $fila, $titulo);
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);

            $fila = $this->tabla(
                $hoja,
                $fila + 1,
                ['Posición', 'Nombre', 'Órdenes', 'Monto total (L)', 'Galones estimados', 'Km estimados'],
                array_map(fn ($item, $i) => [
                    $i + 1,
                    $item['nombre'] ?? trim(($item['placa'] ?? '') . ' ' . ($item['descripcion'] ?? '')),
                    $item['cantidad_ordenes'],
                    $item['monto_total'],
                    $item['galones_total'],
                    $item['km_total'],
                ], $items, array_keys($items)),
                [2 => self::ENTERO, 3 => self::MONTO, 4 => self::MONTO, 5 => self::MONTO]
            );
        }
    }

    // Una fila por línea de combustible
    private function hojaOrdenes(Worksheet $hoja, array $ordenes): void
    {
        $this->tabla(
            $hoja,
            1,
            ['Fecha completada', 'Orden', 'Estado', 'Conductor', 'Gasolinera', 'Vehículo / planta generadora', 'Combustible', 'Galones estimados', 'Monto (L)'],
            array_map(fn ($o) => [
                Carbon::parse($o['fecha_completada']),
                $o['numero_orden'],
                self::ESTADOS[$o['estado']] ?? $o['estado'],
                $o['conductor'],
                $o['gasolinera'],
                $o['destino'],
                ucfirst((string) $o['tipo_combustible']),
                $o['galones_estimados'],
                $o['monto'],
            ], $ordenes),
            [0 => self::FECHA, 7 => self::MONTO, 8 => self::MONTO]
        );
    }

    // Escribe encabezados, filas y formatos por columna. Devuelve la siguiente fila libre.
    private function tabla(Worksheet $hoja, int $fila, array $encabezados, array $filas, array $formatos = []): int
    {
        $ultimaColumna = Coordinate::stringFromColumnIndex(count($encabezados));

        foreach ($encabezados as $i => $texto) {
            $this->escribir($hoja, $i + 1, $fila, $texto);
        }
        $this->encabezado($hoja, "A{$fila}:{$ultimaColumna}{$fila}");

        $inicio = $fila + 1;
        foreach (array_values($filas) as $f => $valores) {
            foreach ($valores as $i => $valor) {
                $this->escribir($hoja, $i + 1, $inicio + $f, $valor);
            }
        }

        if ($filas) {
            $fin = $inicio + count($filas) - 1;
            foreach ($formatos as $i => $formato) {
                $columna = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->getStyle("{$columna}{$inicio}:{$columna}{$fin}")->getNumberFormat()->setFormatCode($formato);
            }
        }

        return $inicio + count($filas) + 1;
    }

    // Negrita con fondo gris
    private function encabezado(Worksheet $hoja, string $rango): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true);
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
    }

    // Los números y fechas se guardan como valores reales; los textos siempre como
    // texto (nunca como fórmula), por si un campo libre empieza con "=".
    private function escribir(Worksheet $hoja, int $columna, int $fila, mixed $valor): void
    {
        if ($valor === null || $valor === '') {
            return;
        }

        $celda = Coordinate::stringFromColumnIndex($columna) . $fila;

        if ($valor instanceof \DateTimeInterface) {
            $hoja->setCellValueExplicit($celda, Date::PHPToExcel($valor), DataType::TYPE_NUMERIC);
        } elseif (is_int($valor) || is_float($valor)) {
            $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_NUMERIC);
        } else {
            $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
        }
    }
}
