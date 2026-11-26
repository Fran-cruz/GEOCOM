<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    gasolineras: {
        type: Object,
        required: true,
    },
});

const formVisible = ref(false);
const editingGasolinera = ref(null);
const form = useForm({
    nombre: '',
    direccion: '',
    codigo_qr: '',
});

const closeForm = () => {
    formVisible.value = false;
    editingGasolinera.value = null;
    form.reset();
    form.clearErrors();
};

const createGasolinera = () => {
    editingGasolinera.value = null;
    form.reset();
    form.clearErrors();
    formVisible.value = true;
};

const editGasolinera = (gasolinera) => {
    editingGasolinera.value = gasolinera;
    form.clearErrors();
    form.nombre = gasolinera.nombre || '';
    form.direccion = gasolinera.direccion || '';
    form.codigo_qr = gasolinera.codigo_qr || '';
    formVisible.value = true;
};

const submitForm = () => {
    if (editingGasolinera.value) {
        form.put(`/gasolineras/${editingGasolinera.value.id}`, {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post('/gasolineras', {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

const deleteGasolinera = (gasolinera) => {
    if (window.confirm(`¿Deseas desactivar la gasolinera ${gasolinera.nombre}?`)) {
        router.delete(`/gasolineras/${gasolinera.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Administración</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Gasolineras</h1>
                    <p class="mt-1 text-sm text-slate-600">Consulta y administra las estaciones afiliadas.</p>
                </div>
                <button
                    v-if="$page.props.can_operate"
                    type="button"
                    @click="createGasolinera"
                    class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400"
                >
                    Nueva gasolinera
                </button>
            </header>

            <section
                v-if="$page.props.can_operate && formVisible"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            >
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ editingGasolinera ? 'Editar gasolinera' : 'Registrar gasolinera' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">Completa los datos de la estación afiliada.</p>
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
                        <label for="gasolinera-nombre" class="mb-1.5 block text-sm font-medium text-slate-700">Nombre</label>
                        <input
                            id="gasolinera-nombre"
                            v-model="form.nombre"
                            type="text"
                            required
                            maxlength="100"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.nombre" class="mt-1 text-sm text-red-600">{{ form.errors.nombre }}</p>
                    </div>

                    <div>
                        <label for="gasolinera-direccion" class="mb-1.5 block text-sm font-medium text-slate-700">
                            Dirección <span class="font-normal text-slate-500">(opcional)</span>
                        </label>
                        <input
                            id="gasolinera-direccion"
                            v-model="form.direccion"
                            type="text"
                            maxlength="255"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.direccion" class="mt-1 text-sm text-red-600">{{ form.errors.direccion }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="gasolinera-codigo-qr" class="mb-1.5 block text-sm font-medium text-slate-700">Código QR</label>
                        <input
                            id="gasolinera-codigo-qr"
                            v-model="form.codigo_qr"
                            type="text"
                            required
                            maxlength="100"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.codigo_qr" class="mt-1 text-sm text-red-600">{{ form.errors.codigo_qr }}</p>
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
                            {{ form.processing ? 'Guardando...' : (editingGasolinera ? 'Guardar cambios' : 'Crear gasolinera') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Nombre</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Dirección</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Código QR</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Estado</th>
                                <th v-if="$page.props.can_operate" scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="gasolinera in gasolineras.data" :key="gasolinera.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-slate-900">{{ gasolinera.nombre }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ gasolinera.direccion || '—' }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    <span class="break-all font-mono text-xs">{{ gasolinera.codigo_qr }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <StatusBadge :status="gasolinera.activo ? 'Activo' : 'Inactivo'" />
                                </td>
                                <td v-if="$page.props.can_operate" class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="editGasolinera(gasolinera)"
                                            class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteGasolinera(gasolinera)"
                                            class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                        >
                                            Desactivar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="gasolineras.data.length === 0">
                                <td :colspan="$page.props.can_operate ? 5 : 4" class="px-5 py-10 text-center text-sm text-slate-500">
                                    No hay gasolineras registradas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-3">
                    <Pagination :links="gasolineras.links" />
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
