<script setup>
import { ref, watch, computed } from "vue"
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
  // { id, fecha_hora, asistentes_maximos, taller:{id,nombre}, docente:{ci, nombre?, apellido?, ...} }
  editing: { type: Object, default: null },
  talleres: { type: Array, default: () => [] }, // [{ id, nombre }]
})

const emit = defineEmits(["close", "saved"])

/* ======================
   Formulario (useForm)
   ====================== */
const form = useForm({
  fecha_hora: "",
  asistentes_maximos: '1',
  taller_id: "",
  ci_docente: "",
})

/* ============================
   Helpers de fecha/hora (input)
   ============================ */
const toInputDateTimeLocal = (v) => {
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

/* ============================
   Prefill (editar / nuevo)
   ============================ */
const docenteDisplay = ref("") // etiqueta visible en el input (nombre o CI)
watch(
  () => props.editing,
  (val) => {
    form.clearErrors()
    if (val) {
      form.fecha_hora = toInputDateTimeLocal(val.fecha_hora) || ""
      form.asistentes_maximos = String(val?.asistentes_maximos ?? 1)
      form.taller_id = val?.taller?.id ?? ""
      form.ci_docente = val?.docente?.ci ?? ""
      const nombre = [val?.docente?.nombre, val?.docente?.apellido].filter(Boolean).join(" ")
      docenteDisplay.value = nombre || val?.docente?.ci || ""
    } else {
      form.reset()
      form.asistentes_maximos = 1
      docenteDisplay.value = ""
    }
  },
  { immediate: true }
)

// Si cambia el taller, limpiamos el docente seleccionado (para re-sugerir)
watch(
  () => form.taller_id,
  () => {
    form.ci_docente = ""
    docenteDisplay.value = ""
    docenteSearch.value = ""
    docenteResults.value = []
  }
)

/* ============================
   Autocomplete de Docentes
   ============================ */
const docenteSearch = ref("")
const docenteResults = ref([]) // [{ ci, nombre, recomendado }]
const buscandoDocentes = ref(false)
const docenteApiError = ref("")
let lastReqId = 0

const showDocentesDropdown = computed(() => {
  return !!docenteSearch.value && (buscandoDocentes.value || docenteResults.value.length > 0 || docenteApiError.value)
})

const searchDocentes = async () => {
  const q = docenteSearch.value?.trim()
  docenteApiError.value = ""
  docenteResults.value = []
  if (!q) return
  const reqId = ++lastReqId
  buscandoDocentes.value = true
  try {
    const params = {
      q,
      ...(form.taller_id ? { taller_id: form.taller_id } : {}),
    }
    const { data } = await axios.get(route("admin.docentes.buscar"), { params })
    // Evitar desorden si llegan fuera de orden
    if (reqId !== lastReqId) return
    // Esperamos [{ ci, nombre, recomendado }]
    docenteResults.value = Array.isArray(data) ? data : []
  } catch (e) {
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
    // Si escribe un CI de 8 dígitos, no hace falta buscar
    const isEightDigits = /^\d{7,8}$/.test(docenteSearch.value ?? "")
    if (isEightDigits) {
      docenteResults.value = []
      docenteApiError.value = ""
      buscandoDocentes.value = false
      return
    }
    debounceTimer = setTimeout(searchDocentes, 300)
  }
)

// Seleccionar docente desde la lista
const selectDocente = (item) => {
  form.ci_docente = String(item.ci)
  docenteDisplay.value = [item.nombre, item.ci].filter(Boolean).join(" · ")
  docenteSearch.value = ""
  docenteResults.value = []
}

// Permitir ingresar CI manual (8 dígitos)
const applyCiIfValid = () => {
  const ci = (docenteSearch.value || "").trim()
  if (/^\d{7,8}$/.test(ci)) {
    form.ci_docente = ci
    docenteDisplay.value = ci
    docenteSearch.value = ""
    docenteResults.value = []
    docenteApiError.value = ""
  }
}

const clearDocente = () => {
  form.ci_docente = ""
  docenteDisplay.value = ""
  docenteSearch.value = ""
  docenteResults.value = []
  docenteApiError.value = ""
}

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
  // Si el usuario dejó un CI válido en el cuadro de búsqueda sin seleccionar, aplicarlo
  if (!form.ci_docente && /^\d{7,8}$/.test(docenteSearch.value ?? "")) {
    form.ci_docente = docenteSearch.value.trim()
  }

  if (props.editing?.id) {
    form.transform(d => ({ ...d, asistentes_maximos: Number(d.asistentes_maximos) }))
      .put(route("admin.clases.update", props.editing.id), {
        preserveScroll: true,
        onSuccess: () => {
          emit("saved")
          close()
        },
      })
  } else {
    form.transform(d => ({ ...d, asistentes_maximos: Number(d.asistentes_maximos) }))
      .post(route("admin.clases.store"), {
        preserveScroll: true,
        onSuccess: () => {
          emit("saved")
          close()
        },
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

        <!-- Docente (autocomplete) -->
        <div>
          <InputLabel value="Docente (CI o nombre)*" />
          <!-- Etiqueta del docente seleccionado -->
          <div v-if="form.ci_docente || docenteDisplay" class="mb-2 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-2 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-xs">
              {{ docenteDisplay || form.ci_docente }}
              <button type="button" class="hover:text-indigo-950" @click="clearDocente">×</button>
            </span>
          </div>

          <!-- Campo de búsqueda / ingreso -->
          <div class="relative">
            <TextInput v-model="docenteSearch" type="text" class="mt-1 block w-full"
              placeholder="Escribí nombre o CI (8 dígitos)…" @keydown.enter.prevent="applyCiIfValid" />

            <!-- Dropdown de resultados -->
            <div v-if="showDocentesDropdown" class="absolute z-10 mt-1 w-full rounded-md border bg-white shadow">
              <div v-if="buscandoDocentes" class="px-3 py-2 text-xs text-gray-500">
                Buscando…
              </div>
              <div v-else-if="docenteApiError" class="px-3 py-2 text-xs text-red-600">
                {{ docenteApiError }}
              </div>
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
                  No se encontraron docentes para “{{ docenteSearch }}”.
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
