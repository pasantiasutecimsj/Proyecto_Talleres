<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegistroPersonasService
{
    // PERSONAS
    public function getPersonas()
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/personas');
    }

    public function getPersona($ci)
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/personas/' . $ci);
    }

    public function createPersona($persona)
    {
        return Http::post(env('URL_REGISTRO_PERSONAS') . '/personas', $persona);
    }

    public function updatePersona($id, $persona)
    {
        return Http::put(env('URL_REGISTRO_PERSONAS') . '/personas/' . $id, $persona);
    }

    public function patchPersona($id, $persona)
    {
        return Http::patch(env('URL_REGISTRO_PERSONAS') . '/personas/' . $id, $persona);
    }

    public function deletePersona($id)
    {
        return Http::delete(env('URL_REGISTRO_PERSONAS') . '/personas/' . $id);
    }

    public function updateOrCreatePersona($persona)
    {
        return Http::post(env('URL_REGISTRO_PERSONAS') . '/personas/updateOrCreate', $persona);
    }

    // ORGANIZACIONES
    public function getOrganizaciones()
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/organizaciones');
    }

    public function getOrganizacion($id)
    {
        log::info(env('URL_REGISTRO_PERSONAS') . '/organizaciones/' . $id);
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/organizaciones/' . $id);
    }

    public function createOrganizacion($organizacion)
    {
        return Http::post(env('URL_REGISTRO_PERSONAS') . '/organizaciones', $organizacion);
    }

    public function updateOrganizacion($id, $organizacion)
    {
        return Http::put(env('URL_REGISTRO_PERSONAS') . '/organizaciones/' . $id, $organizacion);
    }

    public function patchOrganizacion($id, $organizacion)
    {
        return Http::patch(env('URL_REGISTRO_PERSONAS') . '/organizaciones/' . $id, $organizacion);
    }

    public function deleteOrganizacion($id)
    {
        return Http::delete(env('URL_REGISTRO_PERSONAS') . '/organizaciones/' . $id);
    }

    // CIUDADES
    public function getCiudades()
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/ciudades');
    }

    public function getCiudad($id)
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/ciudades/' . $id);
    }

    public function getCiudadByNombre($nombre)
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/ciudades/nombre/' . $nombre);
    }

    public function createCiudad($ciudad)
    {
        return Http::post(env('URL_REGISTRO_PERSONAS') . '/ciudades', $ciudad);
    }

    public function updateCiudad($id, $ciudad)
    {
        return Http::put(env('URL_REGISTRO_PERSONAS') . '/ciudades/' . $id, $ciudad);
    }

    public function patchCiudad($id, $ciudad)
    {
        return Http::patch(env('URL_REGISTRO_PERSONAS') . '/ciudades/' . $id, $ciudad);
    }

    public function deleteCiudad($id)
    {
        return Http::delete(env('URL_REGISTRO_PERSONAS') . '/ciudades/' . $id);
    }

    // DIRECCIONES
    public function getDirecciones()
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/direcciones');
    }

    public function getDireccionesFullData()
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/direcciones/full');
    }

    public function getDireccion($id)
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/direcciones/' . $id);
    }

    public function getDireccionesByPersona($ci)
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/direcciones/persona/' . $ci);
    }

    public function getDireccionesByOrganizacion($id)
    {
        return Http::get(env('URL_REGISTRO_PERSONAS') . '/direcciones/organizacion/' . $id);
    }

    public function buscarDireccion($filtros)
    {
        return Http::post(env('URL_REGISTRO_PERSONAS') . '/direcciones/buscar', $filtros);
    }

    public function createDireccionConPersona($data)
    {
        return Http::post(env('URL_REGISTRO_PERSONAS') . '/direcciones/crear/persona', $data);
    }

    public function createDireccionConOrganizacion($data)
    {
        return Http::post(env('URL_REGISTRO_PERSONAS') . '/direcciones/crear/organizacion', $data);
    }

    public function updateDireccion($id, $direccion)
    {
        return Http::put(env('URL_REGISTRO_PERSONAS') . '/direcciones/' . $id, $direccion);
    }

    public function patchDireccion($id, $direccion)
    {
        return Http::patch(env('URL_REGISTRO_PERSONAS') . '/direcciones/' . $id, $direccion);
    }

    public function updatePersonaDireccion($id, $data)
    {
        return Http::put(env('URL_REGISTRO_PERSONAS') . '/direcciones/persona-direccion/' . $id, $data);
    }

    public function patchPersonaDireccion($id, $data)
    {
        return Http::patch(env('URL_REGISTRO_PERSONAS') . '/direcciones/persona-direccion/' . $id, $data);
    }

    public function deleteDireccion($id)
    {
        return Http::delete(env('URL_REGISTRO_PERSONAS') . '/direcciones/' . $id);
    }

    public function deletePersonaDireccion($id)
    {
        return Http::delete(env('URL_REGISTRO_PERSONAS') . '/direcciones/persona-direccion/' . $id);
    }
    
}
