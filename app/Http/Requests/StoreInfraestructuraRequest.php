<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInfraestructuraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tieneRol('Administrador') ?? false; // RN06
    }

    public function rules(): array
    {
        return [
            'nombre'  => ['required', 'string', 'max:120'],
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
            'tipo_id' => ['required', 'integer', 'exists:tipos_infraestructura,id'],
            'geojson' => ['required', 'string', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'  => 'El nombre es obligatorio.',
            'zona_id.exists'   => 'La zona elegida no existe.',
            'tipo_id.exists'   => 'El tipo elegido no existe.',
            'geojson.required' => 'Pega el GeoJSON de la ubicación.',
        ];
    }
}