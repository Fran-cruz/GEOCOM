<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de órdenes GEOCOM</title>
    <style>
        @page { margin: 28px 30px 36px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 3px solid #176b55; margin-bottom: 16px; padding-bottom: 12px; }
        .brand { color: #176b55; font-size: 19px; font-weight: bold; letter-spacing: 1px; }
        .subtitle { color: #5b6575; font-size: 9px; margin-top: 3px; }
        .report-title { color: #172033; font-size: 16px; margin-top: 12px; }
        .metadata { color: #4b5563; line-height: 1.7; margin: 12px 0; }
        .section-title { border-bottom: 1px solid #d6dce5; color: #176b55; font-size: 11px; margin: 15px 0 7px; padding-bottom: 4px; }
        .filters { border: 1px solid #d6dce5; border-radius: 3px; padding: 8px; }
        .filter { display: inline-block; margin: 2px 12px 2px 0; }
        .summary { border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px; width: 100%; }
        .summary td { background: #f1f6f4; border: 1px solid #d9e7e2; padding: 9px; width: 33.3%; }
        .summary-label { color: #5b6575; display: block; font-size: 8px; text-transform: uppercase; }
        .summary-value { color: #176b55; display: block; font-size: 15px; font-weight: bold; margin-top: 4px; }
        .states { border-collapse: collapse; margin-top: 8px; width: 100%; }
        .states th, .states td { border: 1px solid #d6dce5; padding: 5px 7px; text-align: left; }
        .states th { background: #f3f5f8; }
        .orders { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .orders th, .orders td { border: 1px solid #d6dce5; overflow-wrap: break-word; padding: 5px; text-align: left; vertical-align: top; }
        .orders th { background: #176b55; color: #fff; font-size: 8px; }
        .orders td { font-size: 7.5px; }
        .orders tbody tr:nth-child(even) { background: #f7f9fb; }
        .amount { text-align: right !important; white-space: nowrap; }
        .note { color: #5b6575; font-size: 8px; margin-top: 9px; }
        .footer { border-top: 1px solid #d6dce5; bottom: -20px; color: #718096; font-size: 8px; left: 0; padding-top: 6px; position: fixed; right: 0; }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">GEOCOM</div>
        <div class="subtitle">Gestión de operaciones y combustible</div>
        <h1 class="report-title">Reporte de órdenes y consumos</h1>
    </header>

    <section class="metadata">
        <p><strong>Generado:</strong> {{ $generadoEn }}</p>
        <p><strong>Exportado por:</strong> {{ $usuario->name }} ({{ $usuario->email }})</p>
    </section>

    <h2 class="section-title">Filtros aplicados</h2>
    <div class="filters">
        @forelse ($filtrosAplicados as $etiqueta => $valor)
            <span class="filter"><strong>{{ $etiqueta }}:</strong> {{ $valor }}</span>
        @empty
            <span>Sin filtros; se muestran todas las órdenes.</span>
        @endforelse
    </div>

    <h2 class="section-title">Resumen ejecutivo</h2>
    <table class="summary">
        <tr>
            <td>
                <span class="summary-label">Órdenes filtradas</span>
                <span class="summary-value">{{ $resumen['total'] }}</span>
            </td>
            <td>
                <span class="summary-label">Órdenes ejecutadas</span>
                <span class="summary-value">{{ $resumen['ordenes_ejecutadas'] }}</span>
            </td>
            <td>
                <span class="summary-label">Monto registrado en órdenes ejecutadas</span>
                <span class="summary-value">L {{ number_format($resumen['monto_consumido'], 2, ',', '.') }}</span>
            </td>
        </tr>
    </table>

    <table class="states">
        <thead>
            <tr>
                <th>Estado</th>
                <th>Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resumen['por_estado'] as $estado => $cantidad)
                <tr>
                    <td>{{ $etiquetasEstados[$estado] ?? $estado }}</td>
                    <td>{{ $cantidad }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="section-title">Detalle de órdenes y consumos</h2>
    <table class="orders">
        <thead>
            <tr>
                <th style="width: 10%">Orden</th>
                <th style="width: 10%">Asignación</th>
                <th style="width: 13%">Conductor</th>
                <th style="width: 12%">Gasolinera</th>
                <th style="width: 12%">Estado</th>
                <th style="width: 13%">Destino</th>
                <th style="width: 8%">Combustible</th>
                <th class="amount" style="width: 11%">Monto asignado</th>
                <th class="amount" style="width: 11%">Monto contabilizado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ordenes as $orden)
                @forelse ($orden->detalles as $detalle)
                    <tr>
                        <td>{{ $orden->numero_orden }}</td>
                        <td>{{ $orden->fecha_asignacion?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $orden->conductor?->usuario?->name ?? '—' }}</td>
                        <td>{{ $orden->gasolinera?->nombre ?? '—' }}</td>
                        <td>{{ $etiquetasEstados[$orden->estado] ?? $orden->estado }}</td>
                        <td>{{ $detalle->vehiculo?->placa ?? $detalle->descripcion_mgp ?? 'MGP' }}</td>
                        <td>{{ $detalle->tipo_combustible }}</td>
                        <td class="amount">L {{ number_format((float) $detalle->monto, 2, ',', '.') }}</td>
                        <td class="amount">
                            @if (in_array($orden->estado, $estadosEjecutados, true))
                                L {{ number_format((float) $detalle->monto, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td>{{ $orden->numero_orden }}</td>
                        <td>{{ $orden->fecha_asignacion?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $orden->conductor?->usuario?->name ?? '—' }}</td>
                        <td>{{ $orden->gasolinera?->nombre ?? '—' }}</td>
                        <td>{{ $etiquetasEstados[$orden->estado] ?? $orden->estado }}</td>
                        <td colspan="4">Sin detalles registrados</td>
                    </tr>
                @endforelse
            @empty
                <tr>
                    <td colspan="9">No se encontraron órdenes para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">El monto contabilizado se calcula con el monto registrado en cada detalle de las órdenes completadas o completadas tarde.</p>
    <footer class="footer">GEOCOM · Reporte generado por el Portal Web</footer>
</body>
</html>
