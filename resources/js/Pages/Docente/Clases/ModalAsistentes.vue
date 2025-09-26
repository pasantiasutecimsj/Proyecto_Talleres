<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
  show:    { type: Boolean, default: false },
  claseId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close'])

/* ==================
   Estado principal
   ================== */
const loading = ref(false)
const error   = ref('')

const clase = ref(null)       // { id, fecha_hora, fecha_hora_iso, asistentes_maximos, taller_* , totales:{inscriptos,presentes} }
const asistentes = ref([])    // [{ ci, nombre|null, asistio: bool }]

/* Loading por fila de asistente (toggle) */
const rowBusy = ref(new Set())

/* =============
   Carga / fetch
   ============= */
async function fetchData() {
  if (!props.claseId) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get(`/docente/api/clases/${props.claseId}/asistentes`)
    clase.value = data?.clase ?? null
    asistentes.value = data?.asistentes ?? []
  } catch (e) {
    console.error(e)
    error.value = 'No se pudo cargar la información de la clase.'
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.show, props.claseId],
  ([open, id]) => {
    if (open && id) fetchData()
  },
  { immediate: true }
)

/* =========================
   Helpers de fecha / labels
   ========================= */
function parseDate(s) {
  return new Date(s || '')
}
const claseDate = computed(() => {
  const iso = clase.value?.fecha_hora_iso || clase.value?.fecha_hora
  return iso ? parseDate(iso) : null
})
const isPast = computed(() => {
  if (!claseDate.value) return false
  return claseDate.value.getTime() < Date.now()
})
function uyDateLabel(d) {
  if (!d) return ''
  return d.toLocaleString('es-UY', {
    timeZone: 'America/Montevideo',
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/* =====================
   Totales y derivados
   ===================== */
const totales = computed(() => clase.value?.totales ?? { inscriptos: 0, presentes: 0 })
const maximos = computed(() => clase.value?.asistentes_maximos ?? null)
const cupos = computed(() => {
  if (maximos.value == null) return null
  return Math.max(maximos.value - (totales.value?.inscriptos ?? 0), 0)
})

/* ===========================
   Toggle de asistencia (PATCH)
   =========================== */
async function toggleAsistencia(a) {
  const newVal = !a.asistio
  const ci = a.ci
  rowBusy.value.add(ci)

  try {
    const { data } = await axios.patch(
      `/docente/api/clases/${props.claseId}/asistentes/${ci}`,
      { asistio: newVal }
    )
    // Actualizamos fila y totales del backend
    a.asistio = !!data?.asistente?.asistio
    if (clase.value?.totales && data?.totales) {
      clase.value.totales = data.totales
    }
  } catch (e) {
    console.error(e)
    // revertir visual si falla
    error.value = 'No se pudo actualizar la asistencia. Intentá nuevamente.'
  } finally {
    rowBusy.value.delete(ci)
  }
}

/* =========
   Eventos
   ========= */
const close = () => emit('close')
const recargar = () => fetchData()
</script>

<template>
  <Modal :show="show" @close="close">
    <div class="p-6">
      <!-- Header -->
      <div class="mb-2">
        <h2 class="text-lg font-semibold text-gray-900">Asistentes</h2>
        <p v-if="claseDate" class="text-sm text-gray-600 capitalize">
          {{ uyDateLabel(claseDate) }}
        </p>
      </div>

      <!-- Info de la clase -->
      <div
        v-if="clase"
        class="mb-4 rounded-md border border-gray-200 bg-white p-4"
      >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div class="min-w-0">
            <div class="text-sm text-gray-700">
              <span class="font-medium">{{ clase.taller_nombre }}</span>
            </div>
            <div class="text-xs text-gray-500">
              {{ clase.taller_calle }} <span v-if="clase.taller_numero">{{ clase.taller_numero }}</span>
            </div>
          </div>

          <!-- Resumen dinámico -->
          <div class="text-sm text-gray-700">
            <template v-if="isPast">
              <span class="font-medium">Inscriptos:</span> {{ totales.inscriptos }}
              <span class="mx-2 text-gray-300">•</span>
              <span class="font-medium">Presentes:</span> {{ totales.presentes }}
            </template>
            <template v-else>
              <span class="font-medium">Inscriptos:</span> {{ totales.inscriptos }}
              <span class="mx-2 text-gray-300">•</span>
              <span class="font-medium">Cupos:</span>
              <span>
                <template v-if="cupos !== null">{{ cupos }}</template>
                <template v-else>—</template>
              </span>
            </template>
          </div>
        </div>
      </div>

      <!-- Loading / error -->
      <div v-if="loading" class="flex items-center gap-2 text-gray-600 mb-3">
        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v8z" />
        </svg>
        Cargando…
      </div>
      <div v-if="error" class="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        {{ error }}
      </div>

      <!-- Lista de asistentes -->
      <div v-if="!loading" class="rounded-md border border-gray-200 overflow-hidden">
        <template v-if="asistentes.length">
          <ul role="list" class="divide-y divide-gray-200">
            <li v-for="a in asistentes" :key="a.ci" class="p-3 sm:p-4">
              <div class="flex items-start justify-between gap-3">
                <!-- Datos -->
                <div class="min-w-0">
                  <div class="text-sm font-medium text-gray-900 truncate">
                    {{ a.nombre || '—' }}
                  </div>
                  <div class="text-xs text-gray-500">
                    CI: {{ a.ci }}
                  </div>
                </div>

                <!-- Toggle asistencia -->
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 select-none">
                  <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-azul focus:ring-azul"
                    :checked="a.asistio"
                    :disabled="rowBusy.has(a.ci)"
                    @change="toggleAsistencia(a)"
                  />
                  <span>
                    Asistió
                    <span v-if="rowBusy.has(a.ci)" class="ml-1 text-xs text-gray-400">(guardando…)</span>
                  </span>
                </label>
              </div>
            </li>
          </ul>
        </template>

        <div v-else class="p-6 text-sm text-gray-600">
          No hay asistentes inscriptos para esta clase.
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-5 flex items-center justify-end gap-2">
        <SecondaryButton @click="recargar">Recargar</SecondaryButton>
        <PrimaryButton @click="close">Cerrar</PrimaryButton>
      </div>
    </div>
  </Modal>
</template>
