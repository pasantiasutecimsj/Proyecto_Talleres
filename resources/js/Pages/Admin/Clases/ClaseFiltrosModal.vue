<script setup>
import { ref, watch } from "vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    filtros: {
        type: Object,
        default: () => ({ q: "", taller: "", desde: "", hasta: "" }),
    },
    talleres: { type: Array, default: () => [] }, // [{id, nombre}]
});

const emit = defineEmits(["close", "update:filtros", "apply", "clear"]);

const localFiltros = ref({ q: "", taller: "", desde: "", hasta: "" });

// Sincronizar al abrir
watch(
    () => props.show,
    (open) => {
        if (open) {
            localFiltros.value = {
                q: props.filtros?.q ?? "",
                taller: props.filtros?.taller ?? "",
                desde: props.filtros?.desde ?? "",
                hasta: props.filtros?.hasta ?? "",
            };
        }
    },
    { immediate: true }
);

const aplicar = () => {
    emit("update:filtros", { ...localFiltros.value });
    emit("apply");
    emit("close");
};

const limpiar = () => {
    localFiltros.value = { q: "", taller: "", desde: "", hasta: "" };
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
                <!-- q: CI o nombre del docente -->
                <div>
                    <label for="f-q" class="block text-sm font-medium text-gray-700">
                        Docente (CI o Nombre)
                    </label>
                    <input id="f-q" v-model="localFiltros.q" type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
                        placeholder="Ej. 12345678 o María Pérez" />
                </div>

                <!-- Taller -->
                <div>
                    <label for="f-taller" class="block text-sm font-medium text-gray-700">Taller</label>
                    <select id="f-taller" v-model="localFiltros.taller"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm">
                        <option value="">Todos</option>
                        <option v-for="t in talleres" :key="t.id" :value="t.id">
                            {{ t.nombre }}
                        </option>
                    </select>
                </div>

                <!-- Rango de fechas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="f-desde" class="block text-sm font-medium text-gray-700">Desde</label>
                        <input id="f-desde" v-model="localFiltros.desde" type="date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm" />
                    </div>
                    <div>
                        <label for="f-hasta" class="block text-sm font-medium text-gray-700">Hasta</label>
                        <input id="f-hasta" v-model="localFiltros.hasta" type="date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm" />
                    </div>
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
                        <button type="submit"
                            class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Aplicar filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </Modal>
</template>
