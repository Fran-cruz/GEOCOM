<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    conductores: {
        type: Object,
        required: true,
    },
});

const formVisible = ref(false);
const editingConductor = ref(null);
const form = useForm({
    nombre: '',
    correo: '',
    contrasena: '',
    telefono: '',
    cargo: '',
});

const closeForm = () => {
    formVisible.value = false;
    editingConductor.value = null;
    form.reset();
    form.clearErrors();
};

const createConductor = () => {
    editingConductor.value = null;
    form.reset();
    form.clearErrors();
    formVisible.value = true;
};

const editConductor = (conductor) => {
    editingConductor.value = conductor;
    form.clearErrors();
    form.nombre = conductor.usuario?.name || '';
    form.correo = conductor.usuario?.email || '';
    form.contrasena = '';
    form.telefono = conductor.telefono || '';
    form.cargo = conductor.cargo || '';
    formVisible.value = true;
};

const submitForm = () => {
    if (editingConductor.value) {
        form.put(`/conductores/${editingConductor.value.id}`, {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post('/conductores', {
        preserveScroll: true,
        onSuccess: closeForm,
    });
};

const deleteConductor = (conductor) => {
    const nombre = conductor.usuario?.name || 'este conductor';
    if (window.confirm(`¿Deseas desactivar a ${nombre}?`)) {
        router.delete(`/conductores/${conductor.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <section class="space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Administración</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Conductores</h1>
                    <p class="mt-1 text-sm text-slate-600">Consulta y administra los conductores registrados.</p>
                </div>
                <button
                    v-if="$page.props.can_operate"
                    type="button"
                    @click="createConductor"
                    class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400"
                >
                    Nuevo conductor
                </button>
            </header>

            <section
                v-if="$page.props.can_operate && formVisible"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            >
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ editingConductor ? 'Editar conductor' : 'Registrar conductor' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">Completa los datos solicitados.</p>
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
                        <label for="conductor-nombre" class="mb-1.5 block text-sm font-medium text-slate-700">Nombre</label>
                        <input
                            id="conductor-nombre"
                            v-model="form.nombre"
                            type="text"
                            autocomplete="name"
                            required
                            maxlength="255"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.nombre" class="mt-1 text-sm text-red-600">{{ form.errors.nombre }}</p>
                    </div>

                    <div>
                        <label for="conductor-correo" class="mb-1.5 block text-sm font-medium text-slate-700">Correo</label>
                        <input
                            id="conductor-correo"
                            v-model="form.correo"
                            type="email"
                            autocomplete="email"
                            required
                            maxlength="255"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.correo" class="mt-1 text-sm text-red-600">{{ form.errors.correo }}</p>
                    </div>

                    <div>
                        <label for="conductor-contrasena" class="mb-1.5 block text-sm font-medium text-slate-700">
                            Contraseña
                            <span v-if="editingConductor" class="font-normal text-slate-500">(opcional al editar)</span>
                        </label>
                        <input
                            id="conductor-contrasena"
                            v-model="form.contrasena"
                            type="password"
                            :required="!editingConductor"
                            autocomplete="new-password"
                            minlength="8"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.contrasena" class="mt-1 text-sm text-red-600">{{ form.errors.contrasena }}</p>
                    </div>

                    <div>
                        <label for="conductor-telefono" class="mb-1.5 block text-sm font-medium text-slate-700">Teléfono</label>
                        <input
                            id="conductor-telefono"
                            v-model="form.telefono"
                            type="tel"
                            autocomplete="tel"
                            required
                            maxlength="20"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.telefono" class="mt-1 text-sm text-red-600">{{ form.errors.telefono }}</p>
                    </div>

                    <div>
                        <label for="conductor-cargo" class="mb-1.5 block text-sm font-medium text-slate-700">Cargo</label>
                        <input
                            id="conductor-cargo"
                            v-model="form.cargo"
                            type="text"
                            required
                            maxlength="100"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                        >
                        <p v-if="form.errors.cargo" class="mt-1 text-sm text-red-600">{{ form.errors.cargo }}</p>
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
                            {{ form.processing ? 'Guardando...' : (editingConductor ? 'Guardar cambios' : 'Crear conductor') }}
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
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Correo</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Teléfono</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Cargo</th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Estado</th>
                                <th v-if="$page.props.can_operate" scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="conductor in conductores.data" :key="conductor.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-slate-900">{{ conductor.usuario?.name || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ conductor.usuario?.email || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ conductor.telefono || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ conductor.cargo || '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <StatusBadge :status="conductor.activo ? 'Activo' : 'Inactivo'" />
                                </td>
                                <td v-if="$page.props.can_operate" class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="editConductor(conductor)"
                                            class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteConductor(conductor)"
                                            class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                        >
                                            Desactivar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="conductores.data.length === 0">
                                <td :colspan="$page.props.can_operate ? 6 : 5" class="px-5 py-10 text-center text-sm text-slate-500">
                                    No hay conductores registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-3">
                    <Pagination :links="conductores.links" />
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
