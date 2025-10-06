<script setup>
import { computed } from "vue";
import Modal from "@/Components/Modal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

const props = defineProps({
  show:   { type: Boolean, default: false },
  taller: {
    type: Object,
    default: null, // { id, nombre, descripcion?, id_ciudad?, ciudad?, calle?, numero? }
  },
});

const emit = defineEmits(["close"]);
const close = () => emit("close");

const hasAddress = computed(() => {
  if (!props.taller) return false;
  return !!(props.taller.calle || props.taller.numero || props.taller.ciudad);
});

const addressLine = computed(() => {
  if (!props.taller) return "";
  const calle  = props.taller.calle  ?? "";
  const numero = props.taller.numero ?? "";
  const base   = [calle, numero].filter(Boolean).join(" ").trim();
  const ciudad = props.taller.ciudad ?? "";
  return [base, ciudad].filter(Boolean).join(" · ");
});

// Link opcional a Google Maps si hay dirección
const mapsUrl = computed(() => {
  if (!hasAddress.value) return null;
  const q = encodeURIComponent(addressLine.value);
  return `https://www.google.com/maps/search/?api=1&query=${q}`;
});
</script>

<template>
  <Modal :show="show" @close="close" maxWidth="lg">
    <div class="p-0">
      <!-- Header bonito -->
      <div class="flex items-center gap-3 rounded-t-lg bg-gradient-to-r from-indigo-600 to-sky-600 px-6 py-5 text-white">
        <div class="rounded-xl bg-white/10 p-2">
          <!-- Icono -->
          <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8 7v10m8-10v10M3 7h18M3 17h18M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="truncate text-lg font-semibold leading-tight">
            {{ taller?.nombre || 'Taller' }}
          </h2>
          <div class="mt-1 flex flex-wrap items-center gap-2">
            <span v-if="taller?.id"
                  class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 text-xs">
              <span class="opacity-90">ID</span> <span class="font-semibold">{{ taller.id }}</span>
            </span>
            <span v-if="taller?.ciudad"
                  class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 text-xs">
              <!-- pin -->
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 21s-6-5.686-6-10a6 6 0 1 1 12 0c0 4.314-6 10-6 10z"/>
                <circle cx="12" cy="11" r="2.5"/>
              </svg>
              {{ taller.ciudad }}
            </span>
          </div>
        </div>
      </div>

      <!-- Contenido -->
      <div class="space-y-5 p-6">
        <!-- Descripción -->
        <section class="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
          <h3 class="text-sm font-semibold text-gray-800">Descripción</h3>
          <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700"
             :class="{'italic text-gray-500': !taller?.descripcion}">
            {{ taller?.descripcion || 'Sin descripción registrada.' }}
          </p>
        </section>

        <!-- Ubicación -->
        <section class="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Ubicación</h3>
            <a v-if="mapsUrl"
               :href="mapsUrl"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
              Ver en Maps
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M7 17L17 7M7 7h10v10"/>
              </svg>
            </a>
          </div>

          <div class="mt-2 flex items-start gap-3">
            <div class="rounded-lg bg-gray-100 p-2 text-gray-600">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 21s-6-5.686-6-10a6 6 0 1 1 12 0c0 4.314-6 10-6 10z"/>
                <circle cx="12" cy="11" r="2.5"/>
              </svg>
            </div>

            <div class="min-w-0">
              <p v-if="hasAddress" class="truncate text-sm text-gray-800">
                {{ addressLine }}
              </p>
              <p v-else class="text-sm italic text-gray-500">
                Sin dirección registrada.
              </p>
            </div>
          </div>
        </section>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
        <SecondaryButton @click="close">Cerrar</SecondaryButton>
      </div>
    </div>
  </Modal>
</template>
