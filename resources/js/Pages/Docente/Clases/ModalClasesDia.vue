<script setup>
import { computed } from 'vue'

import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
  show:   { type: Boolean, default: false },
  day:    { type: String,  default: '' }, // YYYY-MM-DD
  clases: { type: Array,   default: () => [] }, // [{ id, fecha_hora(_iso), taller_* }]
})

const emit = defineEmits(['close', 'ver-taller', 'ver-clase'])

/* =========================
   Helpers de fecha/labels
   ========================= */
function parseIso(isoOrStr) {
  // Preferimos ISO si está disponible
  const s = typeof isoOrStr === 'string' ? isoOrStr : ''
  return new Date(s)
}

function toUyTime(isoOrStr) {
  const d = parseIso(isoOrStr)
  return d.toLocaleTimeString('es-UY', {
    timeZone: 'America/Montevideo',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function dayToLabel(dayStr) {
  if (!dayStr) return ''
  // Evitamos desfases de TZ forzando -03:00
  const d = new Date(`${dayStr}T00:00:00-03:00`)
  return d.toLocaleDateString('es-UY', {
    timeZone: 'America/Montevideo',
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

function isPast(isoOrStr) {
  const now = new Date()
  const d   = parseIso(isoOrStr)
  return d.getTime() < now.getTime()
}

/* =========================
   Datos derivados / orden
   ========================= */
const sortedClases = computed(() => {
  const items = Array.isArray(props.clases) ? [...props.clases] : []
  return items.sort((a, b) => {
    const da = parseIso(a.fecha_hora_iso || a.fecha_hora).getTime()
    const db = parseIso(b.fecha_hora_iso || b.fecha_hora).getTime()
    return da - db
  })
})

/* ==============
   Acciones/emit
   ============== */
const close = () => emit('close')

function verAsistentes(clase) {
  // El padre abrirá el modal de asistentes con este id
  emit('ver-clase', {
    id: clase.id,
    fecha_hora: clase.fecha_hora,
    fecha_hora_iso: clase.fecha_hora_iso,
    taller_id: clase.taller_id,
    taller_nombre: clase.taller_nombre,
    taller_calle: clase.taller_calle,
    taller_numero: clase.taller_numero,
  })
}

function verTaller(clase) {
  // Compatibilidad con el index.vue actual (@ver-taller="openTaller")
  emit('ver-taller', clase)
}
</script>

<template>
  <Modal :show="show" @close="close">
    <div class="p-6">
      <!-- Header -->
      <h2 class="text-lg font-medium text-gray-900 mb-1">
        Clases del día
      </h2>
      <p class="text-sm text-gray-600 mb-4 capitalize">
        {{ dayToLabel(day) }}
      </p>

      <!-- Estado vacío -->
      <div
        v-if="!sortedClases.length"
        class="rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600"
      >
        No hay clases programadas para este día.
      </div>

      <!-- Lista de clases -->
      <div v-else class="divide-y divide-gray-200 rounded-md border border-gray-200">
        <div
          v-for="c in sortedClases"
          :key="c.id"
          class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <!-- Info principal -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <div class="text-base font-semibold text-gray-900">
                {{ toUyTime(c.fecha_hora_iso || c.fecha_hora) }}
              </div>

              <!-- Chip pasado/futuro -->
              <span
                v-if="isPast(c.fecha_hora_iso || c.fecha_hora)"
                class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700"
                title="Clase ya realizada"
              >
                Pasada
              </span>
              <span
                v-else
                class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"
                title="Clase futura"
              >
                Próxima
              </span>
            </div>

            <div class="mt-1 text-sm text-gray-700 truncate">
              <span class="font-medium">{{ c.taller_nombre }}</span>
            </div>
            <div v-if="c.taller_calle || c.taller_numero" class="text-xs text-gray-500">
              {{ c.taller_calle }} <span v-if="c.taller_numero">{{ c.taller_numero }}</span>
            </div>
          </div>

          <!-- Acciones -->
          <div class="flex shrink-0 items-center gap-2">
            <SecondaryButton @click="verTaller(c)">Ver taller</SecondaryButton>
            <PrimaryButton @click="verAsistentes(c)">Ver asistentes</PrimaryButton>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-5 flex justify-end">
        <SecondaryButton @click="close">Cerrar</SecondaryButton>
      </div>
    </div>
  </Modal>
</template>
