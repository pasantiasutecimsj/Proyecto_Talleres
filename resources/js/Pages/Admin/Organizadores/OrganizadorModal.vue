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
  // edición: { user_id, nombre?, email?, talleres?: [{id,nombre}] }
  editing: { type: Object, default: null },
  talleres: { type: Array, default: () => [] }, // [{ id, nombre }]
})

const emit = defineEmits(['close', 'saved'])

const form = useForm({
  mode: 'create', // 'attach' | 'create' | 'edit'  ← ahora por defecto 'create'
  // attach existente
  user_id: '',
  // crear nuevo
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  // editar (usuario.* va dentro del PATCH)
  usuario: { name: '', email: '', password: '', password_confirmation: '' },
  // talleres (solo importa para organizador)
  talleres: [], // number[]
})

/* ===========================
   Prefill edición / nuevo
   =========================== */
watch(
  () => props.editing,
  (val) => {
    if (val) {
      form.mode = 'edit'
      form.user_id = String(val.user_id ?? '')
      form.usuario.name  = val.nombre ?? val.name ?? ''
      form.usuario.email = val.email ?? ''
      form.usuario.password = ''
      form.usuario.password_confirmation = ''
      form.talleres = Array.isArray(val.talleres) ? val.talleres.map(t => Number(t.id)) : []
      // limpiar campos de creación/attach
      form.name = form.email = form.password = form.password_confirmation = ''
    } else {
      form.reset()
      form.mode = 'create'   // ← cuando es alta, arrancamos en "Crear nuevo"
      form.talleres = []
    }
  },
  { immediate: true }
)

/* ======================
   Talleres: UI helpers
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
  if (isTallerSelected(id)) form.talleres = form.talleres.filter(tid => tid !== id)
  else form.talleres.push(id)
}
const clearTalleres = () => {
  const setIds = new Set(filteredTalleres.value.map(t => Number(t.id)))
  form.talleres = form.talleres.filter(id => !setIds.has(Number(id)))
}

/* ======================
   Autocomplete (attach)
   ====================== */
const searchTerm = ref('')
const searching = ref(false)
const results = ref([])
const searchError = ref('')
let debounceTimer = null

const doSearch = async (q) => {
  if (!q || q.trim().length < 2) {
    results.value = []
    return
  }
  searching.value = true
  searchError.value = ''
  try {
    const { data } = await axios.get(route('admin.usuarios.buscar'), { params: { q: q.trim() } })
    results.value = Array.isArray(data) ? data : (data?.data ?? [])
  } catch (e) {
    searchError.value = 'No se pudo buscar en la API de usuarios.'
  } finally {
    searching.value = false
  }
}

watch(searchTerm, (q) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => doSearch(q), 300)
})
const selectResult = (u) => {
  form.user_id = String(u.id)
  results.value = []
  searchTerm.value = `${u.name ?? 'Usuario'} (${u.email ?? 's/ email'})`
}

/* ==============
   Submit & close
   ============== */
const close = () => emit('close')

const canSubmit = computed(() => {
  if (form.mode === 'attach') return !!form.user_id
  if (form.mode === 'create') {
    return !!form.name && !!form.email && !!form.password && form.password === form.password_confirmation
  }
  // edit
  return !!form.user_id
})

const submit = () => {
  // En todos los casos, este modal SOLO gestiona ORGANIZADORES.
  // create/attach: enviamos roles=['organizador']; edit: no tocamos roles.

  if (form.mode === 'edit') {
    const payload = {
      talleres: form.talleres,
      usuario: {},
    }
    if (form.usuario.name !== '')  payload.usuario.name  = form.usuario.name
    if (form.usuario.email !== '') payload.usuario.email = form.usuario.email
    if (form.usuario.password !== '') {
      payload.usuario.password = form.usuario.password
      payload.usuario.password_confirmation = form.usuario.password_confirmation
    }

    form.transform(() => payload)
      .patch(route('admin.organizadores.update', Number(form.user_id)), {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); close() },
      })
    return
  }

  // create / attach → usamos store()
  const payload = {
    roles: ['organizador'],        // <- forzado siempre
    talleres: form.talleres ?? [],
  }

  if (form.mode === 'attach') {
    payload.user_id = Number(form.user_id)
  } else {
    payload.name = form.name
    payload.email = form.email
    payload.password = form.password
    payload.password_confirmation = form.password_confirmation
  }

  form.transform(() => payload)
    .post(route('admin.organizadores.store'), {
      preserveScroll: true,
      onSuccess: () => { emit('saved'); close() },
    })
}
</script>

<template>
  <Modal :show="show" @close="close">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-4">
        <template v-if="form.mode === 'edit'">Editar organizador</template>
        <template v-else>Alta de organizador (crear o adjuntar)</template>
      </h2>

      <!-- Tabs de modo (ocultos si editando) -->
      <div v-if="form.mode !== 'edit'" class="mb-4 inline-flex rounded-lg border bg-gray-50 p-1">
        <!-- CREAR nuevo (primero y seleccionado por defecto) -->
        <button
          type="button"
          class="px-3 py-1 text-sm rounded-md"
          :class="form.mode === 'create' ? 'bg-white shadow' : 'text-gray-600 hover:text-gray-800'"
          @click="form.mode = 'create'"
        >
          Crear nuevo
        </button>
        <!-- ADJUNTAR existente -->
        <button
          type="button"
          class="px-3 py-1 text-sm rounded-md"
          :class="form.mode === 'attach' ? 'bg-white shadow' : 'text-gray-600 hover:text-gray-800'"
          @click="form.mode = 'attach'"
        >
          Adjuntar existente
        </button>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- ====== CREAR ====== -->
        <div v-if="form.mode === 'create'">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <InputLabel for="name" value="Nombre*" />
              <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
              <InputError :message="form.errors.name" class="mt-2" />
            </div>
            <div>
              <InputLabel for="email" value="Email*" />
              <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
              <InputError :message="form.errors.email" class="mt-2" />
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <InputLabel for="password" value="Contraseña*" />
              <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
              <InputError :message="form.errors.password" class="mt-2" />
            </div>
            <div>
              <InputLabel for="password_confirmation" value="Confirmar contraseña*" />
              <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required />
            </div>
          </div>
          <p class="text-xs text-gray-500">Se creará con el rol <strong>organizador</strong> en el proyecto.</p>
        </div>

        <!-- ====== ADJUNTAR ====== -->
        <div v-else-if="form.mode === 'attach'">
          <InputLabel value="Buscar usuario (nombre o email)" />
          <div class="mt-1 relative">
            <input
              v-model="searchTerm"
              type="text"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-azul focus:ring-azul sm:text-sm"
              placeholder="Ej: María o maria@imsj.gub.uy"
            />
            <div v-if="searching" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500">buscando…</div>
          </div>
          <p v-if="searchError" class="text-xs text-red-600 mt-1">{{ searchError }}</p>

          <div v-if="results.length" class="mt-2 max-h-44 overflow-auto rounded border divide-y bg-white">
            <button
              v-for="u in results"
              :key="u.id"
              type="button"
              class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
              @click="selectResult(u)"
            >
              <div class="font-medium">{{ u.name || 'Sin nombre' }}</div>
              <div class="text-xs text-gray-500 flex items-center gap-2">
                <span>#{{ u.id }}</span>
                <span v-if="u.email">· {{ u.email }}</span>
              </div>
            </button>
          </div>

          <div class="mt-4">
            <InputLabel for="user_id" value="User ID (api_usuarios)" />
            <TextInput id="user_id" v-model="form.user_id" type="number" min="1" class="mt-1 block w-full" placeholder="Ej: 123" required />
            <InputError :message="form.errors.user_id" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">Se asignará automáticamente el rol <strong>organizador</strong>.</p>
          </div>
        </div>

        <!-- ====== EDITAR ORGANIZADOR ====== -->
        <div v-else>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <InputLabel for="e_name" value="Nombre" />
              <TextInput id="e_name" v-model="form.usuario.name" type="text" class="mt-1 block w-full" placeholder="(sin cambios)" />
              <InputError :message="form.errors['usuario.name']" class="mt-2" />
            </div>
            <div>
              <InputLabel for="e_email" value="Email" />
              <TextInput id="e_email" v-model="form.usuario.email" type="email" class="mt-1 block w-full" placeholder="(sin cambios)" />
              <InputError :message="form.errors['usuario.email']" class="mt-2" />
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <InputLabel for="e_password" value="Nueva contraseña" />
              <TextInput id="e_password" v-model="form.usuario.password" type="password" class="mt-1 block w-full" />
              <InputError :message="form.errors['usuario.password']" class="mt-2" />
            </div>
            <div>
              <InputLabel for="e_password_confirmation" value="Confirmar contraseña" />
              <TextInput id="e_password_confirmation" v-model="form.usuario.password_confirmation" type="password" class="mt-1 block w-full" />
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-1">
            Este módulo no modifica otros roles del usuario. El rol <strong>organizador</strong> se conserva.
          </p>
        </div>

        <!-- ====== TALLERES ====== -->
        <div>
          <InputLabel value="Asignar a Talleres" />

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

          <div class="mt-3 flex gap-2 items-center">
            <input v-model="tallerSearch" type="text" class="flex-1 rounded border px-2 py-1 text-sm" placeholder="Buscar taller…" />
            <button type="button" @click="clearTalleres" class="flex text-xs px-2 py-1 rounded bg-indigo-100 hover:bg-indigo-200">Limpiar</button>
          </div>

          <div class="mt-2 max-h-48 overflow-auto rounded border divide-y">
            <label v-for="t in filteredTalleres" :key="t.id" class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
              <input type="checkbox" :checked="isTallerSelected(t.id)" @change="toggleTaller(t.id)" />
              <span class="flex-1">{{ t.nombre }}</span>
            </label>
            <div v-if="!filteredTalleres.length" class="px-3 py-2 text-xs text-gray-500 italic">No hay talleres que coincidan con “{{ tallerSearch }}”.</div>
          </div>

          <InputError :message="form.errors.talleres" class="mt-2" />
        </div>

        <!-- Botones -->
        <div class="flex justify-end gap-3 mt-6">
          <SecondaryButton @click="close">Cancelar</SecondaryButton>
          <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
            <span>
              {{ form.processing ? 'Guardando...' :
                (form.mode === 'edit' ? 'Guardar cambios' : 'Confirmar') }}
            </span>
          </PrimaryButton>
        </div>
      </form>
    </div>
  </Modal>
</template>
