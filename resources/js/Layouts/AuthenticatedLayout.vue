<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash || {});

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex flex-col">
        <nav class="bg-slate-900 text-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <Link href="/dashboard" class="font-bold text-lg tracking-wide text-amber-400">
                        GEOCOM
                    </Link>
                    <div class="hidden md:flex items-center gap-4 text-sm">
                        <Link href="/dashboard" class="hover:text-amber-300">Dashboard</Link>
                        <Link href="/solicitudes" class="hover:text-amber-300">Solicitudes</Link>
                        <Link href="/ordenes" class="hover:text-amber-300">Órdenes</Link>
                        <Link href="/conductores" class="text-slate-400 hover:text-amber-300">Conductores</Link>
                        <Link href="/vehiculos" class="text-slate-400 hover:text-amber-300">Vehículos</Link>
                        <Link href="/gasolineras" class="text-slate-400 hover:text-amber-300">Gasolineras</Link>
                        <Link href="/caja-chica" class="text-slate-400 hover:text-amber-300">Caja Chica</Link>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <span v-if="user" class="text-slate-300">{{ user.name }}</span>
                    <button
                        type="button"
                        @click="logout"
                        class="bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded text-xs font-medium cursor-pointer"
                    >
                        Cerrar sesión
                    </button>
                </div>
            </div>
        </nav>

        <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 mt-4 space-y-2">
            <div v-if="flash.success" class="bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded text-sm">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded text-sm">
                {{ flash.error }}
            </div>
        </div>

        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <slot />
        </main>
    </div>
</template>