<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";

import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Modal from "@/Components/Modal.vue";

import ClaseModal from "./ClaseModal.vue";

const props = defineProps({
  clases: {
    type: Array, // [{ id, fecha_hora, asistentes_maximos, taller:{id,nombre}, docente:{ci, nombre?, apellido?, ...} }]
    default: () => [],
  },
  talleres: {
    type: Array, // [{ id, nombre }]
    default: () => [],
  },
  filtros: {
    type: Object,
    default: () => ({ q: "", taller: "", desde: "", hasta: "" }),
  },
});

const showModal = ref(false);
const editing = ref(null);
const mostrarModalFiltros = ref(false);

const filtros = ref({
  q: props.filtros?.q ?? "",
  taller: props.filtros?.taller ?? "",
  desde: props.filtros?.desde ?? "",
  hasta: props.filtros?.hasta ?? "",
});

const openNew = () => {
  editing.value = null;
  showModal.value = true;
};
const openEdit = (clase) => {
  editing.value = clase;
  showModal.value = true;
};
const closeModal = () => (showModal.value = false);
const handleSaved = () => {
  // El POST/PUT redirige al index y recarga la lista
};

/* =========================
   Filtros: aplicar / limpiar
   ========================= */
const aplicarFiltros = () => {
  const params = {
    q: filtros.value.q?.trim() || undefined,
    taller: filtros.value.taller || undefined,
    desde: filtros.value.desde || undefined,
    hasta: filtros.value.hasta || undefined,
  };
  Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);

  router.get(route("admin.clases.index"), params, {
    preserveScroll: true,
    preserveState: true,
  });
  mostrarModalFiltros.value = false;
};

const limpiarFiltros = () => {
  filtros.value = { q: "", taller: "", desde: "", hasta: "" };
  router.get(route("admin.clases.index"), {}, {
    preserveScroll: true,
    preserveState: true,
  });
};

/* ===============
   Helpers de UI
   =============== */
const fmtFechaHora = (isoLike) => {
  if (!isoLike) return "—";
  const d = new Date(isoLike);
  if (isNaN(d.getTime())) return isoLike; // fallback
  try {
    // Formato legible local (ej: 12/09/2025 14:30)
    return new Intl.DateTimeFormat("es-UY", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    }).format(d);
  } catch {
    return d.toLocaleString?.() ?? String(isoLike);
  }
};

const isPasada = (isoLike) => {
  if (!isoLike) return false;
  const d = new Date(isoLike);
  if (isNaN(d.getTime())) return false;
  return d.getTime() < Date.now();
};

const docenteLabel = (clase) => {
  const d = clase?.docente ?? {};
  const base = [d.nombre, d.apellido].filter(Boolean).join(" ");
  return base || d.ci || "—";
};
</script>

<template>
  <Head title="Clases (Administrador)" />

  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Clases (Administrador)
        </h2>
        <p class="text-sm text-gray-600">
          Alta y mantenimiento de clases por Taller y Docente.
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Encabezado + acciones -->
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">Gestión de Clases</h3>
            <p class="text-sm text-gray-600">
              Hacé clic en una fila para editar la clase.
            </p>
          </div>
          <div class="flex gap-2">
            <PrimaryButton @click="mostrarModalFiltros = true">🔎︎ Filtros</PrimaryButton>
            <PrimaryButton @click="openNew()">+ Nueva Clase</PrimaryButton>
          </div>
        </div>

        <!-- Chips de filtros activos -->
        <div
          v-if="filtros.q || filtros.taller || filtros.desde || filtros.hasta"
          class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-5 w-5 text-gray-600"
                fill="currentColor"
                aria-hidden="true"
              >
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

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span
              v-if="filtros.q"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Docente/CI:</strong> {{ filtros.q }}
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

            <span
              v-if="filtros.desde"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Desde:</strong> {{ filtros.desde }}
            </span>

            <span
              v-if="filtros.hasta"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Hasta:</strong> {{ filtros.hasta }}
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
                    <th class="w-1/5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Fecha / Hora
                    </th>
                    <th class="w-1/4 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Taller
                    </th>
                    <th class="w-1/4 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Docente
                    </th>
                    <th class="w-1/6 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Cupo
                    </th>
                    <th class="w-1/6 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Estado
                    </th>
                  </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                  <tr
                    v-for="cl in props.clases"
                    :key="cl.id"
                    @click="openEdit(cl)"
                    class="hover:bg-gray-50 hover:scale-95 transform transition-all duration-200 ease-in-out cursor-pointer"
                  >
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ fmtFechaHora(cl.fecha_hora) }}
                    </td>

                    <td class="px-6 py-4 text-sm text-gray-900">
                      <span
                        v-if="cl.taller"
                        class="inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-3 py-0.5 text-xs font-medium"
                      >
                        {{ cl.taller?.nombre }}
                      </span>
                      <span v-else class="text-gray-500">—</span>
                    </td>

                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ docenteLabel(cl) }}
                    </td>

                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ cl.asistentes_maximos ?? "—" }}
                    </td>

                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <span
                        v-if="isPasada(cl.fecha_hora)"
                        class="inline-flex items-center rounded-full bg-gray-200 text-gray-700 px-3 py-0.5 text-xs font-medium"
                      >
                        Pasada
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center rounded-full bg-green-100 text-green-800 px-3 py-0.5 text-xs font-medium"
                      >
                        Próxima
                      </span>
                    </td>
                  </tr>

                  <!-- Vacío -->
                  <tr v-if="props.clases.length === 0">
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                      No hay clases para mostrar.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Modal de Clase -->
    <ClaseModal
      :show="showModal"
      :editing="editing"
      :talleres="props.talleres"
      @close="closeModal"
      @saved="handleSaved"
    />

    <!-- Modal de filtros -->
    <Modal :show="mostrarModalFiltros" @close="() => (mostrarModalFiltros.value = false)">
      <div class="p-6 w-full max-w-md">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h2>

        <form @submit.prevent="aplicarFiltros" class="space-y-4">
          <!-- q: CI o nombre del docente -->
          <div>
            <label for="f-q" class="block text-sm font-medium text-gray-700">Docente (CI o Nombre)</label>
            <input
              id="f-q"
              v-model="filtros.q"
              type="text"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
              placeholder="Ej. 12345678 o María Pérez"
            />
          </div>

          <!-- Taller -->
          <div>
            <label for="f-taller" class="block text-sm font-medium text-gray-700">Taller</label>
            <select
              id="f-taller"
              v-model="filtros.taller"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
            >
              <option value="">Todos</option>
              <option v-for="t in props.talleres" :key="t.id" :value="t.id">
                {{ t.nombre }}
              </option>
            </select>
          </div>

          <!-- Rango de fechas -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="f-desde" class="block text-sm font-medium text-gray-700">Desde</label>
              <input
                id="f-desde"
                v-model="filtros.desde"
                type="date"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
              />
            </div>
            <div>
              <label for="f-hasta" class="block text-sm font-medium text-gray-700">Hasta</label>
              <input
                id="f-hasta"
                v-model="filtros.hasta"
                type="date"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
              />
            </div>
          </div>

          <div class="flex justify-between gap-2 pt-2">
            <button
              type="button"
              @click="limpiarFiltros"
              class="px-4 py-2 text-sm text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100"
            >
              Limpiar
            </button>
            <div class="flex gap-2">
              <button
                type="button"
                @click="mostrarModalFiltros = false"
                class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
              >
                Cancelar
              </button>
              <button
                type="submit"
                class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700"
              >
                Aplicar filtros
              </button>
            </div>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
