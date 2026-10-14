<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEscenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tieneRol('Administrador') ?? false; // RN06
    }

    public function rules(): array
    {
        return [
            'nombre'  => ['required', 'string', 'max:100'],
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
            'nivel'   => ['required', 'in:leve,moderado,extremo'],
            'origen'  => ['required', 'in:simulado,importado'],
            'geojson' => ['required', 'string', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'  => 'El nombre es obligatorio.',
            'zona_id.exists'   => 'La zona elegida no existe.',
            'nivel.in'         => 'Elige un nivel válido.',
            'origen.in'        => 'Elige un origen válido.',
            'geojson.required' => 'Pega el GeoJSON del área inundada.',
            'geojson.max'      => 'El GeoJSON es demasiado grande.',
        ];
    }
}