<script setup>
import { ref, watch, computed } from "vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
  show: { type: Boolean, default: false },
  filtros: {
    type: Object,
    default: () => ({ busqueda: "", taller: "", nombre: "" }),
  },
  // Para el select de Taller
  talleres: { type: Array, default: () => [] }, // [{id, nombre}]
});

// v-model para `filtros` + eventos
const emit = defineEmits(["close", "update:filtros", "apply", "clear"]);

// Copia local para editar sin tocar el estado del padre hasta “Aplicar”
const localFiltros = ref({ busqueda: "", taller: "", nombre: "" });

// Sincronizar al abrir/cambiar (y al montar)
watch(
  () => props.show,
  (open) => {
    if (open) {
      localFiltros.value = {
        busqueda: props.filtros?.busqueda ?? "",
        taller: props.filtros?.taller ?? "",
        nombre: props.filtros?.nombre ?? "",
      };
    }
  },
  { immediate: true }
);

// Orden de talleres por nombre (opcional)
const sortedTalleres = computed(() =>
  [...props.talleres].sort((a, b) =>
    String(a.nombre ?? "").localeCompare(String(b.nombre ?? ""))
  )
);

const aplicar = () => {
  emit("update:filtros", { ...localFiltros.value });
  emit("apply");
  emit("close");
};

const limpiar = () => {
  localFiltros.value = { busqueda: "", taller: "", nombre: "" };
  emit("update:filtros", { ...localFiltros.value });
  emit("clear");
};
</script>

<template>
  <Modal :show="show" @close="() => emit('close')">
    <div class="p-6 w-full mx-auto overflow-x-hidden
           max-w-[min(28rem,calc(100vw-2rem))]">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h2>

      <form @submit.prevent="aplicar" class="space-y-4">
        <!-- Buscar por CI -->
        <div>
          <label for="f-busqueda" class="block text-sm font-medium text-gray-700">
            Buscar por CI
          </label>
          <input id="f-busqueda" v-model="localFiltros.busqueda" type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
            placeholder="Ej. 12345678" />
        </div>

        <!-- Buscar por Nombre / Apellido -->
        <div>
          <label for="f-nombre" class="block text-sm font-medium text-gray-700">
            Nombre o Apellido
          </label>
          <input id="f-nombre" v-model="localFiltros.nombre" type="text"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
            placeholder="Ej. María Pérez" />
          <p class="text-xs text-gray-500 mt-1">
            Filtra usando los datos provenientes de Registro de Personas (con cache).
          </p>
        </div>

        <!-- Taller -->
        <div>
          <label for="f-taller" class="block text-sm font-medium text-gray-700">Taller</label>
          <select id="f-taller" v-model="localFiltros.taller"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm">
            <option value="">Todos</option>
            <option v-for="t in sortedTalleres" :key="t.id" :value="t.id">
              {{ t.nombre }}
            </option>
          </select>
        </div>

        <div class="flex justify-between gap-2 pt-2">
          <button type="button" @click="limpiar"
            class="px-4 py-2 text-sm text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100">
            Limpiar
          </button>
          <div class="flex gap-2">
            <button type="button" @click="emit('close')"
              class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
              Cancelar
            </button>
            <button type="submit" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">
              Aplicar filtros
            </button>
          </div>
        </div>
      </form>
    </div>
  </Modal>
</template>
