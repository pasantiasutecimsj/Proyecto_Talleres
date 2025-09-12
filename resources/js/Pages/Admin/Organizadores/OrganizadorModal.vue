<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { useForm } from '@inertiajs/vue3'

import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  editing: { type: Object, default: null }, // { ci, nombre?, apellido?, ... , talleres?: [{id,nombre}] }
  // 👇 catálogo completo de talleres para asignar
  talleres: { type: Array, default: () => [] }, // [{ id, nombre }]
})

const emit = defineEmits(['close', 'saved'])

const yaExiste = ref(false)
const cargandoPersona = ref(false)
const apiError = ref('')

const form = useForm({
  ci: '',
  nombre: '',
  segundo_nombre: '',
  apellido: '',
  segundo_apellido: '',
  telefono: '',
  // 👇 IDs de los talleres seleccionados
  talleres: [], // number[]
})

/* ===========================
   Prefill de edición / nuevo
   =========================== */
watch(
  () => props.editing,
  (val) => {
    apiError.value = ''
    yaExiste.value = false
    if (val) {
      form.ci               = val.ci ?? ''
      form.nombre           = val.nombre ?? ''
      form.segundo_nombre   = val.segundo_nombre ?? ''
      form.apellido         = val.apellido ?? ''
      form.segundo_apellido = val.segundo_apellido ?? ''
      form.telefono         = val.telefono ?? ''
      // Preseleccionar talleres si vienen en edición
      form.talleres         = Array.isArray(val.talleres) ? val.talleres.map(t => Number(t.id)) : []
    } else {
      form.reset()
      form.talleres = []
    }
  },
  { immediate: true }
)

/* ======================================================
   Prefill + existe cuando el usuario escribe CI (en "nuevo")
   ====================================================== */
watch(
  () => form.ci,
  async (ci) => {
    apiError.value = ''
    if (!ci || ci.length < 7 || props.editing) return

    try {
      cargandoPersona.value = true

      // 1) Prefill desde api_personas
      const p = await axios.get(`/admin/organizadores/persona/${ci}`)
      const persona = p.data?.persona
      if (persona) {
        form.nombre           = persona.nombre ?? ''
        form.segundo_nombre   = persona.segundoNombre ?? ''
        form.apellido         = persona.apellido ?? ''
        form.segundo_apellido = persona.segundoApellido ?? ''
        form.telefono         = persona.telefono ?? ''
      }

      // 2) Ya existe localmente
      const e = await axios.get(`/admin/organizadores/existe/${ci}`)
      yaExiste.value = !!e.data?.existe
    } catch (err) {
      apiError.value = 'No se pudo consultar datos de la persona.'
    } finally {
      cargandoPersona.value = false
    }
  }
)

/* ======================
   Talleres: UI y helpers
   ====================== */
const tallerSearch = ref('')

const filteredTalleres = computed(() => {
  const term = tallerSearch.value.trim().toLowerCase()
  if (!term) return props.talleres
  return props.talleres.filter(t => (t.nombre ?? '').toLowerCase().includes(term))
})

const isTallerSelected = (id) => form.talleres.includes(Number(id))

const toggleTaller = (id) => {
  id = Number(id)
  if (isTallerSelected(id)) {
    form.talleres = form.talleres.filter(tid => tid !== id)
  } else {
    form.talleres.push(id)
  }
}

const clearTalleres = () => {
  const setIds = new Set(filteredTalleres.value.map(t => Number(t.id)))
  form.talleres = form.talleres.filter(id => !setIds.has(Number(id)))
}

/* ==============
   Submit & close
   ============== */
const close = () => emit('close')

const submit = () => {
  // Siempre POST a store() para hacer updateOrCreate en API + firstOrCreate local
  // y asignar talleres (el controller debe sync() con form.talleres)
  form.post(route('admin.organizadores.store'), {
    preserveScroll: true,
    onSuccess: () => {
      emit('saved')
      close()
    },
  })
}
</script>

<template>
  <Modal :show="show" @close="close">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-4">
        {{ editing ? 'Sincronizar Organizador' : 'Nuevo Organizador' }}
      </h2>

      <!-- Banner informativo si ya existe -->
      <div
        v-if="yaExiste && !editing"
        class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
      >
        Esta CI ya está registrada como organizador. Al guardar, <strong>se actualizarán los datos en Registro de Personas</strong>,
        se mantendrá el registro local y podrás asignar/actualizar sus talleres.
      </div>

      <div v-if="apiError" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        {{ apiError }}
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- CI -->
        <div>
          <InputLabel for="ci" value="Cédula (8 dígitos)" />
          <TextInput
            id="ci"
            v-model="form.ci"
            type="text"
            maxlength="8"
            class="mt-1 block w-full"
            placeholder="Ej: 12345678"
            :disabled="!!editing"
            required
          />
          <InputError :message="form.errors.ci" class="mt-2" />
          <p v-if="cargandoPersona" class="text-xs text-gray-500 mt-1">Cargando datos…</p>
        </div>

        <!-- Nombre y Segundo nombre -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <InputLabel for="nombre" value="Nombre*" />
            <TextInput
              id="nombre"
              v-model="form.nombre"
              type="text"
              class="mt-1 block w-full"
              placeholder="Ej: María"
              required
            />
            <InputError :message="form.errors.nombre" class="mt-2" />
          </div>
          <div>
            <InputLabel for="segundo_nombre" value="Segundo nombre" />
            <TextInput
              id="segundo_nombre"
              v-model="form.segundo_nombre"
              type="text"
              class="mt-1 block w-full"
              placeholder="Ej: Laura"
            />
            <InputError :message="form.errors.segundo_nombre" class="mt-2" />
          </div>
        </div>

        <!-- Apellido y Segundo apellido -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <InputLabel for="apellido" value="Apellido*" />
            <TextInput
              id="apellido"
              v-model="form.apellido"
              type="text"
              class="mt-1 block w-full"
              placeholder="Ej: Pérez"
              required
            />
            <InputError :message="form.errors.apellido" class="mt-2" />
          </div>
          <div>
            <InputLabel for="segundo_apellido" value="Segundo apellido" />
            <TextInput
              id="segundo_apellido"
              v-model="form.segundo_apellido"
              type="text"
              class="mt-1 block w-full"
              placeholder="Ej: Gómez"
            />
            <InputError :message="form.errors.segundo_apellido" class="mt-2" />
          </div>
        </div>

        <!-- Teléfono -->
        <div>
          <InputLabel for="telefono" value="Teléfono" />
          <TextInput
            id="telefono"
            v-model="form.telefono"
            type="text"
            class="mt-1 block w-full"
            placeholder="Ej: 099123456"
          />
          <InputError :message="form.errors.telefono" class="mt-2" />
        </div>

        <!-- =======================
             Asignación de Talleres
             ======================= -->
        <div>
          <InputLabel value="Asignar a Talleres" />

          <!-- Chips de seleccionados -->
          <div v-if="form.talleres.length" class="mt-2 flex flex-wrap gap-2">
            <span
              v-for="tid in form.talleres"
              :key="'chip-' + tid"
              class="inline-flex items-center gap-2 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-xs"
            >
              {{ (props.talleres.find(t => Number(t.id) === Number(tid))?.nombre) ?? `Taller ${tid}` }}
              <button type="button" @click="toggleTaller(tid)" class="hover:text-indigo-950">×</button>
            </span>
          </div>

          <!-- Barra de búsqueda + limpiar -->
          <div class="mt-3 flex gap-2 items-center">
            <input
              v-model="tallerSearch"
              type="text"
              class="flex-1 rounded border px-2 py-1 text-sm"
              placeholder="Buscar taller…"
            />
            <button
              type="button"
              @click="clearTalleres"
              class="flex text-xs px-2 py-1 rounded bg-indigo-100 hover:bg-indigo-200"
            >
              Limpiar
            </button>
          </div>

          <!-- Lista de talleres con check -->
          <div class="mt-2 max-h-48 overflow-auto rounded border divide-y">
            <label
              v-for="t in filteredTalleres"
              :key="t.id"
              class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
            >
              <input type="checkbox" :checked="isTallerSelected(t.id)" @change="toggleTaller(t.id)" />
              <span class="flex-1">{{ t.nombre }}</span>
            </label>
            <div v-if="!filteredTalleres.length" class="px-3 py-2 text-xs text-gray-500 italic">
              No hay talleres que coincidan con “{{ tallerSearch }}”.
            </div>
          </div>

          <InputError :message="form.errors.talleres" class="mt-2" />
        </div>

        <!-- Botones -->
        <div class="flex justify-end gap-3 mt-6">
          <SecondaryButton @click="close">Cancelar</SecondaryButton>
          <PrimaryButton
            type="submit"
            :disabled="form.processing || !form.ci || form.ci.length !== 8 || !form.nombre || !form.apellido"
          >
            <span>
              {{ form.processing ? 'Guardando...' : (editing ? 'Sincronizar' : 'Guardar') }}
            </span>
          </PrimaryButton>
        </div>
      </form>
    </div>
  </Modal>
</template>
