<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-r from-azul to-azul-dark">
        <!-- Contenido -->
        <div class="flex flex-col items-center bg-gradient-to-r from-azul to-azul-dark pt-8">
            <div>
                <Link href="/">
                    <img src="/talleres_imsj/images/San-Jose-LOGO-b.png" alt="Logo" class="mt-20 w-48 h-48" />
                </Link>
            </div>

            <div class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">
                <slot />
            </div>
        </div>
    </div>
</template>

