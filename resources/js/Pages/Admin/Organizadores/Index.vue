<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import OrganizadorModal from "./OrganizadorModal.vue";
import OrganizadorFiltrosModal from "./OrganizadorFiltrosModal.vue";

const props = defineProps({
  organizadores: { type: Array, default: () => [] },
  talleres: { type: Array, default: () => [] }, // activos
  filtros: { type: Object, default: () => ({ busqueda: "", taller: "", nombre: "", estado: "activos" }) },
});

const showModal = ref(false);
const editing = ref(null);

// Filtros
const mostrarModalFiltros = ref(false);
const filtros = ref({
  busqueda: props.filtros?.busqueda ?? "",
  taller:   props.filtros?.taller   ?? "",
  nombre:   props.filtros?.nombre   ?? "",
  estado:   props.filtros?.estado   ?? "activos", // activos|inactivos|todos
});

const openNew  = () => { editing.value = null; showModal.value = true; };
const openEdit = (org) => { editing.value = org; showModal.value = true; };
const closeModal = () => (showModal.value = false);
const handleSaved = () => { /* redirige en POST */ };

// Navegar con filtros (incluye estado)
const navegarConFiltros = () => {
  const params = {
    busqueda: filtros.value.busqueda?.trim() || undefined,
    taller:   filtros.value.taller || undefined,
    nombre:   filtros.value.nombre?.trim() || undefined,
    estado:   filtros.value.estado || "activos",
  };
  Object.keys(params).forEach(k => params[k] === undefined && delete params[k]);
  router.get(route("admin.organizadores.index"), params, { preserveScroll: true, preserveState: true });
};

const aplicarFiltros = () => { navegarConFiltros(); mostrarModalFiltros.value = false; };
const limpiarFiltros = () => {
  filtros.value = { busqueda: "", taller: "", nombre: "", estado: filtros.value.estado };
  router.get(route("admin.organizadores.index"), { estado: filtros.value.estado }, { preserveScroll: true, preserveState: true });
};

// Borrado lógico
const desactivar = (org) => {
  if (confirm(`¿Desactivar al organizador ${org.ci}?`)) {
    router.delete(route("admin.organizadores.destroy", org.ci), {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["organizadores", "filtros"] }),
    });
  }
};
const restaurar = (org) => {
  if (confirm(`¿Restaurar al organizador ${org.ci}?`)) {
    router.patch(route("admin.organizadores.restore", org.ci), {}, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ["organizadores", "filtros"] }),
    });
  }
};
</script>

<template>
  <Head title="Organizadores (Administrador)" />
  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Organizadores (Administrador)</h2>
        <p class="text-sm text-gray-600">Alta/mantenimiento de organizadores y sincronización con Registro de Personas.</p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">Gestión de Organizadores</h3>
            <p class="text-sm text-gray-600">Hacé clic en una fila para sincronizar datos de la persona.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label class="text-sm text-gray-700">Estado:</label>
            <select v-model="filtros.estado" @change="navegarConFiltros" class="rounded-md border-gray-300 text-sm">
              <option value="activos">Activos</option>
              <option value="inactivos">Inactivos</option>
            </select>

            <PrimaryButton @click="mostrarModalFiltros = true">🔎︎ Filtros</PrimaryButton>
            <PrimaryButton @click="openNew()">+ Nuevo Organizador</PrimaryButton>
          </div>
        </div>

        <!-- Chips de filtros -->
        <div v-if="filtros.busqueda || filtros.taller || filtros.nombre" class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3">
          <div class="flex items-start justify-between gap-3">
            <span class="text-sm font-medium text-gray-700">Filtros activos</span>
            <SecondaryButton @click="limpiarFiltros" class="!py-1 !px-3 text-sm">Limpiar filtros</SecondaryButton>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span v-if="filtros.busqueda" class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">CI:</strong> {{ filtros.busqueda }}
            </span>
            <span v-if="filtros.nombre" class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Nombre:</strong> {{ filtros.nombre }}
            </span>
            <span v-if="filtros.taller" class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Taller:</strong>
              {{ (props.talleres.find(t => String(t.id) === String(filtros.taller))?.nombre) || filtros.taller }}
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
                    <th class="w-1/6 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CI</th>
                    <th class="w-1/4 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre y Apellido</th>
                    <th class="w-7/12 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talleres que organiza</th>
                    <th class="px-6 py-3"></th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="org in props.organizadores" :key="org.ci" class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" @click="openEdit(org)">{{ org.ci }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" @click="openEdit(org)">
                      <span v-if="org.nombre || org.apellido">{{ [org.nombre, org.apellido].filter(Boolean).join(' ') }}</span>
                      <span v-else class="text-gray-500">—</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900" @click="openEdit(org)">
                      <div class="flex flex-wrap gap-2">
                        <span v-for="taller in org.talleres" :key="taller.id" class="inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-3 py-0.5 text-xs font-medium">
                          {{ taller.nombre }}
                        </span>
                        <span v-if="!org.talleres || org.talleres.length === 0" class="text-gray-500">—</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                      <div class="flex gap-2 justify-end">
                        <template v-if="org.Activo === false">
                          <SecondaryButton @click.stop="restaurar(org)">Restaurar</SecondaryButton>
                        </template>
                        <template v-else>
                          <SecondaryButton @click.stop="desactivar(org)">Desactivar</SecondaryButton>
                        </template>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="props.organizadores.length === 0">
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">No hay organizadores para mostrar.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>

    <OrganizadorModal
      :show="showModal"
      :editing="editing"
      :talleres="props.talleres"
      @close="closeModal"
      @saved="handleSaved"
    />

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
