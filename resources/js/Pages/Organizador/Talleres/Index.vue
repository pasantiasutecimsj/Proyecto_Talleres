<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";

import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

// Reutilizamos componentes del Admin
import TallerFiltrosModal from "@/Pages/Admin/Talleres/TallerFiltrosModal.vue";
import TallerModal from "@/Pages/Admin/Talleres/TallerModal.vue"; // 👈 para editar

const props = defineProps({
  talleres: { type: Array, default: () => [] },
  ciudades: { type: Array, default: () => [] },        // [{id, nombre}]
  organizadores: { type: Array, default: () => [] },   // [{ci, nombre?, apellido?}]
  filtros: { type: Object, default: () => ({ organizador: "", nombre: "", ciudad: "" }) },
});

/* =========================
   Estado UI / filtros
   ========================= */
const mostrarModalFiltros = ref(false);
const filtros = ref({
  organizador: props.filtros?.organizador ?? "",
  nombre:      props.filtros?.nombre ?? "",
  ciudad:      props.filtros?.ciudad ?? "",
});

const selectedOrganizador = ref(filtros.value.organizador || "");

// ====== Estado modal edición ======
const showModal = ref(false);
const editingTaller = ref(null);

const openModal = (t) => {
  editingTaller.value = t;
  showModal.value = true;
};
const closeModal = () => {
  showModal.value = false;
  editingTaller.value = null;
};
const handleSaved = () => {
  closeModal();
  // refresca manteniendo filtros actuales (organizador/nombre/ciudad)
  router.reload({ preserveScroll: true, preserveState: true });
};

// Ordenadas para UI
const sortedCiudades = computed(() =>
  [...props.ciudades].sort((a, b) => String(a.nombre ?? "").localeCompare(String(b.nombre ?? "")))
);
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

/* =========================
   Navegación con filtros
   ========================= */
const navegar = () => {
  const params = {
    // prioriza el valor del select; si está vacío usa el que ya tenía filtros
    organizador: selectedOrganizador.value || filtros.value.organizador || undefined,
    nombre: filtros.value.nombre?.trim() || undefined,
    ciudad: filtros.value.ciudad || undefined,
  };
  Object.keys(params).forEach(k => params[k] === undefined && delete params[k]);

  router.get(route("organizador.talleres.index"), params, {
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
   Filtro frontal (nombre/ciudad)
   ========================= */
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

/* =========================
   Acciones UI
   ========================= */
const limpiarOrganizador = () => {
  selectedOrganizador.value = ""; // el watcher hace navegar()
};

const aplicarFiltros = (payload) => {
  // Mezcla sin perder el organizador actual
  if (payload) filtros.value = { ...filtros.value, ...payload };
  mostrarModalFiltros.value = false; // cerrar primero
  navegar();                         // luego navegar
};

const limpiarFiltros = () => {
  filtros.value = { ...filtros.value, nombre: "", ciudad: "" };
  navegar();
};
</script>

<template>
  <Head title="Talleres por Organizador" />

  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Talleres por Organizador
        </h2>
        <p class="text-sm text-gray-600">
          Seleccioná un organizador para ver los talleres que organiza. Además, podés filtrar por nombre/ciudad.
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

        <!-- Fila superior: selector de organizador + acciones -->
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

        <!-- Banner: Filtros activos -->
        <div
          v-if="selectedOrganizador || filtros.nombre || filtros.ciudad"
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
              Limpiar filtros (nombre/ciudad)
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
            <div class="overflow-x-auto">
              <p class="text-gray-600 mb-4">
                → Seleccioná un organizador para ver sus talleres. Podés filtrar por nombre/ciudad.
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
                    v-for="t in filteredTalleres"
                    :key="t.id"
                    class="hover:bg-gray-50 transition-colors cursor-pointer"
                    @click="openModal(t)"
                  >
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ t.ciudad || '—' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ t.nombre }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                      <span class="line-clamp-2">{{ t.descripcion || '—' }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ [t.calle, t.numero].filter(Boolean).join(' ') || '—' }}
                    </td>
                  </tr>

                  <tr v-if="filteredTalleres.length === 0">
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                      No hay talleres para mostrar con estos filtros.
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

    <!-- Modal Filtros (reutilizado) -->
    <TallerFiltrosModal
      :show="mostrarModalFiltros"
      v-model:filtros="filtros"
      :ciudades="props.ciudades"
      @close="mostrarModalFiltros = false"
      @update:filtros="(f) => { filtros.value = { ...filtros.value, ...f }; }"
    />

    <!-- Modal editar (reutilizado del Admin) -->
    <TallerModal
      :show="showModal"
      :editing="editingTaller"
      :ciudades="props.ciudades"
      @close="closeModal"
      @saved="handleSaved"
    />
  </AuthenticatedLayout>
</template>
