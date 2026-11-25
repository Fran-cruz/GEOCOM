<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const badgeClasses = computed(() => {
    const key = props.status?.toLowerCase();
    const map = {
        pendiente: 'bg-amber-100 text-amber-800 border-amber-300',
        aprobada: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        completada: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        ejecutada: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        rechazada: 'bg-red-100 text-red-800 border-red-300',
        vencida: 'bg-rose-100 text-rose-800 border-rose-300',
        asignada: 'bg-blue-100 text-blue-800 border-blue-300',
    };
    return map[key] || 'bg-gray-100 text-gray-800 border-gray-300';
});
</script>

<template>
    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full border capitalize" :class="badgeClasses">
        {{ status }}
    </span>
</template>