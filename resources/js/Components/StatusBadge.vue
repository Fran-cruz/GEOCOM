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
        pendiente: 'bg-[#FDF1DD] text-[#8A5A00] border-[#F5A623]',
        abierta: 'bg-[#E3EEFB] text-[#285C91] border-[#4A90D9]',
        abierta_tarde: 'bg-[#E3EEFB] text-[#285C91] border-[#4A90D9]',
        aprobada: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        completada: 'bg-[#E3F1E4] text-[#2E7D32] border-[#2E7D32]',
        completada_tarde: 'bg-[#E3F1E4] text-[#2E7D32] border-[#2E7D32]',
        ejecutada: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        rechazada: 'bg-red-100 text-red-800 border-red-300',
        vencida: 'bg-[#FAE3E3] text-[#C62828] border-[#C62828]',
        cancelada: 'bg-rose-50 text-rose-700 border-rose-200',
        asignada: 'bg-blue-100 text-blue-800 border-blue-300',
    };
    return map[key] || 'bg-gray-100 text-gray-800 border-gray-300';
});

const badgeLabel = computed(() => props.status.replaceAll('_', ' '));
</script>

<template>
    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full border capitalize" :class="badgeClasses">
        {{ badgeLabel }}
    </span>
</template>