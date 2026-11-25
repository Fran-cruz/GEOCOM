<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap gap-1 mt-4">
        <template v-for="(link, key) in links" :key="key">
            <span
                v-if="link.url === null"
                v-html="link.label"
                class="px-3 py-1.5 text-xs text-gray-400 border border-gray-200 rounded"
            />
            <Link
                v-else
                :href="link.url"
                v-html="link.label"
                preserve-scroll
                class="px-3 py-1.5 text-xs border rounded transition"
                :class="link.active
                    ? 'bg-slate-900 text-white border-slate-900 font-semibold'
                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            />
        </template>
    </div>
</template>