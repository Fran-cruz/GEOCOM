<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    solicitudesPendientes: {
        type: Number,
        default: 0,
    },
    ordenesPorEstado: {
        type: Object,
        default: () => ({}),
    },
    ordenesEjecutadas: {
        type: Number,
        default: 0,
    },
    gastoEjecutado: {
        type: Number,
        default: 0,
    },
    gastoCompletado: {
        type: Number,
        default: 0,
    },
    ultimasSolicitudes: {
        type: Array,
        default: () => [],
    },
    ultimasOrdenes: {
        type: Array,
        default: () => [],
    },
});

const estadoOrdenes = [
    { key: 'pendiente', label: 'Pendientes', bar: 'bg-amber-500', text: 'text-amber-700' },
    { key: 'abierta', label: 'Abiertas', bar: 'bg-blue-500', text: 'text-blue-700' },
    { key: 'abierta_tarde', label: 'Abiertas tarde', bar: 'bg-cyan-500', text: 'text-cyan-700' },
    { key: 'completada', label: 'Completadas', bar: 'bg-emerald-500', text: 'text-emerald-700' },
    { key: 'completada_tarde', label: 'Completadas tarde', bar: 'bg-emerald-600', text: 'text-emerald-700' },
    { key: 'vencida', label: 'Vencidas', bar: 'bg-rose-500', text: 'text-rose-700' },
    { key: 'cancelada', label: 'Canceladas', bar: 'bg-pink-500', text: 'text-pink-700' },
];

const ordenesTotales = computed(() => estadoOrdenes.reduce(
    (total, estado) => total + Number(props.ordenesPorEstado?.[estado.key] || 0),
    0,
));

const porcentajeEjecutado = computed(() => (
    ordenesTotales.value
        ? Math.round((Number(props.ordenesEjecutadas || 0) / ordenesTotales.value) * 100)
        : 0
));

const distribucionOrdenes = computed(() => estadoOrdenes.map((estado) => {
    const cantidad = Number(props.ordenesPorEstado?.[estado.key] || 0);

    return {
        ...estado,
        cantidad,
        porcentaje: ordenesTotales.value ? (cantidad / ordenesTotales.value) * 100 : 0,
    };
}));

const fechaActual = computed(() => new Intl.DateTimeFormat('es-HN', {
    dateStyle: 'full',
    timeZone: 'America/Tegucigalpa',
}).format(new Date()));

const formatCurrency = (amount) => `L ${new Intl.NumberFormat('es-HN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(amount) || 0)}`;

const formatDate = (value) => {
    if (!value) return '—';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeZone: 'America/Tegucigalpa',
    }).format(date);
};

const getSolicitudDestino = (solicitud) => (
    solicitud.vehiculo?.placa
    || solicitud.descripcion_actividad
    || solicitud.tipo_destino
    || 'Sin vehículo asignado'
);

const getOrdenDestino = (orden) => {
    const destinos = (orden.detalles || [])
        .map((detalle) => detalle.vehiculo?.placa || detalle.descripcion_mgp || detalle.tipo_destino)
        .filter(Boolean);

    return [...new Set(destinos)].slice(0, 2).join(' · ') || 'Sin detalle de destino';
};
</script>

<template>
    <Head title="Panel de Control | GESTCOM" />

    <AuthenticatedLayout>
        <div class="space-y-7">
            <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#EEE7FA] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.15em] text-[#6042A6]">
                        Resumen operativo
                    </span>
                    <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Panel de Control Operativo
                    </h1>
                    <p class="mt-2 text-sm capitalize text-slate-500">{{ fechaActual }}</p>
                </div>

                <div class="flex flex-wrap gap-2.5">
                    <Link
                        v-if="$page.props.auth?.can_operate"
                        href="/ordenes"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#6042A6] px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#342052] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/40 focus-visible:ring-offset-2"
                    >
                        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                            <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-linecap="round" stroke-width="1.7" />
                        </svg>
                        Nueva orden
                    </Link>
                    <Link
                        href="/solicitudes"
                        class="inline-flex items-center gap-2 rounded-xl border border-[#E5E0EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#6042A6] transition hover:border-[#6042A6]/30 hover:bg-[#EEE7FA]/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/30"
                    >
                        Ver solicitudes
                    </Link>
                    <Link
                        href="/reportes"
                        class="inline-flex items-center gap-2 rounded-xl border border-[#E5E0EB] bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-[#6042A6]/30 hover:bg-[#EEE7FA]/50 hover:text-[#6042A6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/30"
                    >
                        Reportes
                    </Link>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores principales">
                <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-slate-500">Gasto ejecutado</p>
                            <p class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ formatCurrency(gastoEjecutado) }}</p>
                        </div>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#EEE7FA] text-[#6042A6]">
                            <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M3 6.5h14v9H3v-9Zm0 0 2-3h10l2 3M7 10h6m-6 2.5h3" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-[11px] text-slate-400">Monto de órdenes completadas</p>
                </article>

                <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-slate-500">Órdenes ejecutadas</p>
                            <p class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ ordenesEjecutadas }}</p>
                        </div>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M10 2.8 16 5v4.8c0 3.5-2.5 6.1-6 7.4-3.5-1.3-6-3.9-6-7.4V5l6-2.2Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.4" />
                                <path d="m7.2 9.8 1.8 1.8 3.8-4" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3 text-[11px]">
                        <span class="text-slate-400">{{ ordenesEjecutadas }} de {{ ordenesTotales }} órdenes</span>
                        <span class="font-semibold text-emerald-700">{{ porcentajeEjecutado }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: `${porcentajeEjecutado}%` }" />
                    </div>
                </article>

                <Link
                    href="/solicitudes"
                    class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:border-amber-200 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-slate-500">Solicitudes pendientes</p>
                            <p class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ solicitudesPendientes }}</p>
                        </div>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-50 text-amber-600">
                            <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M5 3.5h10a1 1 0 0 1 1 1v12l-6-3-6 3v-12a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.4" />
                                <path d="M7.5 7h5m-5 3h5" stroke="currentColor" stroke-linecap="round" stroke-width="1.4" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-[11px] font-medium text-amber-700 group-hover:text-amber-800">Revisar solicitudes <span aria-hidden="true">→</span></p>
                </Link>

            </section>

            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <span class="inline-flex items-center rounded-full bg-[#EEE7FA] px-2.5 py-1 text-[9px] font-bold uppercase tracking-[0.14em] text-[#6042A6]">
                            Resumen operativo
                        </span>
                        <h2 class="mt-2 text-lg font-bold tracking-tight text-slate-900">Órdenes por estado</h2>
                    </div>
                    <p class="text-xs text-slate-500">{{ ordenesTotales }} órdenes en total</p>
                </div>

                <div class="mt-6 grid gap-x-10 gap-y-4 md:grid-cols-2">
                    <div v-for="estado in distribucionOrdenes" :key="estado.key" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="font-medium text-slate-600">{{ estado.label }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-semibold text-slate-800">{{ estado.cantidad }}</span>
                                <span class="w-10 text-right text-[10px] text-slate-400">{{ Math.round(estado.porcentaje) }}%</span>
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="estado.bar"
                                :style="{ width: `${estado.porcentaje}%` }"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 2xl:grid-cols-2">
                <article class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div>
                            <h2 class="text-base font-bold tracking-tight text-slate-900">Últimas solicitudes pendientes</h2>
                            <p class="mt-1 text-xs text-slate-500">Solicitudes que requieren seguimiento.</p>
                        </div>
                        <Link href="/solicitudes" class="shrink-0 rounded-lg px-2.5 py-2 text-xs font-semibold text-[#6042A6] transition hover:bg-[#EEE7FA]">
                            Ver todas
                        </Link>
                    </div>

                    <div v-if="ultimasSolicitudes.length" class="divide-y divide-slate-100">
                        <div
                            v-for="solicitud in ultimasSolicitudes"
                            :key="solicitud.id"
                            class="flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50/80 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">
                                    {{ solicitud.conductor?.usuario?.name || 'Conductor sin asignar' }}
                                </p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ getSolicitudDestino(solicitud) }}</p>
                            </div>
                            <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                                <span class="text-[11px] text-slate-400">{{ formatDate(solicitud.fecha || solicitud.created_at) }}</span>
                                <StatusBadge :status="solicitud.estado || 'pendiente'" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-6 py-10 text-center">
                        <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-[#EEE7FA] text-[#6042A6]">
                            <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="m5 10 3.2 3.2L15.5 6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-700">Todo al día</p>
                        <p class="mt-1 text-xs text-slate-500">No hay solicitudes pendientes por revisar.</p>
                    </div>
                </article>

                <article class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div>
                            <h2 class="text-base font-bold tracking-tight text-slate-900">Últimas órdenes asignadas</h2>
                            <p class="mt-1 text-xs text-slate-500">Actividad reciente de la operación.</p>
                        </div>
                        <Link href="/ordenes" class="shrink-0 rounded-lg px-2.5 py-2 text-xs font-semibold text-[#6042A6] transition hover:bg-[#EEE7FA]">
                            Ver todas
                        </Link>
                    </div>

                    <div v-if="ultimasOrdenes.length" class="divide-y divide-slate-100">
                        <Link
                            v-for="orden in ultimasOrdenes"
                            :key="orden.id"
                            :href="`/ordenes/${orden.id}`"
                            class="flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50/80 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-[#6042A6]">{{ orden.numero_orden || `Orden #${orden.id}` }}</span>
                                    <StatusBadge :status="orden.estado || 'pendiente'" />
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-600">
                                    {{ orden.conductor?.usuario?.name || 'Conductor sin asignar' }}
                                    <span class="px-1 text-slate-300">·</span>
                                    {{ orden.gasolinera?.nombre || 'Sin gasolinera' }}
                                </p>
                                <p class="mt-1 truncate text-[11px] text-slate-400">{{ getOrdenDestino(orden) }}</p>
                            </div>
                            <span class="shrink-0 text-[11px] text-slate-400">{{ formatDate(orden.fecha_asignacion || orden.created_at) }}</span>
                        </Link>
                    </div>
                    <div v-else class="px-6 py-10 text-center">
                        <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-[#EEE7FA] text-[#6042A6]">
                            <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M5 3.5h10a1 1 0 0 1 1 1v12l-6-3-6 3v-12a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-linejoin="round" stroke-width="1.4" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-700">Sin órdenes recientes</p>
                        <p class="mt-1 text-xs text-slate-500">Las órdenes asignadas aparecerán aquí.</p>
                    </div>
                </article>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
