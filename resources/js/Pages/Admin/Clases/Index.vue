<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  title: { type: String, default: 'Clases (Administrador)' },
})
</script>

<template>
  <Head :title="props.title" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          {{ props.title }}
        </h2>
        <p class="text-sm text-gray-600">
          Listado y gestión de clases. (Vacío por ahora)
        </p>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg">
          <div class="p-6 space-y-3">
            <p class="text-gray-700 text-sm">
              Próximamente: tabla con clases, filtros por taller/fecha/docente y acciones.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
