<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

// Podés importar tus botones/componentes comunes si los vas a usar
// import PrimaryButton from '@/Components/PrimaryButton.vue'
</script>

<template>
  <Head title="Inicio" />

  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            <!-- Título principal -->
            Bienvenido/a
          </h2>
          <p class="text-sm text-gray-600">Subtítulo o descripción breve</p>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

        <!-- Tarjeta genérica -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900">Título de tarjeta</h3>
            <p class="text-sm text-gray-600">
              Texto o descripción de la tarjeta.
            </p>
          </div>
        </div>

        <!-- Sección de accesos rápidos (placeholder) -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Accesos rápidos</h3>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Link
                href="#"
                class="group bg-azul text-white rounded-md px-5 py-5 shadow hover:bg-azul-claro hover:scale-[1.02] transition duration-150 ease-in-out inline-flex items-center gap-4"
              >
                <!-- Ícono genérico -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"
                     class="w-8 h-8 shrink-0" fill="currentColor" aria-hidden="true">
                  <path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/>
                </svg>
                <div class="text-left">
                  <div class="font-semibold">Acceso ejemplo</div>
                  <div class="text-sm opacity-90">Descripción breve</div>
                </div>
              </Link>
            </div>
          </div>
        </div>

        <!-- Otra tarjeta (ejemplo: movimientos, noticias, etc.) -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900">Otra sección</h3>
            <p class="text-sm text-gray-600">
              Contenido placeholder.
            </p>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
