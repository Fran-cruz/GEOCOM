<script setup>
import axios from 'axios';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    ordenes: {
        type: Object,
        required: true,
    },
    filtros: {
        type: Object,
        default: () => ({}),
    },
    conductores: {
        type: Array,
        default: () => [],
    },
    gasolineras: {
        type: Array,
        default: () => [],
    },
    vehiculos: {
        type: Array,
        default: () => [],
    },
    solicitudesPendientes: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const canOperate = computed(() => Boolean(page.props.auth?.can_operate ?? page.props.can_operate));
const filterValues = () => ({
    estado: props.filtros.estado || '',
    conductor_id: props.filtros.conductor_id || '',
    gasolinera_id: props.filtros.gasolinera_id || '',
    desde: props.filtros.desde || '',
    hasta: props.filtros.hasta || '',
    vehiculo_id: new URLSearchParams(page.url.split('?')[1] || '').get('vehiculo_id') || '',
});
const filters = reactive(filterValues());
const filterStates = [
    { value: '', label: 'Todos' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'abierta', label: 'Abierta' },
    { value: 'completada', label: 'Completada' },
    { value: 'vencida', label: 'Vencida' },
    { value: 'abierta_tarde', label: 'Abierta Tarde' },
    { value: 'completada_tarde', label: 'Completada Tarde' },
];

watch(() => props.filtros, () => Object.assign(filters, filterValues()), { deep: true });
watch(() => page.url, () => {
    filters.vehiculo_id = new URLSearchParams(page.url.split('?')[1] || '').get('vehiculo_id') || '';
});

const applyFilters = () => {
    router.get('/ordenes', {
        estado: filters.estado || undefined,
        conductor_id: filters.conductor_id || undefined,
        gasolinera_id: filters.gasolinera_id || undefined,
        desde: filters.desde || undefined,
        hasta: filters.hasta || undefined,
        vehiculo_id: filters.vehiculo_id || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

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

const conductorNombre = (orden) => orden.conductor?.usuario?.name || 'Sin conductor';
const ordenDestino = (orden) => (orden.detalles || [])
    .map((detalle) => detalle.vehiculo?.placa || detalle.descripcion_mgp || (detalle.tipo_destino === 'mgp' ? 'Planta MGP' : ''))
    .filter(Boolean)
    .join(' · ') || '—';
const montoAutorizado = (orden) => (orden.detalles || [])
    .reduce((total, detalle) => total + Number(detalle.monto || 0), 0);
const ordenesVisibles = computed(() => {
    if (!filters.vehiculo_id) return props.ordenes.data || [];

    return (props.ordenes.data || []).filter((orden) => (
        (orden.detalles || []).some((detalle) => String(detalle.vehiculo_id || '') === String(filters.vehiculo_id))
    ));
});

const formVisible = ref(false);
const requestedSolicitudId = new URLSearchParams(page.url.split('?')[1] || '').get('solicitud_id') || '';
const form = useForm({
    solicitud_id: requestedSolicitudId,
    gasolinera_id: '',
    horas_vigencia: 1,
    detalles: [],
});
const estimates = reactive([]);
const estimateTimers = new Map();
const estimateVersions = new Map();
let estimateVersionCounter = 0;

const clearEstimates = () => {
    estimateTimers.forEach((timer) => window.clearTimeout(timer));
    estimateTimers.clear();
    estimateVersions.clear();
    estimates.splice(0, estimates.length);
};
onBeforeUnmount(clearEstimates);

const solicitudSeleccionada = computed(() => props.solicitudesPendientes
    .find((solicitud) => String(solicitud.id) === String(form.solicitud_id)));

const getSolicitudDestinoTipos = (solicitud) => {
    if (!solicitud) return [];
    if (solicitud.tipo_destino === 'ambos') return ['vehiculo', 'mgp'];
    if (['vehiculo', 'mgp'].includes(solicitud.tipo_destino)) return [solicitud.tipo_destino];
    return solicitud.vehiculo_id ? ['vehiculo'] : ['mgp'];
};

const crearDetalle = (tipo, solicitud = solicitudSeleccionada.value) => ({
    tipo_destino: tipo,
    vehiculo_id: tipo === 'vehiculo' && solicitud?.vehiculo_id ? String(solicitud.vehiculo_id) : '',
    descripcion_mgp: tipo === 'mgp' ? (solicitud?.descripcion_mgp || '') : '',
    monto: '',
});

const cargarSolicitud = () => {
    clearEstimates();
    form.detalles = getSolicitudDestinoTipos(solicitudSeleccionada.value)
        .map((tipo) => crearDetalle(tipo));
    form.clearErrors('solicitud_id', 'detalles');
};

const openForm = () => {
    form.reset();
    form.clearErrors();
    form.solicitud_id = requestedSolicitudId
        && props.solicitudesPendientes.some((solicitud) => String(solicitud.id) === String(requestedSolicitudId))
        ? requestedSolicitudId
        : '';
    form.gasolinera_id = '';
    form.horas_vigencia = 1;
    cargarSolicitud();
    formVisible.value = true;
};

const closeForm = () => {
    formVisible.value = false;
    clearEstimates();
    form.reset();
    form.clearErrors();
};

const addDetalle = () => {
    const tipoDisponible = ['vehiculo', 'mgp'].find((tipo) => (
        !form.detalles.some((detalle) => detalle.tipo_destino === tipo)
    ));
    if (tipoDisponible && form.detalles.length < 2) {
        form.detalles.push(crearDetalle(tipoDisponible));
    }
};

const removeDetalle = (index) => {
    if (form.detalles.length > 1) {
        form.detalles.splice(index, 1);
        clearEstimates();
        form.detalles.forEach((detalle, detailIndex) => {
            if (detalle.monto) estimateLine(detailIndex);
        });
    }
};

const estimateLine = (index) => {
    const previousTimer = estimateTimers.get(index);
    if (previousTimer) window.clearTimeout(previousTimer);

    const version = ++estimateVersionCounter;
    estimateVersions.set(index, version);
    const detalle = form.detalles[index];
    const monto = Number(detalle?.monto);

    if (!detalle || !Number.isFinite(monto) || monto < 0.01) {
        estimates[index] = null;
        return;
    }

    if (detalle.tipo_destino === 'vehiculo' && !detalle.vehiculo_id) {
        estimates[index] = {
            data: null,
            loading: false,
            error: 'Selecciona un vehículo para calcular la estimación.',
        };
        return;
    }

    const lineSnapshot = detalle;
    const timer = window.setTimeout(async () => {
        estimates[index] = { data: null, loading: true, error: '' };
        const payload = {
            monto,
            ...(detalle.tipo_destino === 'vehiculo'
                ? { vehiculo_id: detalle.vehiculo_id }
                : { tipo_combustible: 'gasolina' }),
        };

        try {
            const response = await axios.post('/ordenes/estimar', payload);
            if (estimateVersions.get(index) !== version || form.detalles[index] !== lineSnapshot) return;
            estimates[index] = { data: response.data, loading: false, error: '' };
        } catch (error) {
            if (estimateVersions.get(index) !== version || form.detalles[index] !== lineSnapshot) return;
            estimates[index] = {
                data: null,
                loading: false,
                error: error.response?.data?.message || 'No se pudo calcular la estimación con los precios vigentes.',
            };
        }
    }, 300);
    estimateTimers.set(index, timer);
};

const submitForm = () => {
    form.post('/ordenes', {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

onMounted(() => {
    if (requestedSolicitudId && props.solicitudesPendientes.some(
        (solicitud) => String(solicitud.id) === String(requestedSolicitudId),
    )) {
        openForm();
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#6042A6]">Gestión de combustible</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#24212D] sm:text-3xl">Órdenes de Combustible</h1>
                    <p class="mt-1 text-sm text-[#706A7A]">Consulta y seguimiento de las órdenes emitidas.</p>
                </div>
                <button
                    v-if="canOperate"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#6042A6] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#342052] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/40 focus-visible:ring-offset-2"
                    @click="openForm"
                >
                    <span aria-hidden="true" class="text-lg leading-none">+</span>
                    Nueva Orden
                </button>
            </header>

            <section class="space-y-4 rounded-2xl border border-[#E5E0EB] bg-white p-4 shadow-sm sm:p-5" aria-label="Filtros de órdenes">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#706A7A]">Estado</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="state in filterStates"
                            :key="state.value || 'todos'"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/30"
                            :class="filters.estado === state.value
                                ? 'border-[#6042A6] bg-[#6042A6] text-white'
                                : 'border-[#E5E0EB] bg-white text-[#706A7A] hover:border-[#6042A6]/40 hover:bg-[#EEE7FA]/50'"
                            @click="filters.estado = state.value; applyFilters()"
                        >
                            {{ state.label }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Conductor
                        <select
                            v-model="filters.conductor_id"
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            @change="applyFilters"
                        >
                            <option value="">Todos los conductores</option>
                            <option v-for="conductor in conductores" :key="conductor.id" :value="String(conductor.id)">
                                {{ conductor.usuario?.name || `Conductor #${conductor.id}` }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Gasolinera
                        <select
                            v-model="filters.gasolinera_id"
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            @change="applyFilters"
                        >
                            <option value="">Todas las gasolineras</option>
                            <option v-for="gasolinera in gasolineras" :key="gasolinera.id" :value="String(gasolinera.id)">
                                {{ gasolinera.nombre }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Vehículo
                        <select
                            v-model="filters.vehiculo_id"
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            @change="applyFilters"
                        >
                            <option value="">Todos los vehículos</option>
                            <option v-for="vehiculo in vehiculos" :key="vehiculo.id" :value="String(vehiculo.id)">
                                {{ vehiculo.placa }} · {{ vehiculo.marca || '' }} {{ vehiculo.modelo || '' }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Desde
                        <input
                            v-model="filters.desde"
                            type="date"
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            @change="applyFilters"
                        >
                    </label>
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Hasta
                        <input
                            v-model="filters.hasta"
                            type="date"
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            @change="applyFilters"
                        >
                    </label>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[#E5E0EB] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#E5E0EB]">
                        <thead class="bg-[#FAF9FC]">
                            <tr>
                                <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">N.° de Orden</th>
                                <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Conductor</th>
                                <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Vehículo / MGP</th>
                                <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Gasolinera</th>
                                <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Monto autorizado</th>
                                <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Vencimiento</th>
                                <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Estado</th>
                                <th scope="col" class="px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0EDF3]">
                            <tr v-for="orden in ordenesVisibles" :key="orden.id" class="transition hover:bg-[#FAF9FC]">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#6042A6]">
                                    {{ orden.numero_orden || `Orden #${orden.id}` }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#24212D]">{{ conductorNombre(orden) }}</td>
                                <td class="max-w-56 px-5 py-4 text-sm text-[#706A7A]">{{ ordenDestino(orden) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#24212D]">{{ orden.gasolinera?.nombre || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-[#24212D]">{{ formatCurrency(montoAutorizado(orden)) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#706A7A]">{{ formatDate(orden.vence_asignacion || orden.vence_apertura) }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><StatusBadge :status="orden.estado || 'pendiente'" /></td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a
                                        :href="`/ordenes/${orden.id}`"
                                        class="rounded-lg px-3 py-2 text-xs font-semibold text-[#6042A6] transition hover:bg-[#EEE7FA]"
                                    >
                                        Ver Detalle
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="!ordenesVisibles.length">
                                <td colspan="8" class="px-5 py-12 text-center">
                                    <p class="text-sm font-semibold text-[#24212D]">No hay órdenes para mostrar</p>
                                    <p class="mt-1 text-xs text-[#706A7A]">Ajusta los filtros o vuelve a consultar más tarde.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-[#E5E0EB] px-5 py-3">
                    <Pagination :links="ordenes.links || []" />
                </div>
            </section>
        </section>

        <div
            v-if="formVisible"
            class="fixed inset-0 z-[60] flex items-end justify-center bg-[#24212D]/50 p-0 backdrop-blur-sm sm:items-center sm:p-5"
            @click.self="closeForm"
        >
            <section
                class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-t-2xl border border-[#E5E0EB] bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-7"
                role="dialog"
                aria-modal="true"
                aria-labelledby="nueva-orden-title"
            >
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#6042A6]">Asignación de combustible</p>
                        <h2 id="nueva-orden-title" class="mt-1 text-xl font-bold text-[#24212D]">Emitir nueva orden</h2>
                        <p class="mt-1 text-sm text-[#706A7A]">Selecciona una solicitud pendiente y asigna los montos autorizados.</p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-[#706A7A] hover:bg-[#FAF9FC]" @click="closeForm">
                        Cerrar
                    </button>
                </div>

                <form class="space-y-5" @submit.prevent="submitForm">
                    <div>
                        <label for="orden-solicitud" class="mb-1.5 block text-sm font-semibold text-[#24212D]">Solicitud pendiente</label>
                        <select
                            id="orden-solicitud"
                            v-model="form.solicitud_id"
                            required
                            class="w-full rounded-xl border border-[#E5E0EB] bg-white px-3.5 py-3 text-sm text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            @change="cargarSolicitud"
                        >
                            <option value="" disabled>Selecciona una solicitud</option>
                            <option v-for="solicitud in solicitudesPendientes" :key="solicitud.id" :value="String(solicitud.id)">
                                #{{ solicitud.id }} · {{ solicitud.conductor?.usuario?.name || 'Conductor sin asignar' }}
                                · {{ solicitud.vehiculo?.placa || solicitud.descripcion_mgp || solicitud.tipo_destino }}
                            </option>
                        </select>
                        <p v-if="form.errors.solicitud_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.solicitud_id }}</p>
                        <p v-if="!solicitudesPendientes.length" class="mt-1.5 text-xs text-amber-700">No hay solicitudes pendientes para emitir una orden.</p>
                    </div>

                    <div v-if="solicitudSeleccionada" class="grid gap-3 rounded-xl bg-[#FAF9FC] p-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#706A7A]">Conductor asignado</p>
                            <p class="mt-1 text-sm font-semibold text-[#24212D]">{{ solicitudSeleccionada.conductor?.usuario?.name || 'Sin conductor' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#706A7A]">Destino solicitado</p>
                            <p class="mt-1 text-sm font-semibold text-[#24212D]">{{ solicitudSeleccionada.vehiculo?.placa || solicitudSeleccionada.descripcion_mgp || solicitudSeleccionada.tipo_destino || '—' }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="orden-gasolinera" class="mb-1.5 block text-sm font-semibold text-[#24212D]">Gasolinera</label>
                            <select
                                id="orden-gasolinera"
                                v-model="form.gasolinera_id"
                                required
                                class="w-full rounded-xl border border-[#E5E0EB] bg-white px-3.5 py-3 text-sm text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            >
                                <option value="" disabled>Selecciona una gasolinera</option>
                                <option v-for="gasolinera in gasolineras" :key="gasolinera.id" :value="String(gasolinera.id)">{{ gasolinera.nombre }}</option>
                            </select>
                            <p v-if="form.errors.gasolinera_id" class="mt-1.5 text-xs text-red-600">{{ form.errors.gasolinera_id }}</p>
                        </div>
                        <div>
                            <label for="orden-vigencia" class="mb-1.5 block text-sm font-semibold text-[#24212D]">Vigencia</label>
                            <select
                                id="orden-vigencia"
                                v-model.number="form.horas_vigencia"
                                required
                                class="w-full rounded-xl border border-[#E5E0EB] bg-white px-3.5 py-3 text-sm text-[#24212D] outline-none focus:border-[#6042A6] focus:ring-2 focus:ring-[#6042A6]/15"
                            >
                                <option :value="1">1 hora</option>
                                <option :value="2">2 horas</option>
                                <option :value="3">3 horas</option>
                            </select>
                            <p v-if="form.errors.horas_vigencia" class="mt-1.5 text-xs text-red-600">{{ form.errors.horas_vigencia }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-[#24212D]">Líneas de combustible autorizadas</h3>
                            <button
                                v-if="form.detalles.length < 2 && form.detalles.length > 0"
                                type="button"
                                class="rounded-lg px-3 py-2 text-xs font-semibold text-[#6042A6] hover:bg-[#EEE7FA]"
                                @click="addDetalle"
                            >
                                + Agregar destino
                            </button>
                        </div>
                        <p v-if="form.errors.detalles" class="text-xs text-red-600">{{ form.errors.detalles }}</p>
                        <article v-for="(detalle, index) in form.detalles" :key="index" class="rounded-xl border border-[#E5E0EB] p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <p class="text-xs font-bold uppercase tracking-wide text-[#6042A6]">
                                    {{ detalle.tipo_destino === 'vehiculo' ? 'Vehículo · Diésel' : 'MGP · Gasolina' }}
                                </p>
                                <button
                                    v-if="form.detalles.length > 1"
                                    type="button"
                                    class="text-xs font-semibold text-red-600 hover:text-red-800"
                                    @click="removeDetalle(index)"
                                >
                                    Quitar
                                </button>
                            </div>
                            <div v-if="detalle.tipo_destino === 'vehiculo'" class="mb-4">
                                <label :for="`orden-vehiculo-${index}`" class="mb-1.5 block text-xs font-semibold text-[#706A7A]">Vehículo</label>
                                <select
                                    :id="`orden-vehiculo-${index}`"
                                    v-model="detalle.vehiculo_id"
                                    required
                                    class="w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm text-[#24212D] outline-none focus:border-[#6042A6]"
                                    @change="estimateLine(index)"
                                >
                                    <option value="" disabled>Selecciona un vehículo</option>
                                    <option v-for="vehiculo in vehiculos" :key="vehiculo.id" :value="String(vehiculo.id)">
                                        {{ vehiculo.placa }} · {{ vehiculo.marca || '' }} {{ vehiculo.modelo || '' }}
                                    </option>
                                </select>
                                <p v-if="form.errors[`detalles.${index}.vehiculo_id`]" class="mt-1 text-xs text-red-600">{{ form.errors[`detalles.${index}.vehiculo_id`] }}</p>
                            </div>
                            <div v-else class="mb-4">
                                <label :for="`orden-mgp-${index}`" class="mb-1.5 block text-xs font-semibold text-[#706A7A]">Descripción de MGP</label>
                                <input
                                    :id="`orden-mgp-${index}`"
                                    v-model="detalle.descripcion_mgp"
                                    type="text"
                                    required
                                    maxlength="150"
                                    class="w-full rounded-lg border border-[#E5E0EB] px-3 py-2.5 text-sm text-[#24212D] outline-none focus:border-[#6042A6]"
                                >
                                <p v-if="form.errors[`detalles.${index}.descripcion_mgp`]" class="mt-1 text-xs text-red-600">{{ form.errors[`detalles.${index}.descripcion_mgp`] }}</p>
                            </div>
                            <label :for="`orden-monto-${index}`" class="mb-1.5 block text-xs font-semibold text-[#706A7A]">Monto autorizado (L)</label>
                            <input
                                :id="`orden-monto-${index}`"
                                v-model="detalle.monto"
                                type="number"
                                min="0.01"
                                max="99999999.99"
                                step="0.01"
                                required
                                placeholder="0.00"
                                class="w-full rounded-lg border border-[#E5E0EB] px-3 py-2.5 text-sm text-[#24212D] outline-none focus:border-[#6042A6]"
                                @input="estimateLine(index)"
                            >
                            <p v-if="form.errors[`detalles.${index}.monto`]" class="mt-1 text-xs text-red-600">{{ form.errors[`detalles.${index}.monto`] }}</p>
                            <div v-if="estimates[index]?.loading" class="mt-3 rounded-lg bg-[#FAF9FC] px-3 py-2 text-xs text-[#706A7A]">
                                Calculando al precio vigente…
                            </div>
                            <div v-else-if="estimates[index]?.error" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700" role="alert">
                                {{ estimates[index].error }}
                            </div>
                            <div v-else-if="estimates[index]?.data" class="mt-3 rounded-xl bg-[#EEE7FA]/60 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-[#6042A6]">Estimación al precio vigente</p>
                                <dl class="mt-2 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                    <div>
                                        <dt class="text-[#706A7A]">Precio / galón</dt>
                                        <dd class="mt-0.5 font-bold text-[#24212D]">{{ formatCurrency(estimates[index].data.precio_por_galon) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#706A7A]">Galones aprox.</dt>
                                        <dd class="mt-0.5 font-bold text-[#24212D]">{{ estimates[index].data.galones_aproximados ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#706A7A]">Litros aprox.</dt>
                                        <dd class="mt-0.5 font-bold text-[#24212D]">{{ estimates[index].data.litros_aproximados ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#706A7A]">Kilómetros</dt>
                                        <dd class="mt-0.5 font-bold text-[#24212D]">{{ estimates[index].data.kilometros_aproximados ?? '—' }}</dd>
                                    </div>
                                </dl>
                                <p class="mt-2 text-[10px] text-[#706A7A]">
                                    {{ estimates[index].data.combustible }} · {{ estimates[index].data.ciudad }}
                                    <span v-if="estimates[index].data.vigente_desde">· vigente desde {{ estimates[index].data.vigente_desde }}</span>
                                </p>
                            </div>
                        </article>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-[#E5E0EB] pt-5 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-xl border border-[#E5E0EB] px-4 py-2.5 text-sm font-semibold text-[#706A7A] hover:bg-[#FAF9FC]" @click="closeForm">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || !canOperate || !solicitudesPendientes.length"
                            class="rounded-xl bg-[#6042A6] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#342052] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ form.processing ? 'Emitiendo...' : 'Emitir Orden' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
