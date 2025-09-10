<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  title: { type: String, default: 'Asistentes por clase (Docente)' },
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
          Seleccioná una clase para ver asistentes y marcar asistencia (en la misma vista).
        </p>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg">
          <div class="p-6 space-y-3 text-sm text-gray-700">
            <p>Próximamente: listado/buscador de clases (por fecha/taller).</p>
            <!-- Ejemplo de navegación futura al detalle -->
            <div class="flex items-center gap-3">
              <Link
                href="#"
                class="inline-flex items-center rounded-md bg-azul px-4 py-2 text-white shadow hover:bg-azul-claro transition"
              >
                Ir al detalle de una clase (demo)
              </Link>
              <span class="text-xs text-gray-500">
                Esto apuntará a <code>/docente/clases/{id}</code> (doc.clases.show)
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
