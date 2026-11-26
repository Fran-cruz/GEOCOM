<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    vehiculos: {
        type: Object,
        required: true,
    },
});

const formVisible = ref(false);
const editingVehiculo = ref(null);
const form = useForm({
    placa: '',
    marca: '',
    modelo: '',
    anio: '',
    tipo_combustible: 'diesel',
    rendimiento_km_litro: '8.00',
});

const closeForm = () => {
    formVisible.value = false;
    editingVehiculo.value = null;
    form.reset();
    form.clearErrors();
};

const createVehiculo = () => {
    editingVehiculo.value = null;
    form.reset();
    form.clearErrors();
    form.tipo_combustible = 'diesel';
    form.rendimiento_km_litro = '8.00';
    formVisible.value = true;
};

const editVehiculo = (vehiculo) => {
    editingVehiculo.value = vehiculo;
    form.clearErrors();
    form.placa = vehiculo.placa || '';
    form.marca = vehiculo.marca || '';
    form.modelo = vehiculo.modelo || '';
    form.anio = vehiculo.anio || '';
    form.tipo_combustible = vehiculo.tipo_combustible || 'diesel';
    form.rendimiento_km_litro = vehiculo.rendimiento_km_litro || '8.00';
    formVisible.value = true;
};

const submitForm = () => {
    if (editingVehiculo.value) {
        form.put(`/vehiculos/${editingVehiculo.value.id}`, {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post('/vehiculos', {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

const deleteVehiculo = (vehiculo) => {
    if (window.confirm(`¿Deseas desactivar el vehículo ${vehiculo.placa}?`)) {
        router.delete(`/vehiculos/${vehiculo.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Administración</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Vehículos</h1>
                    <p class="mt-1 text-sm text-slate-600">Consulta y administra la flota vehicular.</p>
                </div>
                <button
                    v-if="$page.props.can_operate"
                    type="button"
                    @click="createVehiculo"
                    class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400"
                >
                    Nuevo vehículo
                </button>
            </header>

            <section
                v-if="$page.props.can_operate && formVisible"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            >
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ editingVehiculo ? 'Editar vehículo' : 'Registrar vehículo' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">Completa los datos de la unidad.</p>
                    </div>
                    <button
                        type="button"
                        @click="closeForm"
                        class="rounded-md px-2 py-1 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                    >
                        Cerrar
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="vehiculo-placa" class="mb-1.5 block text-sm font-medium text-slate-700">Placa</label>
                        <input
                            id="vehiculo-placa"
                            v-model="form.placa"
                            type="text"
                            required
                            maxlength="15"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.placa" class="mt-1 text-sm text-red-600">{{ form.errors.placa }}</p>
                    </div>

                    <div>
                        <label for="vehiculo-marca" class="mb-1.5 block text-sm font-medium text-slate-700">Marca</label>
                        <input
                            id="vehiculo-marca"
                            v-model="form.marca"
                            type="text"
                            required
                            maxlength="50"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.marca" class="mt-1 text-sm text-red-600">{{ form.errors.marca }}</p>
                    </div>

                    <div>
                        <label for="vehiculo-modelo" class="mb-1.5 block text-sm font-medium text-slate-700">Modelo</label>
                        <input
                            id="vehiculo-modelo"
                            v-model="form.modelo"
                            type="text"
                            required
                            maxlength="80"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.modelo" class="mt-1 text-sm text-red-600">{{ form.errors.modelo }}</p>
                    </div>

                    <div>
                        <label for="vehiculo-anio" class="mb-1.5 block text-sm font-medium text-slate-700">Año</label>
                        <input
                            id="vehiculo-anio"
                            v-model="form.anio"
                            type="number"
                            min="1980"
                            max="2100"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.anio" class="mt-1 text-sm text-red-600">{{ form.errors.anio }}</p>
                    </div>

                    <div>
                        <label for="vehiculo-combustible" class="mb-1.5 block text-sm font-medium text-slate-700">Tipo de combustible</label>
                        <select
                            id="vehiculo-combustible"
                            v-model="form.tipo_combustible"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                            <option value="diesel">Diésel</option>
                            <option value="gasolina">Gasolina</option>
                        </select>
                        <p v-if="form.errors.tipo_combustible" class="mt-1 text-sm text-red-600">{{ form.errors.tipo_combustible }}</p>
                    </div>

                    <div>
                        <label for="vehiculo-rendimiento" class="mb-1.5 block text-sm font-medium text-slate-700">Rendimiento (km/litro)</label>
                        <input
                            id="vehiculo-rendimiento"
                            v-model="form.rendimiento_km_litro"
                            type="number"
                            min="0.01"
                            max="99.99"
                            step="0.01"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.rendimiento_km_litro" class="mt-1 text-sm text-red-600">{{ form.errors.rendimiento_km_litro }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3 sm:col-span-2">
                        <button
                            type="button"
                            @click="closeForm"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Guardando...' : (editingVehiculo ? 'Guardar cambios' : 'Crear vehículo') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Placa</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Marca</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Modelo</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Año</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Combustible</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Estado</th>
                                <th v-if="$page.props.can_operate" scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="vehiculo in vehiculos.data" :key="vehiculo.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-900">{{ vehiculo.placa }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ vehiculo.marca }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ vehiculo.modelo }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ vehiculo.anio || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                    {{ vehiculo.tipo_combustible === 'diesel' ? 'Diésel' : (vehiculo.tipo_combustible === 'gasolina' ? 'Gasolina' : '—') }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <StatusBadge :status="vehiculo.activo ? 'Activo' : 'Inactivo'" />
                                </td>
                                <td v-if="$page.props.can_operate" class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="editVehiculo(vehiculo)"
                                            class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteVehiculo(vehiculo)"
                                            class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                        >
                                            Desactivar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="vehiculos.data.length === 0">
                                <td :colspan="$page.props.can_operate ? 7 : 6" class="px-5 py-10 text-center text-sm text-slate-500">
                                    No hay vehículos registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-3">
                    <Pagination :links="vehiculos.links" />
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
