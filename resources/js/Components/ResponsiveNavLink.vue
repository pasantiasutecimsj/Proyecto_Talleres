<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
    danger: {
        type: Boolean,
        default: false,
    },
});

const classes = computed(() => {
    if (props.danger) {
        return 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-red-400 hover:text-red-600 hover:bg-red-50 focus:outline-none transition duration-150 ease-in-out';
    }

    return props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-white text-start text-base font-medium text-white bg-azul/70 focus:outline-none transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-blue-200 hover:bg-white/10 hover:border-white focus:outline-none transition duration-150 ease-in-out';
});

</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
