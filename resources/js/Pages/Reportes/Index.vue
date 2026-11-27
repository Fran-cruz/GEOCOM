<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    filtros: {
        type: Object,
        default: () => ({}),
    },
    resumen: {
        type: Object,
        default: () => ({}),
    },
    porConductor: {
        type: Array,
        default: () => [],
    },
    porGasolinera: {
        type: Array,
        default: () => [],
    },
    por_combustible: {
        type: Array,
        default: () => [],
    },
    por_mes: {
        type: Array,
        default: () => [],
    },
    por_vehiculo: {
        type: Array,
        default: () => [],
    },
    top_conductores: {
        type: Array,
        default: () => [],
    },
    top_gasolineras: {
        type: Array,
        default: () => [],
    },
    export_pdf_url: {
        type: String,
        default: '/reportes/exportar-pdf',
    },
    ordenes: {
        type: Array,
        default: null,
    },
});

const page = usePage();
const filters = reactive({
    desde: props.filtros.desde || '',
    hasta: props.filtros.hasta || '',
    conductor_id: props.filtros.conductor_id || '',
    gasolinera_id: props.filtros.gasolinera_id || '',
    vehiculo_id: props.filtros.vehiculo_id || '',
    estado: props.filtros.estado || '',
});

const estados = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'abierta', label: 'Abierta' },
    { value: 'abierta_tarde', label: 'Abierta tarde' },
    { value: 'completada', label: 'Completada' },
    { value: 'completada_tarde', label: 'Completada tarde' },
    { value: 'vencida', label: 'Vencida' },
    { value: 'cancelada', label: 'Cancelada' },
];

const summaryCards = computed(() => [
    {
        label: 'Monto total',
        value: formatCurrency(props.resumen.monto_total),
        detail: `${Number(props.resumen.cantidad_ordenes || 0)} órdenes ejecutadas`,
        accent: 'bg-[#EEE7FA] text-[#6042A6]',
        icon: 'currency',
    },
    {
        label: 'Órdenes ejecutadas',
        value: numberFormat(props.resumen.ordenes_ejecutadas ?? props.resumen.cantidad_ordenes),
        detail: `${numberFormat(props.resumen.total)} órdenes en el período`,
        accent: 'bg-emerald-50 text-emerald-700',
        icon: 'orders',
    },
    {
        label: 'Galones totales',
        value: numberFormat(props.resumen.galones_total, 2),
        detail: `${numberFormat(props.resumen.litros_total, 2)} litros estimados`,
        accent: 'bg-blue-50 text-blue-700',
        icon: 'fuel',
    },
    {
        label: 'Kilómetros estimados',
        value: numberFormat(props.resumen.km_total, 2),
        detail: 'Distancia estimada recorrida',
        accent: 'bg-cyan-50 text-cyan-700',
        icon: 'distance',
    },
    {
        label: 'Precio promedio / galón',
        value: props.resumen.precio_promedio_galon === null
            ? '—'
            : formatCurrency(props.resumen.precio_promedio_galon),
        detail: 'Según combustible y fecha',
        accent: 'bg-amber-50 text-amber-700',
        icon: 'average',
    },
    {
        label: 'Cumplimiento a tiempo',
        value: `${numberFormat(props.resumen.cumplimiento_pct, 1)}%`,
        detail: `${numberFormat(props.resumen.cumplidas_a_tiempo)} a tiempo · ${numberFormat(props.resumen.completadas_tarde)} tarde · ${numberFormat(props.resumen.vencidas)} vencidas`,
        accent: 'bg-rose-50 text-rose-700',
        icon: 'compliance',
    },
]);

const fuelSummaries = computed(() => {
    const categories = [
        { key: 'regular', label: 'Regular', color: 'bg-amber-500' },
        { key: 'super', label: 'Súper', color: 'bg-violet-500' },
        { key: 'diesel', label: 'Diésel', color: 'bg-slate-700' },
    ];

    return categories.map((category) => {
        const row = props.por_combustible.find((item) => {
            const type = String(item.tipo_combustible || '').toLowerCase();
            if (category.key === 'regular') return type.includes('regular') || type.includes('gasolina');
            if (category.key === 'super') return type.includes('super') || type.includes('súper');
            return type.includes('diesel') || type.includes('diésel');
        });

        return { ...category, ...(row || {}) };
    });
});

const driverOptions = computed(() => {
    const source = props.top_conductores.length ? props.top_conductores : props.porConductor;
    return source.map((item) => ({
        id: item.id ?? item.conductor_id,
        name: item.nombre ?? item.nombre_conductor ?? 'Conductor',
    })).filter((item) => item.id);
});

const stationOptions = computed(() => {
    const source = props.top_gasolineras.length ? props.top_gasolineras : props.porGasolinera;
    return source.map((item) => ({
        id: item.id ?? item.gasolinera_id,
        name: item.nombre ?? item.nombre_gasolinera ?? 'Gasolinera',
    })).filter((item) => item.id);
});

const vehicleOptions = computed(() => props.por_vehiculo
    .map((item) => ({
        id: item.id,
        name: [item.placa, item.descripcion].filter(Boolean).join(' · ') || 'Vehículo',
    }))
    .filter((item) => item.id));

const exportPdfUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params.set(key, value);
    });
    const query = params.toString();
    const endpoint = props.export_pdf_url.split('?')[0] || '/reportes/exportar-pdf';

    return query ? `${endpoint}?${query}` : endpoint;
});

const requestFilters = () => {
    const params = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );

    router.get('/reportes', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let filterTimeout;
watch(filters, () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(requestFilters, 250);
});

onBeforeUnmount(() => clearTimeout(filterTimeout));

const clearFilters = () => {
    Object.keys(filters).forEach((key) => {
        filters[key] = '';
    });
};

function numberFormat(value, decimals = 0) {
    return new Intl.NumberFormat('es-HN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number(value) || 0);
}

function formatCurrency(value) {
    return `L ${numberFormat(value, 2)}`;
}

function rankWidth(value, rows) {
    const max = Math.max(...rows.map((row) => Number(row.monto_total) || 0), 0);
    return max ? `${Math.max((Number(value) / max) * 100, 4)}%` : '0%';
}

function fuelLabel(value) {
    const type = String(value || '').toLowerCase();
    if (type.includes('regular') || type.includes('gasolina')) return 'Regular';
    if (type.includes('super') || type.includes('súper')) return 'Súper';
    if (type.includes('diesel') || type.includes('diésel')) return 'Diésel';
    return value || 'No especificado';
}

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeZone: 'America/Tegucigalpa',
    }).format(date);
}

function orderDestination(order) {
    return (order.detalles || [])
        .map((detail) => detail.vehiculo?.placa || detail.descripcion_mgp || detail.tipo_destino)
        .filter(Boolean)
        .join(', ') || '—';
}
</script>

<template>
    <Head title="Reportes | GESTCOM" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex rounded-full bg-[#EEE7FA] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.15em] text-[#6042A6]">
                        Análisis institucional
                    </span>
                    <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Reportes operativos</h1>
                    <p class="mt-1.5 text-sm text-slate-500">Analiza consumo, cumplimiento y actividad de las órdenes.</p>
                </div>
                <a
                    :href="exportPdfUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#6042A6] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#342052] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/40 focus-visible:ring-offset-2"
                >
                    <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                        <path d="M10 3v9m0 0 3-3m-3 3L7 9m-3 5v2a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                    </svg>
                    Exportar PDF
                </a>
            </header>

            <section class="rounded-2xl border border-[#E5E0EB]/80 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold tracking-tight text-slate-900">Filtros analíticos</h2>
                        <p class="mt-1 text-xs text-slate-500">Los resultados y el PDF se actualizan con los mismos filtros.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#6042A6] transition hover:bg-[#EEE7FA]"
                        @click="clearFilters"
                    >
                        Limpiar filtros
                    </button>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <div>
                        <label for="reporte-desde" class="mb-1.5 block text-xs font-semibold text-slate-600">Desde</label>
                        <input
                            id="reporte-desde"
                            v-model="filters.desde"
                            type="date"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none transition focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/10"
                        >
                        <p v-if="page.props.errors?.desde" class="mt-1 text-[11px] text-rose-600">{{ page.props.errors.desde }}</p>
                    </div>
                    <div>
                        <label for="reporte-hasta" class="mb-1.5 block text-xs font-semibold text-slate-600">Hasta</label>
                        <input
                            id="reporte-hasta"
                            v-model="filters.hasta"
                            type="date"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none transition focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/10"
                        >
                        <p v-if="page.props.errors?.hasta" class="mt-1 text-[11px] text-rose-600">{{ page.props.errors.hasta }}</p>
                    </div>
                    <div>
                        <label for="reporte-conductor" class="mb-1.5 block text-xs font-semibold text-slate-600">Conductor</label>
                        <select
                            id="reporte-conductor"
                            v-model="filters.conductor_id"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none transition focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/10"
                        >
                            <option value="">Todos los conductores</option>
                            <option v-for="item in driverOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="reporte-gasolinera" class="mb-1.5 block text-xs font-semibold text-slate-600">Gasolinera</label>
                        <select
                            id="reporte-gasolinera"
                            v-model="filters.gasolinera_id"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none transition focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/10"
                        >
                            <option value="">Todas las gasolineras</option>
                            <option v-for="item in stationOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="reporte-vehiculo" class="mb-1.5 block text-xs font-semibold text-slate-600">Vehículo</label>
                        <select
                            id="reporte-vehiculo"
                            v-model="filters.vehiculo_id"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none transition focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/10"
                        >
                            <option value="">Todos los vehículos</option>
                            <option v-for="item in vehicleOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="reporte-estado" class="mb-1.5 block text-xs font-semibold text-slate-600">Estado</label>
                        <select
                            id="reporte-estado"
                            v-model="filters.estado"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none transition focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/10"
                        >
                            <option value="">Todos los estados</option>
                            <option v-for="estado in estados" :key="estado.value" :value="estado.value">{{ estado.label }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6" aria-label="Resumen ejecutivo">
                <article v-for="card in summaryCards" :key="card.label" class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-medium leading-5 text-slate-500">{{ card.label }}</p>
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" :class="card.accent">
                            <svg v-if="card.icon === 'currency'" viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M10 3v14m4-10c0-1.1-1.8-2-4-2S6 5.9 6 7s1.8 2 4 2 4 .9 4 2-1.8 2-4 2-4-.9-4-2" stroke="currentColor" stroke-linecap="round" stroke-width="1.4" />
                            </svg>
                            <svg v-else-if="card.icon === 'orders'" viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M5 3.5h10a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.4" />
                                <path d="M7 7h6M7 10h6m-6 3h3" stroke="currentColor" stroke-linecap="round" stroke-width="1.4" />
                            </svg>
                            <svg v-else-if="card.icon === 'fuel'" viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M4 16V5a1.5 1.5 0 0 1 1.5-1.5h5A1.5 1.5 0 0 1 12 5v11m-8 0h9m-7-9h3m5-1 2 2v5a1.5 1.5 0 0 1-3 0v-2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" />
                            </svg>
                            <svg v-else-if="card.icon === 'distance'" viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M4 15.5 15.5 4M5 5h4M15 15h-4" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                                <circle cx="4.5" cy="15.5" r="1.5" stroke="currentColor" stroke-width="1.3" />
                                <circle cx="15.5" cy="4.5" r="1.5" stroke="currentColor" stroke-width="1.3" />
                            </svg>
                            <svg v-else-if="card.icon === 'average'" viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M4 15.5h12M6 12l3-3 2 2 4-5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                            </svg>
                            <svg v-else viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M10 2.8 16 5v4.8c0 3.5-2.5 6.1-6 7.4-3.5-1.3-6-3.9-6-7.4V5l6-2.2Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.4" />
                                <path d="m7.2 9.8 1.8 1.8 3.8-4" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 truncate text-xl font-bold tracking-tight text-slate-900">{{ card.value }}</p>
                    <p class="mt-1.5 line-clamp-2 min-h-8 text-[10px] leading-4 text-slate-400">{{ card.detail }}</p>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#6042A6]">Desglose</p>
                        <h2 class="mt-1 text-base font-bold tracking-tight text-slate-900">Consumo por combustible</h2>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div v-for="fuel in fuelSummaries" :key="fuel.key" class="rounded-xl border border-slate-100 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="fuel.color" />
                                    {{ fuel.label }}
                                </span>
                                <span class="text-xs font-semibold text-slate-500">{{ numberFormat(fuel.cantidad_ordenes) }} órdenes</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                <div><p class="text-[10px] text-slate-400">Monto</p><p class="mt-1 font-semibold text-slate-800">{{ formatCurrency(fuel.monto_total) }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Galones</p><p class="mt-1 font-semibold text-slate-800">{{ numberFormat(fuel.galones_total, 2) }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Kilómetros</p><p class="mt-1 font-semibold text-slate-800">{{ numberFormat(fuel.km_total, 2) }}</p></div>
                                <div><p class="text-[10px] text-slate-400">Promedio/galón</p><p class="mt-1 font-semibold text-slate-800">{{ fuel.precio_promedio_galon == null ? '—' : formatCurrency(fuel.precio_promedio_galon) }}</p></div>
                            </div>
                        </div>
                        <p v-if="!por_combustible.length" class="py-3 text-center text-xs text-slate-400">No hay consumo registrado para los filtros seleccionados.</p>
                    </div>
                </article>

                <div class="grid gap-5">
                    <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#6042A6]">Ranking</p>
                                <h2 class="mt-1 text-base font-bold tracking-tight text-slate-900">Por conductor</h2>
                            </div>
                            <span class="text-[10px] text-slate-400">Ordenado por monto</span>
                        </div>
                        <div v-if="top_conductores.length" class="mt-4 space-y-3">
                            <div v-for="(item, index) in top_conductores.slice(0, 5)" :key="item.id" class="flex items-center gap-3">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[#EEE7FA] text-[10px] font-bold text-[#6042A6]">{{ index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between gap-2 text-xs">
                                        <span class="truncate font-medium text-slate-700">{{ item.nombre || 'Conductor' }}</span>
                                        <span class="shrink-0 font-semibold text-slate-800">{{ formatCurrency(item.monto_total) }}</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-[#6042A6]" :style="{ width: rankWidth(item.monto_total, top_conductores) }" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-4 py-4 text-center text-xs text-slate-400">Sin datos de conductores para este período.</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#6042A6]">Ranking</p>
                                <h2 class="mt-1 text-base font-bold tracking-tight text-slate-900">Por gasolinera</h2>
                            </div>
                            <span class="text-[10px] text-slate-400">Ordenado por monto</span>
                        </div>
                        <div v-if="top_gasolineras.length" class="mt-4 space-y-3">
                            <div v-for="(item, index) in top_gasolineras.slice(0, 5)" :key="item.id" class="flex items-center gap-3">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[#EEE7FA] text-[10px] font-bold text-[#6042A6]">{{ index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between gap-2 text-xs">
                                        <span class="truncate font-medium text-slate-700">{{ item.nombre || 'Gasolinera' }}</span>
                                        <span class="shrink-0 font-semibold text-slate-800">{{ formatCurrency(item.monto_total) }}</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-violet-400" :style="{ width: rankWidth(item.monto_total, top_gasolineras) }" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-4 py-4 text-center text-xs text-slate-400">Sin datos de gasolineras para este período.</p>
                    </article>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#6042A6]">Detalle</p>
                        <h2 class="mt-1 text-base font-bold tracking-tight text-slate-900">Órdenes del reporte</h2>
                        <p class="mt-1 text-xs text-slate-500">Órdenes que coinciden con los filtros aplicados.</p>
                    </div>
                    <a
                        :href="exportPdfUrl"
                        target="_blank"
                        rel="noopener"
                        class="rounded-lg border border-[#E5E0EB] px-3 py-2 text-xs font-semibold text-[#6042A6] transition hover:bg-[#EEE7FA]"
                    >
                        Descargar detalle PDF
                    </a>
                </div>

                <div v-if="ordenes === null" class="px-6 py-10 text-center">
                    <p class="text-sm font-semibold text-slate-700">El detalle de órdenes no está disponible en esta respuesta.</p>
                    <p class="mt-1 text-xs text-slate-500">El controlador actual envía los resúmenes; usa Exportar PDF para consultar las órdenes filtradas.</p>
                </div>
                <div v-else-if="ordenes.length === 0" class="px-6 py-10 text-center text-sm text-slate-500">
                    No hay órdenes para los filtros seleccionados.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Orden</th>
                                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Fecha</th>
                                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Conductor</th>
                                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Gasolinera</th>
                                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Vehículo / destino</th>
                                <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Estado</th>
                                <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="order in ordenes" :key="order.id" class="transition hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <Link :href="`/ordenes/${order.id}`" class="text-xs font-bold text-[#6042A6] hover:underline">
                                        {{ order.numero_orden || `Orden #${order.id}` }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-xs text-slate-500">{{ formatDate(order.fecha_asignacion) }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-xs text-slate-700">{{ order.conductor?.usuario?.name || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-xs text-slate-700">{{ order.gasolinera?.nombre || '—' }}</td>
                                <td class="max-w-56 truncate px-5 py-3.5 text-xs text-slate-600">{{ orderDestination(order) }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5"><StatusBadge :status="order.estado || 'pendiente'" /></td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right text-xs font-semibold text-slate-800">
                                    {{ formatCurrency((order.detalles || []).reduce((total, detail) => total + Number(detail.monto || 0), 0)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
