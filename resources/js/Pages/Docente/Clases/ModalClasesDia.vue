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
  emit('ver-taller', {
    id: clase.taller_id,
    nombre: clase.taller_nombre,
    descripcion: clase.taller_descripcion ?? null,
    id_ciudad: clase.taller_id_ciudad ?? null,
    ciudad: clase.taller_ciudad ?? null,
    calle: clase.taller_calle ?? null,
    numero: clase.taller_numero ?? null,
  })
}
</script>

<template>
  <Modal :show="show" @close="close" maxWidth="lg">
    <div class="p-0">
      <!-- Header con gradiente -->
      <div class="flex items-center justify-between gap-3 rounded-t-lg bg-gradient-to-r from-indigo-600 to-sky-600 px-6 py-5 text-white">
        <div class="flex items-center gap-3">
          <div class="rounded-xl bg-white/10 p-2">
            <!-- icono calendario -->
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v3m8-3v3M4 11h16M4 7h16M7 22h10a3 3 0 003-3V7a3 3 0 00-3-3H7A3 3 0 004 7v12a3 3 0 003 3z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <h2 class="truncate text-lg font-semibold leading-tight">Clases del día</h2>
            <div class="mt-1">
              <span class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-xs capitalize">
                {{ dayToLabel(day) || '—' }}
              </span>
            </div>
          </div>
        </div>

        <div class="shrink-0">
          <SecondaryButton @click="close" class="bg-white/10 text-white hover:bg-white/20">Cerrar</SecondaryButton>
        </div>
      </div>

      <!-- Contenido -->
      <div class="space-y-4 p-6">
        <!-- Estado vacío -->
        <div v-if="!sortedClases.length" class="rounded-xl border border-gray-200/70 bg-white p-6 text-center shadow-sm">
          <div class="mx-auto mb-3 w-10 rounded-lg bg-gray-100 p-2 text-gray-500">
            <svg class="mx-auto h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v3m8-3v3M4 11h16M4 7h16M7 22h10a3 3 0 003-3V7a3 3 0 00-3-3H7A3 3 0 004 7v12a3 3 0 003 3z"/>
            </svg>
          </div>
          <p class="text-sm text-gray-600">No hay clases programadas para este día.</p>
        </div>

        <!-- Lista de clases -->
        <div v-else class="rounded-xl border border-gray-200/70 bg-white shadow-sm">
          <ul role="list" class="divide-y divide-gray-200">
            <li
              v-for="c in sortedClases"
              :key="c.id"
              class="group flex flex-col gap-3 px-4 py-4 transition-colors hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between"
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
                    class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"
                    title="Clase futura"
                  >
                    Próxima
                  </span>
                </div>

                <div class="mt-1 truncate text-sm text-gray-700">
                  <span class="font-semibold text-gray-900">{{ c.taller_nombre }}</span>
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
            </li>
          </ul>
        </div>
      </div>
    </div>
  </Modal>
</template>
