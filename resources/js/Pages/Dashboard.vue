<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const sections = [
  {
    key: 'admin',
    title: 'Backoffice Administrador',
    requiredRoles: ['admin'], // Esto es el rol que habilita ver los botones de esta sección
    items: [
      { label: 'Talleres', desc: 'ABM de talleres', href: route('admin.talleres.index') },
      { label: 'Organizadores', desc: 'ABM de organizadores', href: route('admin.organizadores.index') },
      { label: 'Clases', desc: 'Listado y gestión', href: route('admin.clases.index') },
      { label: 'Docentes', desc: 'ABM de docentes', href: route('admin.docentes.index') },
    ],
  },
  {
    key: 'organizador',
    title: 'Backoffice Organizador',
    requiredRoles: ['organizador'],
    items: [
      { label: 'Talleres', desc: 'Listado y mantenimiento', href: route('org.talleres.index') },
      { label: 'Clases', desc: 'Organizar y crear clases', href: route('org.clases.index') },
    ],
  },
  {
    key: 'docente',
    title: 'Pantalla Docente',
    requiredRoles: ['docente'],
    items: [
      { label: 'Mis clases', desc: 'Agenda del docente', href: route('doc.clases.index') },
      { label: 'Asistentes por clase', desc: 'Ver y marcar asistencia', href: route('doc.clases.gestion') },
    ],
  },
  {
    key: 'publica',
    title: 'Página Pública',
    requiredRoles: [], // Si no tiene rol, cualquiera la ve
    items: [
      { label: 'Talleres activos', desc: 'Filtrar por ciudad', href: '#' },
      { label: 'Próximas clases del taller', desc: 'Calendario', href: '#' },
      { label: 'Anotarse a una clase', desc: 'Formulario de inscripción', href: '#' },
    ],
  },
]

const { props } = usePage()
const userRoles = computed(() => (props.auth?.roles ?? []).map(r => String(r).toLowerCase()))

function hasAnyRole(required = []) {
  if (!required || required.length === 0) return true
  return required.some(rr => userRoles.value.includes(String(rr).toLowerCase()))
}

// Secciones visibles según roles del usuario
const visibleSections = computed(() =>
  sections
    .map(section => {
      if (!hasAnyRole(section.requiredRoles)) return null
      // si quisieras filtrar items con requiredRoles propios:
      const items = (section.items ?? []).filter(item => hasAnyRole(item.requiredRoles ?? []))
      return { ...section, items }
    })
    .filter(Boolean)
)
</script>

<template>
  <Head title="Inicio" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Sistema de gestión de talleres
          </h2>
          <p class="text-sm text-gray-600">
            Panel principal con accesos para administrador, organizador, docente y público.
          </p>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Accesos rápidos</h3>

            <div class="space-y-8">
              <div v-for="section in visibleSections" :key="section.key">
                <div class="flex items-center justify-between mb-3">
                  <h4 class="text-base font-semibold text-gray-800">{{ section.title }}</h4>
                  <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                    {{ section.key }}
                  </span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  <Link v-for="(item, i) in section.items" :key="i" :href="item.href"
                        class="group bg-azul text-white rounded-md px-5 py-5 shadow hover:bg-azul-claro hover:scale-[1.02] transition duration-150 ease-in-out inline-flex items-center gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"
                         class="w-8 h-8 shrink-0" fill="currentColor" aria-hidden="true">
                      <path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q125 0 212.5-87.5T780-460q0-125-87.5-212.5T480-760q-125 0-212.5 87.5T180-460q0 125 87.5 212.5T480-160Z"/>
                    </svg>
                    <div class="text-left">
                      <div class="font-semibold">{{ item.label }}</div>
                      <div class="text-sm opacity-90">{{ item.desc }}</div>
                    </div>
                  </Link>
                </div>
              </div>

              <p v-if="visibleSections.length === 0" class="text-sm text-gray-600">
                No tienes permisos para estas secciones.
              </p>
            </div>
          </div>
        </div>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Ayuda rápida</h3>
            <p class="text-sm text-gray-600">
              Administrá talleres, clases, organizadores y asistencia. Los usuarios ya ven cada sección según sus roles!.
            </p>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
