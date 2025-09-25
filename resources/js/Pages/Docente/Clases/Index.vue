<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";

import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Modal from "@/Components/Modal.vue";

const docentes = ref([]);
const selectedCi = ref(null);

const loadingDocentes = ref(false);
const loadingClases = ref(false);
const error = ref(null);

const today = new Date();
const todayKey = today.toLocaleDateString("en-CA", { timeZone: "America/Montevideo" });

const viewYear = ref(today.getFullYear());
const viewMonth = ref(today.getMonth());

const clasesRaw = ref([]);
const clasesByDate = ref({});

// cache por docente+anchor (YYYY-MM) -> array de clases
const cache = new Map();

const selectedDay = ref(null);
const showDayModal = ref(false);
const showTallerModal = ref(false);
const tallerDetail = ref(null);

function isoToLocalDateKey(iso) {
  const d = new Date(iso);
  return d.toLocaleDateString("en-CA", { timeZone: "America/Montevideo" });
}

function isoToLocalTime(iso) {
  const d = new Date(iso);
  return d.toLocaleTimeString("es-UY", { timeZone: "America/Montevideo", hour: "2-digit", minute: "2-digit" });
}

function monthLabel(year, month) {
  const dt = new Date(year, month, 1);
  return dt.toLocaleDateString("es-UY", { month: "long", year: "numeric" });
}

function calendarMatrix(year, month) {
  const first = new Date(year, month, 1);
  const firstWeekdayMonStart = (first.getDay() + 6) % 7;
  const start = new Date(year, month, 1 - firstWeekdayMonStart);
  const cells = [];
  for (let i = 0; i < 42; i++) {
    const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
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
    map[k].sort((a, b) => new Date(a.fecha_hora_iso || a.fecha_hora) - new Date(b.fecha_hora_iso || b.fecha_hora));
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

// --- NUEVO: anchor YYYY-MM basado en mes visible ---
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

function prevMonth() {
  if (viewMonth.value === 0) { viewMonth.value = 11; viewYear.value -= 1; }
  else viewMonth.value -= 1;
}

function nextMonth() {
  if (viewMonth.value === 11) { viewMonth.value = 0; viewYear.value += 1; }
  else viewMonth.value += 1;
}

function goToday() {
  viewYear.value = today.getFullYear();
  viewMonth.value = today.getMonth();
}

function onClickDay(dateObj) {
  const key = dateObj.toLocaleDateString("en-CA", { timeZone: "America/Montevideo" });
  const clases = clasesByDate.value[key] ?? [];
  if (!clases || clases.length === 0) return;
  selectedDay.value = key;
  showDayModal.value = true;
}

function openTaller(clase) {
  tallerDetail.value = {
    id: clase.taller_id,
    nombre: clase.taller_nombre,
    calle: clase.taller_calle,
    numero: clase.taller_numero,
  };
  showTallerModal.value = true;
}

function dateHasFuture(dateKey) {
  const clases = clasesByDate.value[dateKey] ?? [];
  const now = new Date();
  return clases.some((c) => new Date(c.fecha_hora_iso || c.fecha_hora) >= now);
}

function isClosestUpcomingDate(dateKey) {
  if (!closestUpcoming.value) return false;
  const k = isoToLocalDateKey(closestUpcoming.value.fecha_hora_iso || closestUpcoming.value.fecha_hora);
  return k === dateKey;
}

onMounted(() => {
  loadDocentes();
  loadClasesForSelected();
});
</script>

<template>
  <Head title="Mis clases (Docente)" />
  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Mis clases</h2>
        <p class="text-sm text-gray-600">
          Calendario de clases del docente. Navegá por mes y seleccioná un día para ver detalles.
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Top row -->
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <label class="text-sm font-medium hidden sm:block">Docente</label>
            <select
              v-model="selectedCi"
              class="block w-full sm:w-72 rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
            >
              <option v-if="loadingDocentes" disabled> Cargando docentes... </option>
              <option v-for="d in docentes" :key="d.ci" :value="d.ci">
                {{ d.nombre ? `${d.nombre} · ${d.ci}` : d.ci }}
              </option>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <SecondaryButton @click="prevMonth">‹</SecondaryButton>
            <div class="px-3 text-sm font-medium text-gray-700">{{ monthLabel(viewYear, viewMonth) }}</div>
            <SecondaryButton @click="nextMonth">›</SecondaryButton>
            <PrimaryButton @click="goToday">Hoy</PrimaryButton>
          </div>
        </div>

        <!-- Loading / error -->
        <div v-if="loadingClases" class="flex justify-center py-8">
          <svg class="animate-spin h-6 w-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a 8 8 0 0 1 8-8v8z"></path>
          </svg>
        </div>
        <div v-if="error" class="text-red-600 text-sm mb-3">{{ error }}</div>

        <!-- Calendar -->
        <div v-else class="bg-white rounded-lg shadow-sm p-4">
          <div class="grid grid-cols-7 text-xs text-gray-500">
            <div class="text-center py-2">Lun</div>
            <div class="text-center py-2">Mar</div>
            <div class="text-center py-2">Mié</div>
            <div class="text-center py-2">Jue</div>
            <div class="text-center py-2">Vie</div>
            <div class="text-center py-2">Sáb</div>
            <div class="text-center py-2">Dom</div>
          </div>

          <div class="grid grid-cols-7 gap-1 mt-1 overflow-visible relative isolate">
            <template v-for="(dateObj, idx) in matrix" :key="idx">
              <div
                class="min-h-[64px] md:min-h-[84px] p-2 rounded-lg relative transition-colors"
                :class="{
                  'opacity-60': dateObj.getMonth() !== viewMonth,
                  'cursor-pointer hover:bg-blue-50': (clasesByDate[dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'})] ?? []).length > 0,
                  'bg-gray-100 ring-2 ring-gray-300': dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'}) === todayKey,
                  'ring-2 ring-verde-500': dateHasFuture(dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'})),
                  'pulse': isClosestUpcomingDate(dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'}))
                }"
                @click="onClickDay(dateObj)"
              >
                <div class="flex items-start justify-between">
                  <div class="text-sm font-medium" :class="{'text-gray-400': dateObj.getMonth() !== viewMonth}">
                    {{ dateObj.getDate() }}
                  </div>

                  <!-- Badge con cantidad de clases -->
                  <div v-if="(clasesByDate[dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'})] ?? []).length > 0" 
                       class="ml-2 inline-flex items-center justify-center text-xs font-semibold px-2 py-1 rounded-full bg-verde-100 text-verde-500">
                    {{ (clasesByDate[dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'})] ?? []).length }}
                  </div>
                </div>

                <!-- Breve resumen de clases -->
                <div class="mt-2 text-xs text-gray-600 hidden sm:block">
                  <template v-for="(c, i) in (clasesByDate[dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'})] ?? []).slice(0,2)" :key="c.id">
                    <div class="truncate">
                      <span class="font-medium">{{ isoToLocalTime(c.fecha_hora_iso || c.fecha_hora) }}</span>
                      <span class="ml-1 truncate">· {{ c.taller_nombre }}</span>
                    </div>
                  </template>
                  <div v-if="(clasesByDate[dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'})] ?? []).length > 2" class="text-xs text-gray-400">
                    + más...
                  </div>
                </div>

                <!-- Punto verde en esquina para pendiente -->
                <div v-if="dateHasFuture(dateObj.toLocaleDateString('en-CA', {timeZone:'America/Montevideo'}))" class="absolute bottom-1 right-1 w-3 h-3 rounded-full bg-verde-500"></div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@keyframes pulse {
  0%,100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 1; }
}
.pulse {
  animation: pulse 1.5s infinite;
  background-color: #ffffff;
  z-index: 1;
}
.bg-verde-500 { background-color: #22c55e; }
.text-verde-500 { color: #22c55e; }
.ring-verde-500 { border-color: #22c55e; }
.bg-verde-100 { background-color: #dcfce7; }
</style>
