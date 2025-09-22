<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Modal from "@/Components/Modal.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
  talleres: Array,
  ciudades: Array, // [{id, nombre, ...}]
});

// ====== Estado UI ======
const showModal = ref(false);
const editingTaller = ref(null);

// Filtros (modal)
const mostrarModalFiltros = ref(false);
const filtros = ref({
  nombre: "",        // busca en nombre y descripción
  ciudad: "",        // id_ciudad
});

// ====== Orden ciudades ======
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
const form = useForm({
  nombre: "",
  descripcion: "",
  id_ciudad: "",
  calle: "",
  numero: "",
});

const openModal = (taller = null) => {
  editingTaller.value = taller;
  if (taller) {
    form.nombre = taller.nombre ?? "";
    form.descripcion = taller.descripcion ?? "";
    form.id_ciudad = taller.id_ciudad ?? "";
    form.calle = taller.calle ?? "";
    form.numero = taller.numero ?? "";
  } else {
    form.reset();
  }
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingTaller.value = null;
  form.reset();
};

const submitForm = () => {
  if (editingTaller.value) {
    form.put(route("admin.talleres.update", editingTaller.value.id), {
      onSuccess: () => closeModal(),
    });
  } else {
    form.post(route("admin.talleres.store"), {
      onSuccess: () => closeModal(),
    });
  }
};

// ====== Filtros: acciones ======
const aplicarFiltros = () => {
  // (frontend) Solo cerramos el modal: filteredTalleres ya reacciona a `filtros`
  mostrarModalFiltros.value = false;
};

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
              {{ (props.ciudades.find(c => String(c.id) === String(filtros.ciudad))?.nombre) || filtros.ciudad }}
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
    <Modal :show="showModal" @close="closeModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">
          {{ editingTaller ? "Editar Taller" : "Nuevo Taller" }}
        </h2>

        <form @submit.prevent="submitForm" class="space-y-6">
          <!-- Ciudad -->
          <div>
            <InputLabel for="id_ciudad" value="Ciudad" />
            <select
              id="id_ciudad"
              v-model="form.id_ciudad"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              required
            >
              <option value="">Seleccioná una ciudad</option>
              <option v-for="ciudad in sortedCiudades" :key="ciudad.id" :value="ciudad.id">
                {{ ciudad.nombre }}
              </option>
            </select>
            <InputError :message="form.errors.id_ciudad" class="mt-2" />
          </div>

          <!-- Nombre -->
          <div>
            <InputLabel for="nombre" value="Nombre" />
            <TextInput
              id="nombre"
              v-model="form.nombre"
              type="text"
              class="mt-1 block w-full"
              placeholder="Ej: Taller de Programación"
              required
            />
            <InputError :message="form.errors.nombre" class="mt-2" />
          </div>

          <!-- Descripción -->
          <div>
            <InputLabel for="descripcion" value="Descripción" />
            <textarea
              id="descripcion"
              v-model="form.descripcion"
              rows="3"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="Descripción breve del taller"
            ></textarea>
            <InputError :message="form.errors.descripcion" class="mt-2" />
          </div>

          <!-- Calle / Número -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <InputLabel for="calle" value="Calle" />
              <TextInput
                id="calle"
                v-model="form.calle"
                type="text"
                class="mt-1 block w-full"
                placeholder="Ej: 18 de Julio"
              />
              <InputError :message="form.errors.calle" class="mt-2" />
            </div>
            <div>
              <InputLabel for="numero" value="Número" />
              <TextInput
                id="numero"
                v-model="form.numero"
                type="text"
                class="mt-1 block w-full"
                placeholder="Ej: 1234"
              />
              <InputError :message="form.errors.numero" class="mt-2" />
            </div>
          </div>

          <!-- Botones -->
          <div class="flex justify-end gap-3 mt-6">
            <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>
            <PrimaryButton
              type="submit"
              :disabled="form.processing || !form.id_ciudad || !form.nombre"
            >
              <span>
                {{
                  form.processing
                    ? (editingTaller ? "Actualizando..." : "Guardando...")
                    : (editingTaller ? "Actualizar" : "Guardar")
                }}
              </span>
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Modal Filtros -->
    <Modal :show="mostrarModalFiltros" @close="() => (mostrarModalFiltros.value = false)">
      <div class="p-6 w-full max-w-md">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h2>

        <form @submit.prevent="aplicarFiltros" class="space-y-4">
          <!-- Buscar por nombre/descripcion -->
          <div>
            <label for="f-nombre" class="block text-sm font-medium text-gray-700">
              Nombre o Descripción
            </label>
            <input
              id="f-nombre"
              v-model="filtros.nombre"
              type="text"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
              placeholder="Ej. programación, cocina..."
            />
          </div>

          <!-- Ciudad -->
          <div>
            <label for="f-ciudad" class="block text-sm font-medium text-gray-700">Ciudad</label>
            <select
              id="f-ciudad"
              v-model="filtros.ciudad"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
            >
              <option value="">Todas</option>
              <option v-for="c in sortedCiudades" :key="c.id" :value="c.id">
                {{ c.nombre }}
              </option>
            </select>
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
