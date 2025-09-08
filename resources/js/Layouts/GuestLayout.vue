<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Navbar -->
        <nav class="border-b border-gray-100 bg-gradient-to-r from-azul to-azul-dark">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-24 items-center justify-between">
                    <div class="flex items-center">
                        <Link href="/" class="flex-shrink-0">
                            <ApplicationLogo class="block h-10 w-auto fill-current text-white" />
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Contenido -->
        <main>
            <slot />
        </main>
    </div>
</template>

