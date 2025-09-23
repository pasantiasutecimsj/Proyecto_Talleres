<script setup>
import { ref, watch, computed, onMounted, nextTick } from "vue"
import axios from "axios"
import { useForm } from "@inertiajs/vue3"

import Modal from "@/Components/Modal.vue"
import PrimaryButton from "@/Components/PrimaryButton.vue"
import SecondaryButton from "@/Components/SecondaryButton.vue"
import TextInput from "@/Components/TextInput.vue"
import InputLabel from "@/Components/InputLabel.vue"
import InputError from "@/Components/InputError.vue"

const props = defineProps({
  show: { type: Boolean, default: false },
  editing: { type: Object, default: null }, // { id, fecha_hora, asistentes_maximos, taller:{id,nombre}, docente:{ci, nombre?, ...} }
  talleres: { type: Array, default: () => [] }, // [{ id, nombre }]
})

const emit = defineEmits(["close", "saved"])

/* ======================
   Formulario (useForm)
   ====================== */
const form = useForm({
  fecha_hora: "",
  asistentes_maximos: "1",
  taller_id: "",
  ci_docente: "",
})

/* ===== Helpers/acciones que usan watchers (deben estar hoisted) ===== */
function selectDocente(item) {
  form.ci_docente = String(item.ci)
  docenteDisplay.value = [item.nombre, item.ci].filter(Boolean).join(" · ")
  docenteSearch.value = ""
  docenteResults.value = []
}

function applyCiIfValid() {
  const ci = (docenteSearch.value || "").trim()
  if (/^\d{7,8}$/.test(ci)) {
    form.ci_docente = ci
    docenteDisplay.value = ci
    docenteSearch.value = ""
    docenteResults.value = []
    docenteApiError.value = ""
  }
}

function clearDocente() {
  form.ci_docente = ""
  docenteDisplay.value = ""
}

/* ============================
   Prefill (editar / nuevo)
   ============================ */
const docenteDisplay = ref("") // etiqueta visible (Nombre · CI)
const isPrefilling = ref(false)

watch(
  () => props.editing,
  async (val) => {
    form.clearErrors()
    if (val) {
      isPrefilling.value = true
      form.fecha_hora = toInputDateTimeLocal(val.fecha_hora) || ""
      form.asistentes_maximos = String(val?.asistentes_maximos ?? 1)
      form.taller_id = val?.taller?.id ?? ""
      // setear docente seleccionado desde editing
      const nombre = [val?.docente?.nombre, val?.docente?.apellido].filter(Boolean).join(" ")
      selectDocente({ ci: val?.docente?.ci ?? "", nombre: nombre || undefined })
      await nextTick()
      isPrefilling.value = false
    } else {
      form.reset()
      form.asistentes_maximos = "1"
      docenteDisplay.value = ""
    }
  },
  { immediate: true }
)

/* ============================
   Helpers de fecha/hora (input)
   ============================ */
function toInputDateTimeLocal(v) {
  if (!v) return ""
  const d = new Date(v)
  if (isNaN(d.getTime())) return ""
  const pad = (n) => String(n).padStart(2, "0")
  const yyyy = d.getFullYear()
  const mm = pad(d.getMonth() + 1)
  const dd = pad(d.getDate())
  const hh = pad(d.getHours())
  const mi = pad(d.getMinutes())
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}`
}

/* ===================================================
   Docentes: dos bloques -> "ranking" y "buscar"
   =================================================== */
const activeDocenteTab = ref("ranking") // 'ranking' | 'buscar'

/* -------- Bloque 1: RANKING (más activos) -------- */
const topDocentes = ref([]) // [{ ci, nombre, clases_count }]
const loadingTop = ref(false)
const topError = ref("")

const loadTopDocentes = async () => {
  topError.value = ""
  topDocentes.value = []
  if (!form.taller_id) return
  loadingTop.value = true
  try {
    const { data } = await axios.get(route("doc.docentes.top"), {
      params: { taller_id: form.taller_id },
    })
    topDocentes.value = Array.isArray(data) ? data : []
  } catch {
    topError.value = "No se pudo cargar el ranking de docentes."
  } finally {
    loadingTop.value = false
  }
}

watch(
  () => form.taller_id,
  () => {
    // Si el cambio de taller viene del prefill inicial, no borres el docente
    if (!isPrefilling.value) {
      clearDocente()
    }
    loadTopDocentes()
    // reset buscador siempre
    docenteSearch.value = ""
    docenteResults.value = []
    docenteApiError.value = ""
  }
)

onMounted(() => {
  if (form.taller_id) loadTopDocentes()
})

/* -------- Bloque 2: BUSCADOR -------- */
const docenteSearch = ref("")
const docenteResults = ref([]) // [{ ci, nombre, recomendado }]
const buscandoDocentes = ref(false)
const docenteApiError = ref("")
let lastReqId = 0

const minQueryOK = (s) => /^\d{7,8}$/.test(s ?? "") || (s ?? "").trim().length >= 2

const searchDocentes = async () => {
  const q = docenteSearch.value?.trim()
  docenteApiError.value = ""
  docenteResults.value = []
  if (!q || !minQueryOK(q)) return
  const reqId = ++lastReqId
  buscandoDocentes.value = true
  try {
    const params = { q, ...(form.taller_id ? { taller_id: form.taller_id } : {}) }
    const { data } = await axios.get(route("admin.docentes.buscar"), { params })
    if (reqId !== lastReqId) return
    docenteResults.value = Array.isArray(data) ? data : []
  } catch {
    if (reqId !== lastReqId) return
    docenteApiError.value = "No se pudo buscar docentes."
  } finally {
    if (reqId === lastReqId) buscandoDocentes.value = false
  }
}

// Debounce simple
let debounceTimer = null
watch(
  () => docenteSearch.value,
  () => {
    if (debounceTimer) clearTimeout(debounceTimer)
    if (!minQueryOK(docenteSearch.value)) {
      docenteResults.value = []
      docenteApiError.value = ""
      buscandoDocentes.value = false
      return
    }
    debounceTimer = setTimeout(searchDocentes, 300)
  }
)

/* ======================
   Guardar / Cerrar
   ====================== */
const close = () => emit("close")

const canSubmit = computed(() => {
  return (
    !!form.fecha_hora &&
    Number(form.asistentes_maximos) >= 1 &&
    !!form.taller_id &&
    !!form.ci_docente &&
    !form.processing
  )
})

const submit = () => {
  if (!form.ci_docente && /^\d{7,8}$/.test(docenteSearch.value ?? "")) {
    form.ci_docente = docenteSearch.value.trim()
  }

  const payload = (d) => ({ ...d, asistentes_maximos: Number(d.asistentes_maximos) })

  if (props.editing?.id) {
    form.transform(payload).put(route("admin.clases.update", props.editing.id), {
      preserveScroll: true,
      onSuccess: () => { emit("saved"); close() },
    })
  } else {
    form.transform(payload).post(route("admin.clases.store"), {
      preserveScroll: true,
      onSuccess: () => { emit("saved"); close() },
    })
  }
}
</script>



<template>
  <Modal :show="show" @close="close">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-4">
        {{ editing ? "Editar Clase" : "Nueva Clase" }}
      </h2>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Fecha y hora -->
        <div>
          <InputLabel for="c-fecha" value="Fecha y hora*" />
          <TextInput id="c-fecha" v-model="form.fecha_hora" type="datetime-local" class="mt-1 block w-full" required />
          <InputError :message="form.errors.fecha_hora" class="mt-2" />
        </div>

        <!-- Taller -->
        <div>
          <InputLabel for="c-taller" value="Taller*" />
          <select id="c-taller" v-model="form.taller_id"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
            required>
            <option value="" disabled>Seleccioná un taller</option>
            <option v-for="t in props.talleres" :key="t.id" :value="t.id">
              {{ t.nombre }}
            </option>
          </select>
          <InputError :message="form.errors.taller_id" class="mt-2" />
        </div>

        <!-- Docente -->
        <div>
          <InputLabel value="Docente*" />

          <!-- Seleccionado -->
          <div v-if="form.ci_docente || docenteDisplay" class="mb-2 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-2 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-xs">
              {{ docenteDisplay || form.ci_docente }}
              <button type="button" class="hover:text-indigo-950" @click="clearDocente">×</button>
            </span>
          </div>

          <!-- Tabs -->
          <div class="flex items-center gap-2 mb-3">
            <button type="button" class="text-xs px-3 py-1.5 rounded-full border"
              :class="activeDocenteTab === 'ranking' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300'"
              @click="activeDocenteTab = 'ranking'">
              Más activos en el taller
            </button>
            <button type="button" class="text-xs px-3 py-1.5 rounded-full border"
              :class="activeDocenteTab === 'buscar' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300'"
              @click="activeDocenteTab = 'buscar'">
              Buscar
            </button>
          </div>

          <!-- BLOQUE 1: Ranking -->
          <div v-show="activeDocenteTab === 'ranking'" class="space-y-2">
            <div class="flex items-center justify-between">
              <p class="text-sm text-gray-600">
                Docentes ordenados por <strong>clases dictadas</strong> en el taller seleccionado.
              </p>
              <button type="button" class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200"
                @click="loadTopDocentes" :disabled="loadingTop || !form.taller_id" title="Refrescar">
                Refrescar
              </button>
            </div>

            <div v-if="!form.taller_id" class="text-xs text-gray-500">
              Seleccioná un taller para ver el ranking.
            </div>

            <div v-else class="max-h-48 overflow-auto rounded border divide-y">
              <div v-if="loadingTop" class="px-3 py-2 text-xs text-gray-500">Cargando…</div>
              <div v-else-if="topError" class="px-3 py-2 text-xs text-red-600">{{ topError }}</div>

              <template v-else>
                <button v-for="(d, idx) in topDocentes" :key="d.ci + '-' + idx" type="button"
                  class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center justify-between"
                  @click="selectDocente(d)">
                  <span class="truncate">
                    <strong>{{ d.nombre || "Sin nombre" }}</strong>
                    <span class="text-gray-500"> · {{ d.ci }}</span>
                  </span>
                  <span
                    class="ml-2 inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-2 py-0.5 text-[10px] font-medium">
                    {{ d.clases_count }} clase{{ d.clases_count === 1 ? "" : "s" }}
                  </span>
                </button>

                <div v-if="!topDocentes.length" class="px-3 py-2 text-xs text-gray-500">
                  No hay docentes con clases previas en este taller.
                </div>
              </template>
            </div>
          </div>

          <!-- BLOQUE 2: Buscador -->
          <div v-show="activeDocenteTab === 'buscar'" class="space-y-2">
            <p class="text-sm text-gray-600">
              Buscá por <strong>CI</strong> (7–8 dígitos) o por <strong>nombre</strong>.
            </p>

            <TextInput v-model="docenteSearch" type="text" class="mt-1 block w-full"
              placeholder="Ej. 12345678 o María Pérez" @keydown.enter.prevent="applyCiIfValid" />

            <div class="max-h-48 overflow-auto rounded border divide-y">
              <div v-if="buscandoDocentes" class="px-3 py-2 text-xs text-gray-500">Buscando…</div>
              <div v-else-if="docenteApiError" class="px-3 py-2 text-xs text-red-600">{{ docenteApiError }}</div>

              <template v-else>
                <button v-for="(d, idx) in docenteResults" :key="d.ci + '-' + idx" type="button"
                  class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center justify-between"
                  @click="selectDocente(d)">
                  <span class="truncate">
                    <strong>{{ d.nombre || "Sin nombre" }}</strong>
                    <span class="text-gray-500"> · {{ d.ci }}</span>
                  </span>
                  <span v-if="d.recomendado"
                    class="ml-2 inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px] font-medium">
                    Recomendado
                  </span>
                </button>

                <div v-if="!docenteResults.length && /^\d{7,8}$/.test(docenteSearch)"
                  class="px-3 py-2 text-xs text-gray-600">
                  Presioná Enter para usar la CI <strong>{{ docenteSearch }}</strong>.
                </div>

                <div v-if="!docenteResults.length && !/^\d{7,8}$/.test(docenteSearch)"
                  class="px-3 py-2 text-xs text-gray-500">
                  Escribí al menos 2 caracteres para buscar por nombre.
                </div>
              </template>
            </div>
          </div>

          <InputError :message="form.errors.ci_docente" class="mt-2" />
        </div>

        <!-- Cupo -->
        <div>
          <InputLabel for="c-cupo" value="Cupo (asistentes máximos)*" />
          <TextInput id="c-cupo" v-model="form.asistentes_maximos" type="number" min="1" class="mt-1 block w-full"
            required />
          <InputError :message="form.errors.asistentes_maximos" class="mt-2" />
        </div>

        <!-- Botones -->
        <div class="flex justify-end gap-3 mt-6">
          <SecondaryButton type="button" @click="close">Cancelar</SecondaryButton>
          <PrimaryButton type="submit" :disabled="!canSubmit">
            <span>{{ form.processing ? "Guardando..." : (editing ? "Actualizar" : "Crear") }}</span>
          </PrimaryButton>
        </div>
      </form>
    </div>
  </Modal>
</template>
