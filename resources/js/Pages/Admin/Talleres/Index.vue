<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
const props = defineProps({ title: String })
</script>

<template>
  <Head :title="props.title || 'Talleres (Administrador)'" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          {{ props.title || 'Talleres (Administrador)' }}
        </h2>
        <p class="text-sm text-gray-600">
          Listado de talleres. (Vacío por ahora)
        </p>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg">
          <div class="p-6">
            <div class="text-gray-700 text-sm">
              Próximamente: tabla con talleres, filtros y acciones.
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
