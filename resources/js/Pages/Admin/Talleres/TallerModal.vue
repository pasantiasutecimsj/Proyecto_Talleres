<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
  show: { type: Boolean, default: false },
  editing: { type: Object, default: null }, // { id, nombre, descripcion, id_ciudad, calle, numero, ... }
  ciudades: { type: Array, default: () => [] }, // catálogo para el select
});

const emit = defineEmits(["close", "saved"]);

const form = useForm({
  nombre: "",
  descripcion: "",
  id_ciudad: "",
  calle: "",
  numero: "",
});

// Orden de ciudades para el select
const sortedCiudades = computed(() =>
  [...props.ciudades].sort((a, b) => a.nombre.localeCompare(b.nombre))
);

// Prefill on open / editing changes
watch(
  () => props.editing,
  (t) => {
    if (t) {
      form.nombre = t.nombre ?? "";
      form.descripcion = t.descripcion ?? "";
      form.id_ciudad = t.id_ciudad ?? "";
      form.calle = t.calle ?? "";
      form.numero = t.numero ?? "";
    } else {
      form.reset();
    }
  },
  { immediate: true }
);

const close = () => emit("close");

const submit = () => {
  if (props.editing?.id) {
    form.put(route("admin.talleres.update", props.editing.id), {
      preserveScroll: true,
      onSuccess: () => {
        emit("saved");
        close();
      },
    });
  } else {
    form.post(route("admin.talleres.store"), {
      preserveScroll: true,
      onSuccess: () => {
        emit("saved");
        close();
      },
    });
  }
};
</script>

<template>
  <Modal :show="show" @close="close">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-4">
        {{ editing ? "Editar Taller" : "Nuevo Taller" }}
      </h2>

      <form @submit.prevent="submit" class="space-y-6">
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
          <SecondaryButton @click="close">Cancelar</SecondaryButton>
          <PrimaryButton
            type="submit"
            :disabled="form.processing || !form.id_ciudad || !form.nombre"
          >
            <span>
              {{
                form.processing
                  ? (editing ? "Actualizando..." : "Guardando...")
                  : (editing ? "Actualizar" : "Guardar")
              }}
            </span>
          </PrimaryButton>
        </div>
      </form>
    </div>
  </Modal>
</template>
