<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  title: { type: String, default: 'Mis clases (Docente)' },
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
          Agenda del docente: hoy y próximas clases. (Placeholder)
        </p>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg">
          <div class="p-6 text-sm text-gray-700">
            Próximamente: tabla con fecha/hora, taller, cupos, acciones.
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
