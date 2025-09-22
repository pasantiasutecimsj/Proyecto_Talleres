<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import OrganizadorModal from "./OrganizadorModal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import OrganizadorFiltrosModal from "./OrganizadorFiltrosModal.vue";

const props = defineProps({
  // lista enviada desde el controller
  organizadores: {
    type: Array,
    default: () => [],
  },
  // catálogo de talleres (id, nombre) enviado desde el controller
  talleres: {
    type: Array,
    default: () => [],
  },
  filtros: {
    type: Object,
    default: () => ({ busqueda: "", taller: "", nombre: "" })
  },
});

const showModal = ref(false);
const editing = ref(null);

// Filtros (controlados por el padre)
const mostrarModalFiltros = ref(false);
const filtros = ref({
  busqueda: props.filtros?.busqueda ?? "",
  taller: props.filtros?.taller ?? "",
  nombre: props.filtros?.nombre ?? "",
});

// ====== ABM ======
const openNew = () => {
  editing.value = null;
  showModal.value = true;
};
const openEdit = (org) => {
  editing.value = org;
  showModal.value = true;
};
const closeModal = () => (showModal.value = false);
const handleSaved = () => {
  // No hace falta nada: el POST redirige al index y recarga la lista
};

// ====== Acciones de filtros (navegan al servidor) ======
const navegarConFiltros = () => {
  const params = {
    busqueda: filtros.value.busqueda?.trim() || undefined,
    taller: filtros.value.taller || undefined,
    nombre: filtros.value.nombre?.trim() || undefined,
  };
  Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);

  router.get(route("admin.organizadores.index"), params, {
    preserveScroll: true,
    preserveState: true,
  });
};

const aplicarFiltros = () => {
  navegarConFiltros();
  mostrarModalFiltros.value = false;
};

const limpiarFiltros = () => {
  filtros.value = { busqueda: "", taller: "", nombre: "" };
  router.get(route("admin.organizadores.index"), {}, {
    preserveScroll: true,
    preserveState: true,
  });
};
</script>

<template>
  <Head title="Organizadores (Administrador)" />

  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Organizadores (Administrador)
        </h2>
        <p class="text-sm text-gray-600">
          Alta/mantenimiento de organizadores y sincronización con Registro de Personas.
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Encabezado + botón -->
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">Gestión de Organizadores</h3>
            <p class="text-sm text-gray-600">
              Hacé clic en una fila para sincronizar datos de la persona.
            </p>
          </div>
          <div class="flex gap-2">
            <PrimaryButton @click="mostrarModalFiltros = true">🔎︎ Filtros</PrimaryButton>
            <PrimaryButton @click="openNew()">+ Nuevo Organizador</PrimaryButton>
          </div>
        </div>

        <!-- Filtros activos -->
        <div
          v-if="filtros.busqueda || filtros.taller || filtros.nombre"
          class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"
                   class="h-5 w-5 text-gray-600" fill="currentColor" aria-hidden="true">
                <path
                  d="M480-120q-75 0-140.5-28.5T226-226q-49-49-77.5-114.5T120-480q0-75 28.5-140.5T226-734q49-49 114.5-77.5T480-840q75 0 140.5 28.5T734-734q49 49 77.5 114.5T840-480q0 75-28.5 140.5T734-226q-49 49-114.5 77.5T480-120Zm0-60q135 0 232.5-97.5T810-480q0-135-97.5-232.5T480-810q-135 0-232.5 97.5T150-480q0 135 97.5 232.5T480-180Zm0-420q-17 0-28.5-11.5T440-640q0-17 11.5-28.5T480-680q17 0 28.5 11.5T520-640q0 17-11.5 28.5T480-600Zm-40 320h80v-240h-80v240Z"
                />
              </svg>
              <span class="text-sm font-medium text-gray-700">Filtros activos</span>
            </div>

            <SecondaryButton @click="limpiarFiltros" class="!py-1 !px-3 text-sm">
              Limpiar filtros
            </SecondaryButton>
          </div>

          <!-- Chips -->
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span
              v-if="filtros.busqueda"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">CI:</strong> {{ filtros.busqueda }}
            </span>

            <span
              v-if="filtros.nombre"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Nombre:</strong> {{ filtros.nombre }}
            </span>

            <span
              v-if="filtros.taller"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Taller:</strong>
              {{
                (props.talleres.find(t => String(t.id) === String(filtros.taller))?.nombre)
                || filtros.taller
              }}
            </span>
          </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div class="overflow-x-auto">
              <table class="min-w-full table-auto">
                <thead>
                  <tr class="bg-gray-50 border-b">
                    <th class="w-1/6 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      CI
                    </th>
                    <th class="w-1/4 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Nombre y Apellido
                    </th>
                    <th class="w-7/12 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Talleres que organiza
                    </th>
                  </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                  <tr
                    v-for="org in props.organizadores"
                    :key="org.ci"
                    @click="openEdit(org)"
                    class="hover:bg-gray-50 hover:scale-95 transform transition-all duration-200 ease-in-out cursor-pointer"
                  >
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ org.ci }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <span v-if="org.nombre || org.apellido">
                        {{ [org.nombre, org.apellido].filter(Boolean).join(' ') }}
                      </span>
                      <span v-else class="text-gray-500">—</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                      <div class="flex flex-wrap gap-2">
                        <span
                          v-for="taller in org.talleres"
                          :key="taller.id"
                          class="inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-3 py-0.5 text-xs font-medium"
                        >
                          {{ taller.nombre }}
                        </span>
                        <span v-if="!org.talleres || org.talleres.length === 0" class="text-gray-500">
                          —
                        </span>
                      </div>
                    </td>
                  </tr>

                  <!-- Vacío -->
                  <tr v-if="props.organizadores.length === 0">
                    <td colspan="3" class="px-6 py-12 text-center text-gray-500">
                      No hay organizadores para mostrar.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Modal ABM -->
    <OrganizadorModal
      :show="showModal"
      :editing="editing"
      :talleres="props.talleres"
      @close="closeModal"
      @saved="handleSaved"
    />

    <!-- Modal Filtros (componente hijo) -->
    <OrganizadorFiltrosModal
      :show="mostrarModalFiltros"
      :talleres="props.talleres"
      v-model:filtros="filtros"
      @close="mostrarModalFiltros = false"
      @apply="aplicarFiltros"
      @clear="limpiarFiltros"
    />
  </AuthenticatedLayout>
</template>
