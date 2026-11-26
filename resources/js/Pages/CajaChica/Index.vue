<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    movimientos: {
        type: Object,
        required: true,
    },
    conductores: {
        type: Array,
        required: true,
    },
});

const today = new Date();
const todayString = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, '0'),
    String(today.getDate()).padStart(2, '0'),
].join('-');

const form = useForm({
    conductor_id: null,
    monto: '',
    descripcion: '',
    fecha_entrega: todayString,
});

const pageTotal = computed(() => (
    props.movimientos.data.reduce((total, movimiento) => total + Number(movimiento.monto || 0), 0)
));

const formatCurrency = (amount) => new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
}).format(Number(amount) || 0);

const formatDate = (date) => {
    if (!date) return '—';

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
};

const submitForm = () => {
    form.post('/caja-chica', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header>
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Administración</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Caja Chica</h1>
                <p class="mt-1 text-sm text-slate-600">Consulta y registra entregas de caja chica.</p>
            </header>

            <section
                v-if="$page.props.can_operate"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            >
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-slate-900">Registrar movimiento</h2>
                    <p class="mt-1 text-sm text-slate-500">Completa los datos de la entrega.</p>
                </div>

                <form @submit.prevent="submitForm" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="caja-conductor" class="mb-1.5 block text-sm font-medium text-slate-700">
                            Conductor <span class="font-normal text-slate-500">(opcional)</span>
                        </label>
                        <select
                            id="caja-conductor"
                            v-model="form.conductor_id"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                            <option :value="null">Sin conductor asociado</option>
                            <option v-for="conductor in conductores" :key="conductor.id" :value="conductor.id">
                                {{ conductor.usuario?.name || `Conductor #${conductor.id}` }}
                            </option>
                        </select>
                        <p v-if="form.errors.conductor_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.conductor_id }}
                        </p>
                    </div>

                    <div>
                        <label for="caja-monto" class="mb-1.5 block text-sm font-medium text-slate-700">Monto</label>
                        <input
                            id="caja-monto"
                            v-model="form.monto"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            inputmode="decimal"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.monto" class="mt-1 text-sm text-red-600">{{ form.errors.monto }}</p>
                    </div>

                    <div>
                        <label for="caja-fecha" class="mb-1.5 block text-sm font-medium text-slate-700">
                            Fecha de entrega
                        </label>
                        <input
                            id="caja-fecha"
                            v-model="form.fecha_entrega"
                            type="date"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.fecha_entrega" class="mt-1 text-sm text-red-600">
                            {{ form.errors.fecha_entrega }}
                        </p>
                    </div>

                    <div>
                        <label for="caja-descripcion" class="mb-1.5 block text-sm font-medium text-slate-700">
                            Descripción
                        </label>
                        <input
                            id="caja-descripcion"
                            v-model="form.descripcion"
                            type="text"
                            maxlength="255"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.descripcion" class="mt-1 text-sm text-red-600">
                            {{ form.errors.descripcion }}
                        </p>
                    </div>

                    <div class="flex justify-end sm:col-span-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Guardando...' : 'Registrar movimiento' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Movimientos registrados</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ movimientos.total }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-amber-800">Entregado en esta página</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ formatCurrency(pageTotal) }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Movimientos de caja chica</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        El total entregado corresponde a los movimientos visibles en esta página.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Fecha de entrega</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Conductor</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Descripción</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Registrado por</th>
                                <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="movimiento in movimientos.data" :key="movimiento.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                    {{ formatDate(movimiento.fecha_entrega) }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                                    {{ movimiento.conductor?.usuario?.name || 'Sin conductor' }}
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700">{{ movimiento.descripcion }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                    {{ movimiento.registrador?.name || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-slate-900">
                                    {{ formatCurrency(movimiento.monto) }}
                                </td>
                            </tr>
                            <tr v-if="movimientos.data.length === 0">
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                                    No hay movimientos de caja chica registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-3">
                    <Pagination :links="movimientos.links" />
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
