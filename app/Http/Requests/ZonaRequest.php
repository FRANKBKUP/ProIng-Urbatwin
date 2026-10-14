<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tieneRol('Administrador') ?? false; // RN06
    }

    public function rules(): array
    {
        return [
            'nombre'      => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            // Al crear el límite es obligatorio; al editar, vacío significa «no cambiar»
            'geojson'     => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'  => 'El nombre es obligatorio.',
            'geojson.required' => 'Pega el GeoJSON del límite de la zona.',
        ];
    }
}