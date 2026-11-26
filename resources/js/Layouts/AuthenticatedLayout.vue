<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const mobileMenuOpen = ref(false);
const user = computed(() => page.props.auth?.user);
const canConsult = computed(() => Boolean(page.props.auth?.can_consult));
const canOperate = computed(() => Boolean(page.props.auth?.can_operate));
const flash = computed(() => page.props.flash || {});
const currentPath = computed(() => page.url.split('?')[0] || '/');

const navigation = [
    { label: 'Dashboard', href: '/dashboard', short: 'D' },
    { label: 'Solicitudes', href: '/solicitudes', short: 'S' },
    { label: 'Órdenes', href: '/ordenes', short: 'O' },
    { label: 'Conductores', href: '/conductores', short: 'C' },
    { label: 'Vehículos', href: '/vehiculos', short: 'V' },
    { label: 'Gasolineras', href: '/gasolineras', short: 'G' },
    { label: 'Caja Chica', href: '/caja-chica', short: 'CC' },
    { label: 'Reportes', href: '/reportes', short: 'R' },
];

const roleLabel = computed(() => {
    const roles = {
        admin: 'Administrador',
        supervisor: 'Supervisor',
        contabilidad: 'Contabilidad',
    };

    return roles[user.value?.rol] || user.value?.rol || 'Usuario';
});

const isActive = (href) => {
    if (href === '/ordenes') {
        return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
    }

    return currentPath.value === href;
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};

const logout = () => {
    router.post('/logout');
};

watch(currentPath, closeMobileMenu);
</script>

<template>
    <div class="min-h-screen bg-[#FAF9FC] text-[#24212D]" style="font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif">
        <button
            v-if="mobileMenuOpen"
            type="button"
            class="fixed inset-0 z-40 bg-slate-950/55 md:hidden"
            aria-label="Cerrar menú de navegación"
            @click="closeMobileMenu"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex h-screen w-72 -translate-x-full flex-col overflow-y-auto bg-[#342052] text-white shadow-[12px_0_40px_rgba(52,32,82,0.12)] transition-transform duration-200 md:translate-x-0"
            :class="{ 'translate-x-0': mobileMenuOpen }"
        >
            <div class="flex items-center justify-between border-b border-white/10 px-6 py-6">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-3 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-[#EEE7FA]"
                    aria-label="GESTCOM, ir al dashboard"
                    @click="closeMobileMenu"
                >
                    <span class="grid h-10 w-10 place-items-center text-[#EEE7FA]">
                        <svg viewBox="0 0 40 40" fill="none" aria-hidden="true" class="h-full w-full">
                            <path d="M20 5.5 33 12.75v14.5L20 34.5 7 27.25v-14.5L20 5.5Z" stroke="currentColor" stroke-width="2" />
                            <path d="M25.7 16.3a7.5 7.5 0 1 0 .1 7.4M20 20h8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-bold tracking-[0.16em]">GESTCOM</span>
                        <span class="mt-0.5 block text-[10px] tracking-wide text-white/50">Gestión institucional</span>
                    </span>
                </Link>
                <button
                    type="button"
                    class="grid h-9 w-9 place-items-center rounded-lg text-white/70 transition hover:bg-white/10 hover:text-white md:hidden"
                    aria-label="Cerrar menú"
                    @click="closeMobileMenu"
                >
                    <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                        <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-linecap="round" stroke-width="1.7" />
                    </svg>
                </button>
            </div>

            <div class="px-5 pt-7">
                <p class="px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-white/40">Menú principal</p>
                <nav v-if="canConsult" aria-label="Navegación principal" class="mt-3 space-y-1">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#EEE7FA]"
                        :class="isActive(item.href)
                            ? 'bg-[#6042A6] text-white shadow-[0_8px_24px_rgba(20,10,40,0.18)]'
                            : 'text-white/70 hover:bg-white/8 hover:text-white'"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                        @click="closeMobileMenu"
                    >
                        <span
                            class="grid h-7 min-w-7 place-items-center rounded-lg border text-[10px] font-bold transition"
                            :class="isActive(item.href)
                                ? 'border-white/20 bg-white/12 text-white'
                                : 'border-white/10 bg-white/5 text-white/55 group-hover:border-white/20 group-hover:text-white'"
                            aria-hidden="true"
                        >
                            {{ item.short }}
                        </span>
                        <span>{{ item.label }}</span>
                        <span v-if="isActive(item.href)" class="ml-auto h-1.5 w-1.5 rounded-full bg-[#EEE7FA]" />
                    </Link>
                </nav>
                <p v-else class="mt-3 rounded-xl bg-white/5 px-3 py-3 text-xs leading-5 text-white/60">
                    Tu cuenta no tiene acceso a los módulos de consulta.
                </p>
            </div>

            <div class="mt-auto px-5 pb-5 pt-8">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#6042A6] text-sm font-bold text-white">
                            {{ user?.name?.slice(0, 1)?.toUpperCase() || 'U' }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-semibold text-white">{{ user?.name || 'Usuario' }}</span>
                            <span class="mt-0.5 block truncate text-[10px] text-white/50">{{ roleLabel }}</span>
                        </span>
                    </div>
                </div>
                <p class="mt-4 px-1 text-[10px] tracking-wide text-white/35">GESTCOM · Portal institucional</p>
            </div>
        </aside>

        <div class="min-h-screen md:pl-72">
            <header class="sticky top-0 z-30 border-b border-[#E5E0EB]/80 bg-white/95 backdrop-blur">
                <div class="mx-auto flex min-h-[76px] max-w-[1600px] items-center justify-between gap-4 px-4 sm:px-7 lg:px-9">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#E5E0EB] text-[#342052] transition hover:bg-[#EEE7FA] md:hidden"
                            :aria-expanded="mobileMenuOpen"
                            aria-label="Abrir menú de navegación"
                            @click="mobileMenuOpen = true"
                        >
                            <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-linecap="round" stroke-width="1.6" />
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <span class="inline-flex rounded-full bg-[#EEE7FA] px-2 py-1 text-[9px] font-bold uppercase tracking-[0.14em] text-[#6042A6]">
                                Portal institucional
                            </span>
                            <p class="mt-0.5 truncate text-sm font-semibold text-[#24212D]">
                                {{ user ? `Bienvenido, ${user.name}` : 'Bienvenido a GESTCOM' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3 sm:gap-4">
                        <div v-if="user" class="hidden text-right sm:block">
                            <p class="max-w-48 truncate text-xs font-semibold text-[#24212D]">{{ user.name }}</p>
                            <p class="mt-0.5 text-[10px] text-[#706A7A]">{{ roleLabel }}</p>
                        </div>
                        <span v-if="user" class="hidden h-9 w-px bg-[#E5E0EB] sm:block" />
                        <button
                            v-if="user"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-[#E5E0EB] px-3 py-2 text-xs font-semibold text-[#706A7A] transition hover:border-[#6042A6]/30 hover:bg-[#EEE7FA] hover:text-[#6042A6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6042A6]/30"
                            @click="logout"
                        >
                            <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M8 3.5H4.5a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1H8m3-3 3.5-3.5L11 6.5m3.5 3.5H7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                            </svg>
                            <span class="hidden sm:inline">Cerrar sesión</span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-7 sm:py-8 lg:px-9">
                <div v-if="flash.success || flash.error" class="mb-6 space-y-3">
                    <div
                        v-if="flash.success"
                        class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                        role="status"
                    >
                        <span class="font-semibold">Listo</span>
                        <span>{{ flash.success }}</span>
                    </div>
                    <div
                        v-if="flash.error"
                        class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                        role="alert"
                    >
                        <span class="font-semibold">Atención</span>
                        <span>{{ flash.error }}</span>
                    </div>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
