<script setup>
import { ref, watch, computed } from "vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    // { organizador: string (CI), nombre: string, ciudad: string }
    filtros: {
        type: Object,
        default: () => ({ organizador: "", nombre: "", ciudad: "" }),
    },
    ciudades: { type: Array, default: () => [] },        // [{ id, nombre }]
    organizadores: { type: Array, default: () => [] },   // [{ ci, nombre?, apellido? }]
});

const emit = defineEmits(["close", "update:filtros", "apply", "clear"]);

// Copia local para editar sin tocar el estado del padre hasta “Aplicar”.
const localFiltros = ref({ organizador: "", nombre: "", ciudad: "" });

watch(
    () => props.show,
    (open) => {
        if (open) {
            localFiltros.value = {
                organizador: props.filtros?.organizador ?? "",
                nombre: props.filtros?.nombre ?? "",
                ciudad: props.filtros?.ciudad ?? "",
            };
        }
    },
    { immediate: true }
);

// Helpers UI
const sortedCiudades = computed(() =>
    [...props.ciudades].sort((a, b) =>
        String(a.nombre ?? "").localeCompare(String(b.nombre ?? ""))
    )
);

const sortedOrganizadores = computed(() =>
    [...props.organizadores].sort((a, b) => {
        const an = [a.nombre, a.apellido, a.ci].filter(Boolean).join(" ").toLowerCase();
        const bn = [b.nombre, b.apellido, b.ci].filter(Boolean).join(" ").toLowerCase();
        return an.localeCompare(bn);
    })
);

const displayOrganizador = (o) => {
    const nom = [o.nombre, o.apellido].filter(Boolean).join(" ");
    return nom ? `${nom} · ${o.ci}` : o.ci;
};

// Acciones
const aplicar = () => {
    emit("update:filtros", { ...localFiltros.value });
    emit("apply", { ...localFiltros.value });
};

const limpiar = () => {
    localFiltros.value = { organizador: "", nombre: "", ciudad: "" };
    emit("update:filtros", { ...localFiltros.value });
    emit("clear");
};
</script>

<template>
    <Modal :show="show" @close="() => emit('close')">
        <div class="p-6 w-full mx-auto overflow-x-hidden max-w-[min(28rem,calc(100vw-2rem))]">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h2>

            <form @submit.prevent="aplicar" class="space-y-4">
                <!-- Organizador -->
                <div>
                    <label for="f-organizador" class="block text-sm font-medium text-gray-700">
                        Organizador
                    </label>
                    <select id="f-organizador" v-model="localFiltros.organizador"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm">
                        <option value="">Todos</option>
                        <option v-for="o in sortedOrganizadores" :key="o.ci" :value="o.ci">
                            {{ displayOrganizador(o) }}
                        </option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        Filtra los talleres que organiza la persona seleccionada.
                    </p>
                </div>

                <!-- Nombre / Descripción -->
                <div>
                    <label for="f-nombre" class="block text-sm font-medium text-gray-700">
                        Nombre o Descripción
                    </label>
                    <input id="f-nombre" v-model="localFiltros.nombre" type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
                        placeholder="Ej. programación, cocina…" />
                </div>

                <!-- Ciudad -->
                <div>
                    <label for="f-ciudad" class="block text-sm font-medium text-gray-700">Ciudad</label>
                    <select id="f-ciudad" v-model="localFiltros.ciudad"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm">
                        <option value="">Todas</option>
                        <option v-for="c in sortedCiudades" :key="c.id" :value="c.id">
                            {{ c.nombre }}
                        </option>
                    </select>
                </div>

                <!-- Acciones -->
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
