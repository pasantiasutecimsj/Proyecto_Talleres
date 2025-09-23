<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";

import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

// Reutilizamos el modal y el modal de filtros del Admin
import ClaseModal from "@/Pages/Admin/Clases/ClaseModal.vue";
import ClaseFiltrosModal from "@/Pages/Admin/Clases/ClaseFiltrosModal.vue";

const props = defineProps({
  clases:       { type: Array,  default: () => [] },  // [{ id, fecha_hora, asistentes_maximos, taller:{id,nombre}, docente:{ci,nombre?,apellido?} }]
  talleres:     { type: Array,  default: () => [] },  // talleres del organizador (para el modal)
  ciudades:     { type: Array,  default: () => [] },  // [{id,nombre}]
  organizadores:{ type: Array,  default: () => [] },  // [{ci, nombre?, apellido?}]
  filtros:      { type: Object, default: () => ({ organizador: "", q: "", taller: "", desde: "", hasta: "" }) },
});

/* =========================
   Estado UI
   ========================= */
const showModal = ref(false);
const editing   = ref(null);

const mostrarModalFiltros = ref(false);
const filtros = ref({
  organizador: props.filtros?.organizador ?? "",
  q:           props.filtros?.q ?? "",
  taller:      props.filtros?.taller ?? "",
  desde:       props.filtros?.desde ?? "",
  hasta:       props.filtros?.hasta ?? "",
});

const selectedOrganizador = ref(filtros.value.organizador || "");

/* =========================
   Helpers de UI
   ========================= */
const sortedOrganizadores = computed(() =>
  [...props.organizadores].sort((a, b) => {
    const an = [a.nombre, a.apellido, a.ci].filter(Boolean).join(" ").toLowerCase();
    const bn = [b.nombre, b.apellido, b.ci].filter(Boolean).join(" ").toLowerCase();
    return an.localeCompare(bn);
  })
);

const displayOrganizador = (ci) => {
  const o = props.organizadores.find(o => String(o.ci) === String(ci));
  if (!o) return ci || "";
  const nom = [o.nombre, o.apellido].filter(Boolean).join(" ");
  return nom ? `${nom} · ${o.ci}` : o.ci;
};

const fmtFechaHora = (isoLike) => {
  if (!isoLike) return "—";
  const d = new Date(isoLike);
  if (isNaN(d.getTime())) return isoLike;
  try {
    return new Intl.DateTimeFormat("es-UY", {
      year: "numeric", month: "2-digit", day: "2-digit",
      hour: "2-digit", minute: "2-digit",
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

/* =========================
   Navegación con filtros
   ========================= */
const navegar = () => {
  const params = {
    organizador: selectedOrganizador.value || filtros.value.organizador || undefined,
    q:      filtros.value.q?.trim() || undefined,
    taller: filtros.value.taller || undefined,
    desde:  filtros.value.desde || undefined,
    hasta:  filtros.value.hasta || undefined,
  };
  Object.keys(params).forEach(k => params[k] === undefined && delete params[k]);

  router.get(route("organizador.clases.index"), params, {
    preserveScroll: true,
    preserveState: false,
  });
};

// Cambiar organizador refresca inmediatamente
watch(selectedOrganizador, (ci) => {
  filtros.value.organizador = ci || "";
  navegar();
});

/* =========================
   Acciones ABM
   ========================= */
const openEdit = (clase) => {
  editing.value = clase;
  showModal.value = true;
};
const closeModal = () => {
  showModal.value = false;
  editing.value = null;
};
const handleSaved = () => {
  // El PUT/POST redirige/recarga desde el backend; cerramos modal por UX.
  closeModal();
};

/* =========================
   Acciones filtros
   ========================= */
const aplicarFiltros = () => {
  mostrarModalFiltros.value = false;
  navegar();
};
const limpiarFiltros = () => {
  filtros.value = { ...filtros.value, q: "", taller: "", desde: "", hasta: "" };
  navegar();
};
const limpiarOrganizador = () => {
  selectedOrganizador.value = ""; // el watcher navega
};
</script>

<template>
  <Head title="Clases por Organizador" />

  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Clases por Organizador
        </h2>
        <p class="text-sm text-gray-600">
          Seleccioná un organizador para ver y editar las clases de sus talleres.
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

        <!-- Selector de organizador + acciones -->
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div class="flex-1">
            <label for="sel-organizador" class="block text-sm font-medium text-gray-700">
              Organizador
            </label>
            <div class="mt-1 flex gap-2">
              <select
                id="sel-organizador"
                v-model="selectedOrganizador"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
              >
                <option value="">— Seleccioná un organizador —</option>
                <option v-for="o in sortedOrganizadores" :key="o.ci" :value="o.ci">
                  {{ [o.nombre, o.apellido].filter(Boolean).join(' ') || 'Sin nombre' }} · {{ o.ci }}
                </option>
              </select>

              <SecondaryButton
                v-if="selectedOrganizador"
                class="shrink-0"
                @click="limpiarOrganizador"
              >
                Limpiar
              </SecondaryButton>
            </div>
            <p class="text-xs text-gray-500 mt-1">
              Al cambiar el organizador, la lista se actualiza automáticamente.
            </p>
          </div>

          <div class="flex gap-2 self-start sm:self-auto">
            <PrimaryButton @click="() => (mostrarModalFiltros = true)">🔎︎ Filtros</PrimaryButton>
          </div>
        </div>

        <!-- Banner: filtros activos -->
        <div
          v-if="selectedOrganizador || filtros.q || filtros.taller || filtros.desde || filtros.hasta"
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
              v-if="selectedOrganizador"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs"
            >
              <strong class="font-semibold">Organizador:</strong>
              {{ displayOrganizador(selectedOrganizador) }}
            </span>

            <span v-if="filtros.q"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Docente/CI:</strong> {{ filtros.q }}
            </span>

            <span v-if="filtros.taller"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Taller:</strong> {{ filtros.taller }}
            </span>

            <span v-if="filtros.desde"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Desde:</strong> {{ filtros.desde }}
            </span>

            <span v-if="filtros.hasta"
              class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Hasta:</strong> {{ filtros.hasta }}
            </span>
          </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            <div class="overflow-x-auto">
              <p class="text-gray-600 mb-4">
                → Hacé clic en una fila para <strong>editar</strong> la clase.
              </p>

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
                      <span v-if="cl.taller"
                        class="inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-3 py-0.5 text-xs font-medium">
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

                  <tr v-if="props.clases.length === 0">
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                      No hay clases para mostrar con estos filtros.
                    </td>
                  </tr>
                </tbody>
              </table>

            </div>
          </div>
        </div>
        <!-- /Tabla -->
      </div>
    </div>

    <!-- Modal de Clase (reutilizado del Admin) -->
    <ClaseModal
      :show="showModal"
      :editing="editing"
      :talleres="props.talleres"
      @close="closeModal"
      @saved="handleSaved"
    />

    <!-- Modal de Filtros (reutilizado del Admin) -->
    <ClaseFiltrosModal
      :show="mostrarModalFiltros"
      :talleres="props.talleres"
      v-model:filtros="filtros"
      @close="mostrarModalFiltros = false"
      @apply="aplicarFiltros"
      @clear="limpiarFiltros"
    />
  </AuthenticatedLayout>
</template>
