<script setup>
import { ref, watch } from 'vue'
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
  editing: { type: Object, default: null }, // { ci, nombre?, apellido?, telefono? ... }
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
    } else {
      form.reset()
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

      // Endpoints de DOCENTES
      const p = await axios.get(`/admin/docentes/persona/${ci}`)
      const persona = p.data?.persona
      if (persona) {
        form.nombre           = persona.nombre ?? ''
        form.segundo_nombre   = persona.segundoNombre ?? ''
        form.apellido         = persona.apellido ?? ''
        form.segundo_apellido = persona.segundoApellido ?? ''
        form.telefono         = persona.telefono ?? ''
      }

      const e = await axios.get(`/admin/docentes/existe/${ci}`)
      yaExiste.value = !!e.data?.existe
    } catch {
      apiError.value = 'No se pudo consultar datos de la persona.'
    } finally {
      cargandoPersona.value = false
    }
  }
)

/* ==============
   Submit & close
   ============== */
const close = () => emit('close')

const submit = () => {
  // POST a docentes.store → updateOrCreate en Registro de Personas + firstOrCreate local
  form.post(route('admin.docentes.store'), {
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
        {{ editing ? 'Sincronizar Docente' : 'Nuevo Docente' }}
      </h2>

      <!-- Banner informativo si ya existe -->
      <div
        v-if="yaExiste && !editing"
        class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
      >
        Esta CI ya está registrada como docente. Al guardar, <strong>se actualizarán los datos en Registro de Personas</strong> y el registro local se mantendrá.
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
