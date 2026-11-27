<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    orden: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const canOperate = computed(() => Boolean(page.props.auth?.can_operate ?? page.props.can_operate));
const enlargedEvidence = ref(null);
const rehabilitationModalOpen = ref(false);
const rehabilitationForm = useForm({
    horas_vigencia: 1,
    password: '',
});

const formatCurrency = (amount) => `L ${new Intl.NumberFormat('es-HN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(amount) || 0)}`;

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/Tegucigalpa',
    }).format(date);
};

const formatDate = (value) => {
    if (!value) return '—';
    const dateOnly = typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
    const date = new Date(dateOnly ? `${value}T12:00:00Z` : value);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeZone: 'America/Tegucigalpa',
    }).format(date);
};

const totalAutorizado = (props.orden.detalles || [])
    .reduce((total, detalle) => total + Number(detalle.monto || 0), 0);

const destinoLinea = (detalle) => detalle.vehiculo?.placa
    ? `${detalle.vehiculo.placa}${detalle.vehiculo.marca ? ` · ${detalle.vehiculo.marca}` : ''}${detalle.vehiculo.modelo ? ` ${detalle.vehiculo.modelo}` : ''}`
    : detalle.descripcion_mgp || (detalle.tipo_destino === 'mgp' ? 'Planta MGP' : 'Vehículo');

const evidenciaLabel = (tipo) => ({
    bomba: 'Bomba',
    factura: 'Factura',
    tablero: 'Tablero',
    tanque: 'Tanque',
}[tipo] || tipo || 'Evidencia');

const montoDespachado = (detalle) => detalle.monto_despachado
    ?? detalle.monto_despachado_total
    ?? detalle.despacho?.monto;

const galonesDespachados = (detalle) => detalle.galones_despachados
    ?? detalle.galones
    ?? detalle.galones_aproximados
    ?? detalle.despacho?.galones;

const precioPorGalon = (detalle) => detalle.precio_por_galon
    ?? detalle.precio_galon
    ?? detalle.despacho?.precio_por_galon;

const openRehabilitationModal = () => {
    rehabilitationForm.reset();
    rehabilitationForm.clearErrors();
    rehabilitationForm.horas_vigencia = 1;
    rehabilitationModalOpen.value = true;
};

const closeRehabilitationModal = () => {
    rehabilitationModalOpen.value = false;
    rehabilitationForm.reset();
    rehabilitationForm.clearErrors();
};

const rehabilitarOrden = () => {
    rehabilitationForm.post(`/ordenes/${props.orden.id}/rehabilitar`, {
        preserveScroll: true,
        onSuccess: closeRehabilitationModal,
    });
};

const combustibleLabel = (detalle) => ({
    regular: 'Regular',
    super: 'Súper',
    diesel: 'Diésel',
    gasolina: 'Gasolina',
}[detalle.combustible_precio || detalle.tipo_combustible] || detalle.combustible_precio || detalle.tipo_combustible || 'Combustible');

const formatNumber = (value) => value == null
    ? '—'
    : new Intl.NumberFormat('es-HN', { maximumFractionDigits: 2 }).format(Number(value));
</script>

<template>
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <a href="/ordenes" class="inline-flex items-center gap-2 text-xs font-semibold text-[#6042A6] hover:text-[#342052]">
                        <span aria-hidden="true">←</span> Volver a órdenes
                    </a>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#24212D]">Detalle de orden</h1>
                </div>
                <a href="/ordenes" class="rounded-xl border border-[#E5E0EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#706A7A] transition hover:bg-[#FAF9FC]">
                    Todas las órdenes
                </a>
                <button
                    v-if="orden.estado === 'vencida' && canOperate"
                    type="button"
                    class="rounded-xl bg-[#6042A6] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-[#342052]"
                    @click="openRehabilitationModal"
                >
                    Rehabilitar Orden
                </button>
            </header>

            <section class="overflow-hidden rounded-2xl bg-[#6042A6] text-white shadow-[0_16px_40px_rgba(52,32,82,0.2)]">
                <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1.2fr_1fr] lg:items-end">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-white/75">Orden de combustible</span>
                            <StatusBadge :status="orden.estado || 'pendiente'" />
                        </div>
                        <h2 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">{{ orden.numero_orden || `Orden #${orden.id}` }}</h2>
                        <p class="mt-2 text-sm text-white/70">Emitida para {{ orden.conductor?.usuario?.name || 'conductor sin asignar' }}</p>
                    </div>
                    <div class="lg:text-right">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/65">Monto total autorizado</p>
                        <p class="mt-1 text-3xl font-bold tracking-tight sm:text-4xl">{{ formatCurrency(totalAutorizado) }}</p>
                    </div>
                </div>
                <div class="grid gap-4 border-t border-white/15 bg-[#342052]/35 px-6 py-4 text-sm sm:grid-cols-2 sm:px-8">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-white/55">Fecha y hora de emisión</p>
                        <p class="mt-1 font-medium">{{ formatDateTime(orden.fecha_asignacion || orden.created_at) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-white/55">Fecha y hora de vencimiento</p>
                        <p class="mt-1 font-medium">{{ formatDateTime(orden.vence_asignacion || orden.vence_apertura) }}</p>
                    </div>
                </div>
            </section>

            <section v-if="orden.justificacion" class="rounded-2xl border border-amber-200 bg-[#FDF1DD] p-5 sm:p-6">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-amber-800">Justificación de tardanza</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#24212D]">{{ orden.justificacion }}</p>
            </section>

            <section class="grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
                <article class="rounded-2xl border border-[#E5E0EB] bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#6042A6]">Información operativa</p>
                        <h2 class="mt-1 text-lg font-bold text-[#24212D]">Datos de la asignación</h2>
                    </div>
                    <dl class="space-y-4">
                        <div class="border-b border-[#F0EDF3] pb-3">
                            <dt class="text-[11px] font-semibold text-[#706A7A]">Conductor asignado</dt>
                            <dd class="mt-1 text-sm font-semibold text-[#24212D]">{{ orden.conductor?.usuario?.name || 'Sin conductor asignado' }}</dd>
                        </div>
                        <div class="border-b border-[#F0EDF3] pb-3">
                            <dt class="text-[11px] font-semibold text-[#706A7A]">Vehículo / Planta MGP</dt>
                            <dd class="mt-1 space-y-1 text-sm font-semibold text-[#24212D]">
                                <p v-for="(detalle, index) in orden.detalles || []" :key="`${detalle.id}-destino-${index}`">
                                    {{ destinoLinea(detalle) }}
                                    <span class="font-normal text-[#706A7A]">· {{ detalle.tipo_destino === 'mgp' ? 'MGP' : 'Vehículo' }}</span>
                                </p>
                                <p v-if="!orden.detalles?.length">Sin destino registrado</p>
                            </dd>
                        </div>
                        <div class="border-b border-[#F0EDF3] pb-3">
                            <dt class="text-[11px] font-semibold text-[#706A7A]">Gasolinera afiliada</dt>
                            <dd class="mt-1 text-sm font-semibold text-[#24212D]">{{ orden.gasolinera?.nombre || 'Sin gasolinera' }}</dd>
                            <p v-if="orden.gasolinera?.direccion" class="mt-1 text-xs text-[#706A7A]">{{ orden.gasolinera.direccion }}</p>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold text-[#706A7A]">Solicitud vinculada</dt>
                            <dd v-if="orden.solicitud" class="mt-1 text-sm font-semibold text-[#24212D]">
                                Solicitud #{{ orden.solicitud.id }}
                                <span v-if="orden.solicitud.tipo_destino" class="font-normal capitalize text-[#706A7A]">· {{ orden.solicitud.tipo_destino }}</span>
                            </dd>
                            <dd v-else class="mt-1 text-sm text-[#706A7A]">No hay una solicitud vinculada.</dd>
                        </div>
                    </dl>
                </article>

                <article class="rounded-2xl border border-[#E5E0EB] bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#6042A6]">Combustible</p>
                        <h2 class="mt-1 text-lg font-bold text-[#24212D]">Líneas autorizadas y despachadas</h2>
                    </div>
                    <div v-if="orden.detalles?.length" class="space-y-4">
                        <article v-for="(detalle, index) in orden.detalles" :key="detalle.id || index" class="rounded-xl border border-[#E5E0EB] bg-[#FAF9FC]/70 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-[#24212D]">{{ combustibleLabel(detalle) }}</p>
                                    <p class="mt-1 text-xs text-[#706A7A]">{{ destinoLinea(detalle) }}</p>
                                </div>
                                <span class="rounded-full bg-[#EEE7FA] px-2.5 py-1 text-[10px] font-semibold capitalize text-[#6042A6]">
                                    {{ detalle.tipo_destino === 'mgp' ? 'MGP' : 'Vehículo' }}
                                </span>
                            </div>
                            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Monto autorizado</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ formatCurrency(detalle.monto) }}</dd>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Combustible</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ combustibleLabel(detalle) }}</dd>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Monto despachado</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">
                                        {{ montoDespachado(detalle) != null ? formatCurrency(montoDespachado(detalle)) : 'No disponible' }}
                                    </dd>
                                </div>
                                <div v-if="galonesDespachados(detalle) != null" class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Galones</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ Number(galonesDespachados(detalle)).toLocaleString('es-HN') }} gal</dd>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Precio por galón</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ precioPorGalon(detalle) == null ? '—' : formatCurrency(precioPorGalon(detalle)) }}</dd>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Precio vigente desde</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ formatDate(detalle.precio_vigente_desde) }}</dd>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Galones estimados</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ formatNumber(detalle.galones_estimados) }} gal</dd>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Litros estimados</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ formatNumber(detalle.litros_estimados) }} L</dd>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#706A7A]">Kilómetros estimados</dt>
                                    <dd class="mt-1 text-sm font-bold text-[#24212D]">{{ formatNumber(detalle.km_estimados) }} km</dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                    <p v-else class="rounded-xl bg-[#FAF9FC] px-4 py-8 text-center text-sm text-[#706A7A]">Esta orden no tiene líneas de combustible registradas.</p>
                </article>
            </section>

            <section class="rounded-2xl border border-[#E5E0EB] bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#6042A6]">Registro visual</p>
                    <h2 class="mt-1 text-lg font-bold text-[#24212D]">Evidencias Fotográficas</h2>
                    <p class="mt-1 text-xs text-[#706A7A]">Fotografías de bomba, factura, tablero y tanque asociadas a cada línea.</p>
                </div>
                <div v-if="(orden.detalles || []).some((detalle) => detalle.evidencias?.length)" class="space-y-5">
                    <div v-for="(detalle, index) in orden.detalles" :key="`evidencias-${detalle.id || index}`">
                        <h3 v-if="detalle.evidencias?.length" class="mb-3 text-xs font-semibold text-[#706A7A]">
                            {{ detalle.tipo_combustible === 'diesel' ? 'Diésel' : 'Gasolina' }} · {{ destinoLinea(detalle) }}
                        </h3>
                        <div v-if="detalle.evidencias?.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <button
                                v-for="evidencia in detalle.evidencias"
                                :key="evidencia.id"
                                type="button"
                                class="group overflow-hidden rounded-xl border border-[#E5E0EB] bg-[#FAF9FC] text-left transition hover:border-[#6042A6]/50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/40"
                                :aria-label="`Ampliar evidencia: ${evidenciaLabel(evidencia.tipo)}`"
                                @click="enlargedEvidence = evidencia"
                            >
                                <img
                                    :src="`/evidencias/${evidencia.id}`"
                                    :alt="`Evidencia fotográfica: ${evidenciaLabel(evidencia.tipo)}`"
                                    class="aspect-square w-full object-cover transition duration-200 group-hover:scale-[1.02]"
                                    loading="lazy"
                                >
                                <span class="block px-3 py-2 text-xs font-semibold capitalize text-[#24212D]">{{ evidenciaLabel(evidencia.tipo) }}</span>
                            </button>
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-xl bg-[#FAF9FC] px-4 py-8 text-center text-sm text-[#706A7A]">No hay evidencias fotográficas disponibles para esta orden.</p>
            </section>
        </section>

        <div
            v-if="enlargedEvidence"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-[#17131F]/90 p-4 sm:p-8"
            role="dialog"
            aria-modal="true"
            :aria-label="`Evidencia ampliada: ${evidenciaLabel(enlargedEvidence.tipo)}`"
            @click.self="enlargedEvidence = null"
            @keydown.esc="enlargedEvidence = null"
        >
            <button
                type="button"
                class="absolute right-4 top-4 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20"
                aria-label="Cerrar imagen ampliada"
                @click="enlargedEvidence = null"
            >
                Cerrar
            </button>
            <img
                :src="`/evidencias/${enlargedEvidence.id}`"
                :alt="`Evidencia fotográfica: ${evidenciaLabel(enlargedEvidence.tipo)}`"
                class="max-h-full max-w-full rounded-xl object-contain shadow-2xl"
                @click.stop
            >
        </div>

        <div
            v-if="rehabilitationModalOpen"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-[#24212D]/55 p-4 backdrop-blur-sm"
            @click.self="closeRehabilitationModal"
        >
            <section
                class="w-full max-w-md rounded-2xl border border-[#E5E0EB] bg-white p-6 shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="rehabilitar-orden-title"
            >
                <h2 id="rehabilitar-orden-title" class="text-lg font-bold text-[#24212D]">Rehabilitar orden vencida</h2>
                <p class="mt-1 text-sm text-[#706A7A]">La orden volverá a estar disponible por el periodo elegido.</p>
                <form class="mt-5 space-y-4" @submit.prevent="rehabilitarOrden">
                    <div>
                        <label for="rehabilitar-horas" class="mb-1.5 block text-sm font-semibold text-[#24212D]">Nueva vigencia</label>
                        <select
                            id="rehabilitar-horas"
                            v-model.number="rehabilitationForm.horas_vigencia"
                            required
                            class="w-full rounded-xl border border-[#E5E0EB] bg-white px-3 py-2.5 text-sm text-[#24212D] outline-none focus:border-[#6042A6]"
                        >
                            <option :value="1">1 hora</option>
                            <option :value="2">2 horas</option>
                            <option :value="3">3 horas</option>
                        </select>
                        <p v-if="rehabilitationForm.errors.horas_vigencia" class="mt-1 text-xs text-rose-700">{{ rehabilitationForm.errors.horas_vigencia }}</p>
                    </div>
                    <div>
                        <label for="rehabilitar-password" class="mb-1.5 block text-sm font-semibold text-[#24212D]">Contraseña del administrador</label>
                        <input
                            id="rehabilitar-password"
                            v-model="rehabilitationForm.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-xl border border-[#E5E0EB] px-3 py-2.5 text-sm text-[#24212D] outline-none focus:border-[#6042A6]"
                        >
                        <p v-if="rehabilitationForm.errors.password" class="mt-1 text-xs text-rose-700">{{ rehabilitationForm.errors.password }}</p>
                    </div>
                    <div class="flex flex-col-reverse gap-3 border-t border-[#E5E0EB] pt-4 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-xl border border-[#E5E0EB] px-4 py-2.5 text-sm font-semibold text-[#706A7A] hover:bg-[#FAF9FC]" @click="closeRehabilitationModal">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="rehabilitationForm.processing || !canOperate"
                            class="rounded-xl bg-[#6042A6] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#342052] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ rehabilitationForm.processing ? 'Rehabilitando…' : 'Confirmar rehabilitación' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
