<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Bienvenido" />
    <GuestLayout>
        <div class="flex flex-col items-center justify-center min-h-[60vh]">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">
                Bienvenido al programa interno de gestión de talleres
            </h1>
            <p class="text-lg text-gray-600 mb-6 text-center">
                Debe iniciar sesión para utilizar el programa.
            </p>
            <Link
                :href="route('login')"
                class="inline-block rounded-md bg-azul px-6 py-2 text-white text-lg font-semibold shadow transition hover:bg-azul-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-azul-dark"
            >
                Iniciar sesión
            </Link>
        </div>
    </GuestLayout>
</template>
