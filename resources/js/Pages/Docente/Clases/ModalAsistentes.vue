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
const presentRate = computed(() => {
  const i = totales.value?.inscriptos ?? 0
  const p = totales.value?.presentes ?? 0
  if (!i) return 0
  return Math.round((p / i) * 100)
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
  <Modal :show="show" @close="close" maxWidth="lg">
    <div class="p-0">
      <!-- Header con gradiente -->
      <div class="flex items-center justify-between gap-3 rounded-t-lg bg-gradient-to-r from-indigo-600 to-sky-600 px-6 py-5 text-white">
        <div class="flex items-center gap-3">
          <div class="rounded-xl bg-white/10 p-2">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8 7v10m8-10v10M3 7h18M3 17h18M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h2 class="truncate text-lg font-semibold leading-tight">Asistentes</h2>
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <span v-if="claseDate" class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-xs">
                {{ uyDateLabel(claseDate) }}
              </span>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs"
                    :class="isPast ? 'bg-white/15' : 'bg-green-400/20 text-white'">
                {{ isPast ? 'Clase pasada' : 'Próxima' }}
              </span>
            </div>
          </div>
        </div>

        <div class="shrink-0">
          <SecondaryButton @click="close" class="bg-white/10 text-white hover:bg-white/20">Cerrar</SecondaryButton>
        </div>
      </div>

      <div class="space-y-5 p-6">
        <!-- Info de la clase -->
        <section v-if="clase" class="rounded-xl border border-gray-200/70 bg-white p-4 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
              <div class="text-sm text-gray-700">
                <span class="font-semibold text-gray-900">{{ clase.taller_nombre }}</span>
              </div>
              <div class="text-xs text-gray-500">
                {{ clase.taller_calle }} <span v-if="clase.taller_numero">{{ clase.taller_numero }}</span>
              </div>
            </div>

            <!-- Resumen dinámico (chips) -->
            <div class="flex flex-wrap items-center gap-2 text-sm">
              <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-gray-800">
                Inscriptos: <span class="ml-1 font-semibold">{{ totales.inscriptos }}</span>
              </span>

              <template v-if="isPast">
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-indigo-700">
                  Presentes: <span class="ml-1 font-semibold">{{ totales.presentes }}</span>
                </span>
                <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-sky-700" v-if="totales.inscriptos">
                  Tasa: <span class="ml-1 font-semibold">{{ presentRate }}%</span>
                </span>
              </template>

              <template v-else>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-emerald-700">
                  Cupos: <span class="ml-1 font-semibold">{{ cupos ?? '—' }}</span>
                </span>
              </template>
            </div>
          </div>
        </section>

        <!-- Loading / error -->
        <div v-if="loading" class="flex items-center gap-2 text-gray-600">
          <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v8z" />
          </svg>
          Cargando…
        </div>
        <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
          {{ error }}
        </div>

        <!-- Lista de asistentes -->
        <section v-if="!loading" class="rounded-xl border border-gray-200/70 bg-white shadow-sm">
          <template v-if="asistentes.length">
            <ul role="list" class="divide-y divide-gray-200">
              <li v-for="a in asistentes" :key="a.ci"
                  class="group flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-gray-50">
                <!-- Datos -->
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-medium text-gray-900">
                      {{ a.nombre || '—' }}
                    </span>

                    <!-- Chip presente -->
                    <span v-if="a.asistio"
                          class="hidden rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 sm:inline-block">
                      Presente
                    </span>
                  </div>
                  <div class="text-xs text-gray-500">
                    CI: {{ a.ci }}
                  </div>
                </div>

                <!-- Toggle asistencia -->
                <label class="inline-flex select-none items-center gap-2 text-sm text-gray-700">
                  <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                    :checked="a.asistio"
                    :disabled="rowBusy.has(a.ci)"
                    @change="toggleAsistencia(a)"
                  />
                  <span class="flex items-center">
                    Asistió
                    <svg v-if="rowBusy.has(a.ci)" class="ml-2 h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v8z" />
                    </svg>
                  </span>
                </label>
              </li>
            </ul>
          </template>

          <div v-else class="px-6 py-10 text-center">
            <div class="mx-auto mb-3 w-10 rounded-lg bg-gray-100 p-2 text-gray-500">
              <svg class="mx-auto h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5v14"/>
              </svg>
            </div>
            <p class="text-sm text-gray-600">No hay asistentes inscriptos para esta clase.</p>
          </div>
        </section>

        <!-- Acciones -->
        <div class="mt-2 flex items-center justify-end gap-2">
          <SecondaryButton @click="recargar">Recargar</SecondaryButton>
          <PrimaryButton @click="close">Cerrar</PrimaryButton>
        </div>
      </div>
    </div>
  </Modal>
</template>
