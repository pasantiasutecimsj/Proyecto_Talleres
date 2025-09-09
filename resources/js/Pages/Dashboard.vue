<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

// Todas las secciones visibles por ahora.
// Más adelante: filtrar por roles desde props de Inertia (auth.user.roles)
const sections = [
  {
    key: 'admin',
    title: 'Backoffice Administrador',
    items: [
      { label: 'Talleres: crear/editar/baja', desc: 'ABM de talleres', href: '#' /* route('admin.talleres.index') */ },
      { label: 'Organizadores: registrar/editar/baja', desc: 'ABM de organizadores', href: '#' /* route('admin.organizadores.index') */ },
      { label: 'Asociar organizador ↔ taller', desc: 'Asignaciones y desvinculaciones', href: '#' /* route('admin.talleres.organizadores') */ },
      { label: 'Clases: listar/crear/editar/cancelar', desc: 'Gestión de clases', href: '#' /* route('admin.clases.index') */ },
      { label: 'Asistentes por clase', desc: 'Listado y control de asistencia', href: '#' /* route('admin.clases.asistentes.index') */ },
    ],
  },
  {
    key: 'organizador',
    title: 'Backoffice Organizador',
    items: [
      { label: 'Elegir taller', desc: 'Si organiza varios', href: '#' /* route('org.talleres.index') */ },
      { label: 'Editar datos del taller', desc: 'Dirección, nombre, descripción', href: '#' /* route('org.talleres.edit') */ },
      { label: 'Clases del taller', desc: 'Listar/crear/editar/cancelar', href: '#' /* route('org.clases.index') */ },
      { label: 'Asistentes por clase', desc: 'Ver y estado de asistencia', href: '#' /* route('org.clases.asistentes.index') */ },
    ],
  },
  {
    key: 'docente',
    title: 'Pantalla Docente',
    items: [
      { label: 'Mis clases (hoy y próximas)', desc: 'Agenda del docente', href: '#' /* route('doc.clases.index') */ },
      { label: 'Asistentes por clase', desc: 'Listado de inscriptos', href: '#' /* route('doc.clases.asistentes.index') */ },
      { label: 'Marcar asistencia', desc: 'Asistió / no asistió', href: '#' /* route('doc.asistencia.index') */ },
    ],
  },
  {
    key: 'publica',
    title: 'Página Pública',
    items: [
      { label: 'Talleres activos', desc: 'Filtrar por ciudad', href: '#' /* route('pub.talleres.index') */ },
      { label: 'Próximas clases del taller', desc: 'Calendario', href: '#' /* route('pub.talleres.show') */ },
      { label: 'Anotarse a una clase', desc: 'Formulario de inscripción', href: '#' /* route('pub.inscripciones.create') */ },
    ],
  },
]

// TODO (roles futuros):
// import { usePage } from '@inertiajs/vue3'
// const { props } = usePage()
// const roles = props.auth?.user?.roles ?? []
// const visible = sections.filter(s => hasRoleFor(s.key, roles))
</script>

<template>
  <Head title="Inicio" />

  <AuthenticatedLayout>
    <!-- Header -->
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

    <!-- Contenido -->
    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

        <!-- Accesos -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Accesos rápidos</h3>
            <p class="text-sm text-gray-600 mb-6">
              (Temporal) Todos los menús están visibles. Luego se filtrarán por rol.
            </p>

            <div class="space-y-8">
              <div v-for="section in sections" :key="section.key">
                <div class="flex items-center justify-between mb-3">
                  <h4 class="text-base font-semibold text-gray-800">
                    {{ section.title }}
                  </h4>
                  <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                    {{ section.key }}
                  </span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  <Link
                    v-for="(item, i) in section.items"
                    :key="i"
                    :href="item.href"
                    class="group bg-azul text-white rounded-md px-5 py-5 shadow hover:bg-azul-claro hover:scale-[1.02] transition duration-150 ease-in-out inline-flex items-center gap-4"
                  >
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
            </div>
          </div>
        </div>

        <!-- Ayuda -->
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Ayuda rápida</h3>
            <p class="text-sm text-gray-600">
              Administrá talleres, clases, organizadores y asistencia. Luego
              cada usuario verá sólo sus secciones según sus roles.
            </p>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
