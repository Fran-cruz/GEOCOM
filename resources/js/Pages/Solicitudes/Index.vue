<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    solicitudes: {
        type: Object,
        required: true,
    },
    filtros: {
        type: Object,
        default: () => ({}),
    },
    gasolineras: {
        type: Array,
        default: () => [],
    },
    vehiculos: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const canOperate = computed(() => Boolean(page.props.auth?.can_operate ?? page.props.can_operate));
const queryParams = () => new URLSearchParams(page.url.split('?')[1] || '');
const filters = reactive({
    estado: props.filtros.estado || '',
    conductor_id: queryParams().get('conductor_id') || '',
    tipo_destino: queryParams().get('tipo_destino') || '',
    actividad: queryParams().get('actividad') || '',
});
const statusOptions = [
    { value: '', label: 'Todas' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'aprobada', label: 'Aprobada' },
    { value: 'rechazada', label: 'Rechazada' },
];
const destinationOptions = [
    { value: '', label: 'Todos los destinos' },
    { value: 'vehiculo', label: 'Vehículo' },
    { value: 'mgp', label: 'MGP' },
    { value: 'ambos', label: 'Ambos' },
];
const conductores = computed(() => {
    const unicos = new Map();
    (props.solicitudes.data || []).forEach((solicitud) => {
        const conductor = solicitud.conductor;
        if (conductor && !unicos.has(String(conductor.id))) {
            unicos.set(String(conductor.id), {
                id: String(conductor.id),
                nombre: conductor.usuario?.name || `Conductor #${conductor.id}`,
            });
        }
    });

    return [...unicos.values()];
});
const solicitudesVisibles = computed(() => (props.solicitudes.data || []).filter((solicitud) => {
    const conductorId = String(solicitud.conductor_id || solicitud.conductor?.id || '');
    const actividad = `${solicitud.descripcion_actividad || ''} ${solicitud.descripcion_mgp || ''}`.toLocaleLowerCase('es');

    return (!filters.conductor_id || conductorId === filters.conductor_id)
        && (!filters.tipo_destino || solicitud.tipo_destino === filters.tipo_destino)
        && (!filters.actividad || actividad.includes(filters.actividad.trim().toLocaleLowerCase('es')));
}));

watch(() => props.filtros.estado, (estado) => {
    filters.estado = estado || '';
});
watch(() => page.url, () => {
    const params = queryParams();
    filters.conductor_id = params.get('conductor_id') || '';
    filters.tipo_destino = params.get('tipo_destino') || '';
    filters.actividad = params.get('actividad') || '';
});

const applyFilters = () => {
    router.get('/solicitudes', {
        estado: filters.estado || undefined,
        conductor_id: filters.conductor_id || undefined,
        tipo_destino: filters.tipo_destino || undefined,
        actividad: filters.actividad || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let activityFilterTimer;
const scheduleActivityFilter = () => {
    window.clearTimeout(activityFilterTimer);
    activityFilterTimer = window.setTimeout(applyFilters, 300);
};
onBeforeUnmount(() => window.clearTimeout(activityFilterTimer));

const rejectForm = useForm({});
const rejectSolicitud = (solicitud) => {
    if (solicitud.estado !== 'pendiente') return;
    if (!window.confirm(`¿Confirmas rechazar la solicitud #${solicitud.id}?`)) return;

    rejectForm.post(`/solicitudes/${solicitud.id}/rechazar`, {
        preserveScroll: true,
    });
};

const destinoSolicitud = (solicitud) => {
    const destinos = [];
    if (solicitud.vehiculo?.placa) destinos.push(solicitud.vehiculo.placa);
    if (solicitud.descripcion_mgp) destinos.push(solicitud.descripcion_mgp);
    if (!destinos.length && solicitud.tipo_destino) destinos.push(solicitud.tipo_destino);

    return destinos.join(' · ') || 'Sin destino especificado';
};

const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeZone: 'America/Tegucigalpa',
    }).format(date);
};
</script>

<template>
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#6042A6]">Gestión de solicitudes</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#24212D] sm:text-3xl">Solicitudes de Combustible</h1>
                    <p class="mt-1 text-sm text-[#706A7A]">Revisa los destinos y da seguimiento a cada solicitud.</p>
                </div>
                <p class="rounded-xl bg-[#EEE7FA] px-4 py-2.5 text-xs font-semibold text-[#6042A6]">
                    {{ solicitudes.total ?? solicitudes.data?.length ?? 0 }} solicitudes
                </p>
            </header>

            <section class="space-y-4 rounded-2xl border border-[#E5E0EB] bg-white p-4 shadow-sm sm:p-5" aria-label="Filtros de solicitudes">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#706A7A]">Estado</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="estado in statusOptions"
                            :key="estado.value || 'todas'"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                            :class="filters.estado === estado.value
                                ? 'border-[#6042A6] bg-[#6042A6] text-white'
                                : 'border-[#E5E0EB] text-[#706A7A] hover:bg-[#EEE7FA]/50'"
                            @click="filters.estado = estado.value; applyFilters()"
                        >
                            {{ estado.label }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Conductor
                        <select
                            v-model="filters.conductor_id"
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6]"
                            @change="applyFilters"
                        >
                            <option value="">Todos los conductores</option>
                            <option v-for="conductor in conductores" :key="conductor.id" :value="conductor.id">{{ conductor.nombre }}</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Destino
                        <select
                            v-model="filters.tipo_destino"
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6]"
                            @change="applyFilters"
                        >
                            <option v-for="destino in destinationOptions" :key="destino.value || 'todos'" :value="destino.value">{{ destino.label }}</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-[#706A7A]">
                        Descripción de actividad
                        <input
                            v-model="filters.actividad"
                            type="search"
                            placeholder="Buscar descripción..."
                            class="mt-1.5 w-full rounded-lg border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm font-normal text-[#24212D] outline-none focus:border-[#6042A6]"
                            @input="scheduleActivityFilter"
                        >
                    </label>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[#E5E0EB] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#E5E0EB]">
                        <thead class="bg-[#FAF9FC]">
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Solicitud</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Conductor</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Destino</th>
                                <th class="min-w-52 px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Descripción de actividad</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Estado</th>
                                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Fecha</th>
                                <th v-if="canOperate" class="whitespace-nowrap px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wide text-[#706A7A]">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0EDF3]">
                            <tr v-for="solicitud in solicitudesVisibles" :key="solicitud.id" class="transition hover:bg-[#FAF9FC]">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#6042A6]">Solicitud #{{ solicitud.id }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#24212D]">{{ solicitud.conductor?.usuario?.name || 'Sin conductor' }}</td>
                                <td class="px-5 py-4">
                                    <p class="text-sm font-medium text-[#24212D]">{{ destinoSolicitud(solicitud) }}</p>
                                    <p class="mt-1 text-[10px] capitalize text-[#706A7A]">{{ solicitud.tipo_destino || '—' }}</p>
                                </td>
                                <td class="max-w-sm px-5 py-4 text-sm text-[#706A7A]">{{ solicitud.descripcion_actividad || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><StatusBadge :status="solicitud.estado || 'pendiente'" /></td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-[#706A7A]">{{ formatDate(solicitud.created_at || solicitud.fecha) }}</td>
                                <td v-if="canOperate" class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            v-if="solicitud.estado === 'pendiente'"
                                            :href="`/ordenes?solicitud_id=${solicitud.id}`"
                                            class="rounded-lg bg-[#6042A6] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#342052]"
                                        >
                                            Aprobar y Asignar Orden
                                        </Link>
                                        <button
                                            v-if="solicitud.estado === 'pendiente'"
                                            type="button"
                                            :disabled="rejectForm.processing"
                                            class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:opacity-50"
                                            @click="rejectSolicitud(solicitud)"
                                        >
                                            Rechazar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!solicitudesVisibles.length">
                                <td :colspan="canOperate ? 7 : 6" class="px-5 py-12 text-center text-sm text-[#706A7A]">
                                    No hay solicitudes que coincidan con los filtros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-[#E5E0EB] px-5 py-3">
                    <Pagination :links="solicitudes.links || []" />
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
