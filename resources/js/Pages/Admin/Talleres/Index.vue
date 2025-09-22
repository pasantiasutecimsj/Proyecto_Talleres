<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TallerModal from "./TallerModal.vue";
import TallerFiltrosModal from "./TallerFiltrosModal.vue";

const props = defineProps({
  talleres: Array,
  ciudades: Array, // [{id, nombre, ...}]
});

// ====== Estado UI ======
const showModal = ref(false);
const editingTaller = ref(null);

// Filtros
const mostrarModalFiltros = ref(false);
const filtros = ref({
  nombre: "",   // busca en nombre y descripción
  ciudad: "",   // id_ciudad
});

// ====== Orden ciudades (solo para mostrar chips) ======
const sortedCiudades = computed(() =>
  [...props.ciudades].sort((a, b) => a.nombre.localeCompare(b.nombre))
);

// ====== Filtro frontal (sin paginar) ======
const filteredTalleres = computed(() => {
  const term = (filtros.value.nombre || "").trim().toLowerCase();
  const ciudad = filtros.value.ciudad ? String(filtros.value.ciudad) : "";

  return props.talleres.filter((t) => {
    const okCiudad = !ciudad || String(t.id_ciudad) === ciudad;
    const txt = `${t.nombre ?? ""} ${t.descripcion ?? ""}`.toLowerCase();
    const okNombre = !term || txt.includes(term);
    return okCiudad && okNombre;
  });
});

// ====== ABM ======
const openModal = (taller = null) => {
  editingTaller.value = taller;
  showModal.value = true;
};
const closeModal = () => {
  showModal.value = false;
  editingTaller.value = null;
};
const handleSaved = () => {
  // Si el store/update no redirige, acá podrías forzar un refresh con router.reload().
  closeModal();
};

// ====== Filtros (acciones de banner) ======
const limpiarFiltros = () => {
  filtros.value = { nombre: "", ciudad: "" };
};
</script>

<template>
  <Head title="Talleres (Administrador)" />

  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Talleres (Administrador)
        </h2>
        <p class="text-sm text-gray-600">
          Gestión de talleres: crear, editar y mantener información básica.
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Header con acciones -->
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">
              Gestión de Talleres
            </h3>
            <p class="text-sm text-gray-600">
              Administrá los talleres: nombre, descripción y ubicación.
            </p>
          </div>
          <div class="flex gap-2">
            <PrimaryButton @click="mostrarModalFiltros = true">🔎︎ Filtros</PrimaryButton>
            <PrimaryButton @click="openModal()">+ Nuevo Taller</PrimaryButton>
          </div>
        </div>

        <!-- Banner: Filtros activos -->
        <div
          v-if="filtros.nombre || filtros.ciudad"
          class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"
                   class="h-5 w-5 text-gray-600" fill="currentColor" aria-hidden="true">
                <path d="M480-120q-75 0-140.5-28.5T226-226q-49-49-77.5-114.5T120-480q0-75 28.5-140.5T226-734q49-49 114.5-77.5T480-840q75 0 140.5 28.5T734-734q49 49 77.5 114.5T840-480q0 75-28.5 140.5T734-226q-49 49-114.5 77.5T480-120Zm0-60q135 0 232.5-97.5T810-480q0-135-97.5-232.5T480-810q-135 0-232.5 97.5T150-480q0 135 97.5 232.5T480-180Zm0-420q-17 0-28.5-11.5T440-640q0-17 11.5-28.5T480-680q17 0 28.5 11.5T520-640q0 17-11.5 28.5T480-600Zm-40 320h80v-240h-80v240Z"/>
              </svg>
              <span class="text-sm font-medium text-gray-700">Filtros activos</span>
            </div>

            <SecondaryButton @click="limpiarFiltros" class="!py-1 !px-3 text-sm">
              Limpiar filtros
            </SecondaryButton>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span
              v-if="filtros.nombre"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Nombre/Desc.:</strong> {{ filtros.nombre }}
            </span>

            <span
              v-if="filtros.ciudad"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Ciudad:</strong>
              {{
                (sortedCiudades.find(c => String(c.id) === String(filtros.ciudad))?.nombre)
                || filtros.ciudad
              }}
            </span>
          </div>
        </div>

        <!-- Card tabla -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <!-- Tabla -->
            <div class="overflow-x-auto">
              <p class="text-gray-600 mb-4">
                → Para editar un taller, hacé clic en la fila correspondiente.
              </p>

              <table class="min-w-full table-auto">
                <thead>
                  <tr class="bg-gray-50 border-b">
                    <th class="w-1/5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Ciudad
                    </th>
                    <th class="w-1/5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Nombre
                    </th>
                    <th class="w-2/5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Descripción
                    </th>
                    <th class="w-1/5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Dirección
                    </th>
                  </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                  <tr
                    v-for="taller in filteredTalleres"
                    :key="taller.id"
                    @click="openModal(taller)"
                    class="hover:bg-gray-50 hover:scale-95 transform transition-all duration-200 ease-in-out cursor-pointer"
                  >
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ taller.ciudad || '—' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ taller.nombre }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                      <span class="line-clamp-2">{{ taller.descripcion || '—' }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ [taller.calle, taller.numero].filter(Boolean).join(' ') || '—' }}
                    </td>
                  </tr>

                  <tr v-if="filteredTalleres.length === 0">
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                      No hay talleres para mostrar.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- /Card tabla -->
      </div>
    </div>

    <!-- Modal crear/editar taller -->
    <TallerModal
      :show="showModal"
      :editing="editingTaller"
      :ciudades="props.ciudades"
      @close="closeModal"
      @saved="handleSaved"
    />

    <!-- Modal Filtros (componente hijo) -->
    <TallerFiltrosModal
      :show="mostrarModalFiltros"
      v-model:filtros="filtros"
      :ciudades="props.ciudades"
      @close="mostrarModalFiltros = false"
    />
  </AuthenticatedLayout>
</template>
