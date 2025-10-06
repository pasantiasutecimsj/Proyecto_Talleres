<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";

import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Modal from "@/Components/Modal.vue";
import ClasesDayModal from "@/Pages/Docente/Clases/ModalClasesDia.vue";
import ModalAsistentes from "@/Pages/Docente/Clases/ModalAsistentes.vue";
import ModalTallerDetalle from "@/Pages/Docente/Clases/ModalTallerDetalle.vue";

/* =========================
   Estado principal
   ========================= */
const docentes = ref([]);
const selectedCi = ref(null);

const loadingDocentes = ref(false);
const loadingClases = ref(false);
const error = ref(null);

const today = new Date();
const todayKey = today.toLocaleDateString("en-CA", {
  timeZone: "America/Montevideo",
});

const viewYear = ref(today.getFullYear());
const viewMonth = ref(today.getMonth());

const clasesRaw = ref([]);
const clasesByDate = ref({});

// cache por docente+anchor (YYYY-MM) -> array de clases
const cache = new Map();

const selectedDay = ref(null);
const showDayModal = ref(false);

// (placeholder hasta crear el modal de Asistentes)
const showAsistentesModal = ref(false);
const claseSeleccionada = ref(null);

// Modal "Ver taller"
const showTallerModal = ref(false);
const tallerDetail = ref(null);
/* =========================
   Helpers de fecha/keys
   ========================= */
function dateKey(d) {
  return d.toLocaleDateString("en-CA", { timeZone: "America/Montevideo" });
}
function hasClasesByKey(k) {
  const map = clasesByDate.value || {};
  return (map[k] ?? []).length > 0;
}
function hasClasesDate(d) {
  return hasClasesByKey(dateKey(d));
}
function isoToLocalDateKey(iso) {
  const d = new Date(iso);
  return d.toLocaleDateString("en-CA", { timeZone: "America/Montevideo" });
}
function isoToLocalTime(iso) {
  const d = new Date(iso);
  return d.toLocaleTimeString("es-UY", {
    timeZone: "America/Montevideo",
    hour: "2-digit",
    minute: "2-digit",
  });
}
function monthLabel(year, month) {
  const dt = new Date(year, month, 1);
  return dt.toLocaleDateString("es-UY", { month: "long", year: "numeric" });
}
function calendarMatrix(year, month) {
  const first = new Date(year, month, 1);
  const firstWeekdayMonStart = (first.getDay() + 6) % 7; // semana inicia lunes
  const start = new Date(year, month, 1 - firstWeekdayMonStart);
  const cells = [];
  for (let i = 0; i < 42; i++) {
    const d = new Date(
      start.getFullYear(),
      start.getMonth(),
      start.getDate() + i
    );
    cells.push(d);
  }
  return cells;
}
const matrix = computed(() => calendarMatrix(viewYear.value, viewMonth.value));

function groupClasesByDate(raw) {
  const map = {};
  for (const c of raw) {
    const key = isoToLocalDateKey(c.fecha_hora_iso || c.fecha_hora);
    if (!map[key]) map[key] = [];
    map[key].push(c);
  }
  for (const k of Object.keys(map)) {
    map[k].sort(
      (a, b) =>
        new Date(a.fecha_hora_iso || a.fecha_hora) -
        new Date(b.fecha_hora_iso || b.fecha_hora)
    );
  }
  return map;
}

const closestUpcoming = computed(() => {
  const now = new Date();
  let best = null;
  for (const c of clasesRaw.value) {
    const dt = new Date(c.fecha_hora_iso || c.fecha_hora);
    if (dt >= now) {
      if (best === null || dt < new Date(best.fecha_hora_iso || best.fecha_hora)) {
        best = c;
      }
    }
  }
  return best;
});

/* =========================
   Carga de datos
   ========================= */
async function loadDocentes() {
  loadingDocentes.value = true;
  error.value = null;
  try {
    const resp = await axios.get("/docente/api/docentes");
    docentes.value = resp.data?.data ?? [];
    if (!selectedCi.value && docentes.value.length > 0) {
      selectedCi.value = docentes.value[0].ci;
    }
  } catch (e) {
    console.error(e);
    error.value = "No se pudieron cargar los docentes.";
  } finally {
    loadingDocentes.value = false;
  }
}

// Anchor YYYY-MM basado en mes visible
function currentAnchorYYYYMM() {
  const pad = (n) => String(n).padStart(2, "0");
  return `${viewYear.value}-${pad(viewMonth.value + 1)}`;
}

async function loadClasesForSelected() {
  if (!selectedCi.value) return;
  loadingClases.value = true;
  error.value = null;
  try {
    const anchor = currentAnchorYYYYMM();
    const key = `${selectedCi.value}|${anchor}`;

    if (cache.has(key)) {
      clasesRaw.value = cache.get(key);
    } else {
      const url = `/docente/api/docentes/${selectedCi.value}/clases?anchor=${anchor}`;
      const resp = await axios.get(url);
      clasesRaw.value = resp.data?.clases ?? [];
      cache.set(key, clasesRaw.value);
    }

    clasesByDate.value = groupClasesByDate(clasesRaw.value);
  } catch (e) {
    console.error(e);
    error.value = "No se pudieron cargar las clases.";
  } finally {
    loadingClases.value = false;
  }
}

watch([selectedCi, viewYear, viewMonth], () => loadClasesForSelected());

/* =========================
   Navegación calendario
   ========================= */
function prevMonth() {
  if (viewMonth.value === 0) {
    viewMonth.value = 11;
    viewYear.value -= 1;
  } else viewMonth.value -= 1;
}
function nextMonth() {
  if (viewMonth.value === 11) {
    viewMonth.value = 0;
    viewYear.value += 1;
  } else viewMonth.value += 1;
}
function goToday() {
  viewYear.value = today.getFullYear();
  viewMonth.value = today.getMonth();
}

/* =========================
   Interacciones UI
   ========================= */
function onClickDay(dateObj) {
  const key = dateKey(dateObj);
  // abrimos modal solo si hay clases ese día
  if (!hasClasesByKey(key)) return;
  selectedDay.value = key;
  showDayModal.value = true;
}

function openTodayModal() {
  // abre el modal del día de hoy (aunque no haya clases)
  selectedDay.value = todayKey;
  showDayModal.value = true;
}

function dateHasFuture(dateKeyStr) {
  const clases = clasesByDate.value[dateKeyStr] ?? [];
  const now = new Date();
  return clases.some(
    (c) => new Date(c.fecha_hora_iso || c.fecha_hora) >= now
  );
}
function isClosestUpcomingDate(dateKeyStr) {
  if (!closestUpcoming.value) return false;
  const k = isoToLocalDateKey(
    closestUpcoming.value.fecha_hora_iso || closestUpcoming.value.fecha_hora
  );
  return k === dateKeyStr;
}

/* =========================
   UI derivados (chips/banner)
   ========================= */
const selectedDocenteLabel = computed(() => {
  const d = docentes.value.find((x) => x.ci === selectedCi.value);
  if (!d) return selectedCi.value || "—";
  return d.nombre ? `${d.nombre} · ${d.ci}` : d.ci;
});
const visibleMonthLabel = computed(() => monthLabel(viewYear.value, viewMonth.value));

/* =========================
   Flujo "ver-clase" (placeholder)
   ========================= */
function openClase(clase) {
  // Dejamos seleccionado y abrimos modal placeholder.
  // Próximo paso: reemplazar por ModalAsistentes.vue
  claseSeleccionada.value = clase;
  showAsistentesModal.value = true;
}

onMounted(() => {
  loadDocentes();
  loadClasesForSelected();
});

function openTaller(taller) {
  tallerDetail.value = taller;
  showTallerModal.value = true;
}
</script>

<template>

  <Head title="Mis clases (Docente)" />

  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Mis clases (Docente)
        </h2>
        <p class="text-sm text-gray-600">
          Calendario mensual. Elegí el docente, navegá por mes y abrí un día para ver las clases.
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Header con acciones -->
        <div class="mb-6 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">
              Calendario del Docente
            </h3>
            <p class="text-sm text-gray-600">
              Vista mensual y acceso rápido a las clases del día seleccionado.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <!-- Navegación de mes -->
            <SecondaryButton @click="prevMonth">‹</SecondaryButton>
            <div class="px-3 text-sm font-medium text-gray-700">
              {{ visibleMonthLabel }}
            </div>
            <SecondaryButton @click="nextMonth">›</SecondaryButton>
            <PrimaryButton @click="goToday">Hoy</PrimaryButton>
            <SecondaryButton @click="openTodayModal">Clases de hoy</SecondaryButton>

            <!-- Selector de docente -->
            <div class="ml-2 flex items-center gap-2">
              <label class="text-sm font-medium hidden sm:block">Docente</label>
              <select v-model="selectedCi"
                class="block w-64 rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm">
                <option v-if="loadingDocentes" disabled>Cargando docentes...</option>
                <option v-for="d in docentes" :key="d.ci" :value="d.ci">
                  {{ d.nombre ? `${d.nombre} · ${d.ci}` : d.ci }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Banner: selección activa -->
        <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" class="h-5 w-5 text-gray-600"
                fill="currentColor" aria-hidden="true">
                <path
                  d="M480-120q-75 0-140.5-28.5T226-226q-49-49-77.5-114.5T120-480q0-75 28.5-140.5T226-734q49-49 114.5-77.5T480-840q75 0 140.5 28.5T734-734q49 49 77.5 114.5T840-480q0 75-28.5 140.5T734-226q-49 49-114.5 77.5T480-120Zm0-60q135 0 232.5-97.5T810-480q0-135-97.5-232.5T480-810q-135 0-232.5 97.5T150-480q0 135 97.5 232.5T480-180Zm0-420q-17 0-28.5-11.5T440-640q0-17 11.5-28.5T480-680q17 0 28.5 11.5T520-640q0 17-11.5 28.5T480-600Zm-40 320h80v-240h-80v240Z" />
              </svg>
              <span class="text-sm font-medium text-gray-700">Selección actual</span>
            </div>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Docente:</strong> {{ selectedDocenteLabel }}
            </span>

            <span class="inline-flex items-center gap-1 rounded-full bg-gray-200 text-gray-800 px-2.5 py-0.5 text-xs">
              <strong class="font-semibold">Mes visible:</strong> {{ visibleMonthLabel }}
            </span>
          </div>
        </div>

        <!-- Loading / error -->
        <div v-if="loadingClases" class="flex justify-center py-8">
          <svg class="animate-spin h-6 w-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24" aria-label="Cargando calendario">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a 8 8 0 0 1 8-8v8z" />
          </svg>
        </div>
        <div v-if="error" class="text-red-600 text-sm mb-3">{{ error }}</div>

        <!-- Card calendario -->
        <div v-else class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-4 text-gray-900">
            <!-- Head de semana -->
            <div class="grid grid-cols-7 text-xs text-gray-500">
              <div class="text-center py-2">Lun</div>
              <div class="text-center py-2">Mar</div>
              <div class="text-center py-2">Mié</div>
              <div class="text-center py-2">Jue</div>
              <div class="text-center py-2">Vie</div>
              <div class="text-center py-2">Sáb</div>
              <div class="text-center py-2">Dom</div>
            </div>

            <!-- Celdas -->
            <div class="grid grid-cols-7 gap-1 mt-1 overflow-visible relative">
              <template v-for="(dateObj, idx) in matrix" :key="idx">
                <div class="min-h-[64px] md:min-h-[84px] p-2 rounded-lg relative transition-colors" :class="{
                  'opacity-60': dateObj.getMonth() !== viewMonth,
                  'cursor-pointer hover:bg-azul-claro/10': hasClasesDate(dateObj),
                  'bg-gray-100 ring-2 ring-gray-300': dateKey(dateObj) === todayKey,
                  'ring-2 ring-verde-500': dateHasFuture(dateKey(dateObj)),
                  'pulse': isClosestUpcomingDate(dateKey(dateObj)),
                }" :role="hasClasesDate(dateObj) ? 'button' : undefined" :tabindex="hasClasesDate(dateObj) ? 0 : -1"
                  @keydown.enter.space="hasClasesDate(dateObj) && onClickDay(dateObj)"
                  @click.stop="hasClasesDate(dateObj) && onClickDay(dateObj)">
                  <div class="flex items-start justify-between">
                    <div class="text-sm font-medium" :class="{ 'text-gray-400': dateObj.getMonth() !== viewMonth }">
                      {{ dateObj.getDate() }}
                    </div>

                    <!-- Badge con cantidad de clases -->
                    <div v-if="hasClasesDate(dateObj)"
                      class="ml-2 inline-flex items-center justify-center text-xs font-semibold px-2 py-1 rounded-full bg-verde-100 text-verde-500">
                      {{ (clasesByDate[dateKey(dateObj)] ?? []).length }}
                    </div>
                  </div>

                  <!-- Breve resumen de clases -->
                  <div class="mt-2 text-xs text-gray-600 hidden sm:block">
                    <template v-for="(c, i) in (clasesByDate[dateKey(dateObj)] ?? []).slice(0, 2)" :key="c.id">
                      <div class="truncate">
                        <span class="font-medium">
                          {{ isoToLocalTime(c.fecha_hora_iso || c.fecha_hora) }}
                        </span>
                        <span class="ml-1 truncate">· {{ c.taller_nombre }}</span>
                      </div>
                    </template>
                    <div v-if="(clasesByDate[dateKey(dateObj)] ?? []).length > 2" class="text-xs text-gray-400">
                      + más...
                    </div>
                  </div>

                  <!-- Punto verde en esquina para pendiente -->
                  <div v-if="dateHasFuture(dateKey(dateObj))"
                    class="absolute bottom-1 right-1 w-3 h-3 rounded-full bg-verde-500" />
                </div>
              </template>
            </div>
          </div>
        </div>
        <!-- /Card calendario -->
      </div>
    </div>

    <!-- Modal: clases del día -->
    <ClasesDayModal :show="showDayModal" :day="selectedDay" :clases="(clasesByDate[selectedDay] ?? [])"
      @close="showDayModal = false" @ver-clase="openClase" @ver-taller="openTaller" />

    <!-- Placeholder hasta ModalAsistentes.vue -->
    <ModalAsistentes :show="showAsistentesModal" :claseId="claseSeleccionada?.id || null"
      @close="() => { showAsistentesModal = false; claseSeleccionada = null }" />

    <ModalTallerDetalle :show="showTallerModal" :taller="tallerDetail" @close="showTallerModal = false" />
  </AuthenticatedLayout>
</template>

<style scoped>
@keyframes pulse {

  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }

  50% {
    transform: scale(1.1);
    opacity: 1;
  }
}

.pulse {
  animation: pulse 1.5s infinite;
  background-color: #ffffff;
  z-index: 1;
}

.bg-verde-500 {
  background-color: #22c55e;
}

.text-verde-500 {
  color: #22c55e;
}

.ring-verde-500 {
  border-color: #22c55e;
}

.bg-verde-100 {
  background-color: #dcfce7;
}
</style>
