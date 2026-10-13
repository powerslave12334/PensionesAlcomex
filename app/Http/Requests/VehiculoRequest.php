<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehiculoId = $this->route('vehiculo')?->id;
        $unico = fn (string $campo) => $vehiculoId ? "unique:vehiculos,{$campo},{$vehiculoId}" : "unique:vehiculos,{$campo}";

        return [
            'tipo_vehiculo_id' => ['required', 'exists:tipos_vehiculo,id'],
            'placas'           => ['required', 'string', 'max:20', $unico('placas')],
            'marca'            => ['nullable', 'string', 'max:80'],
            'modelo'           => ['nullable', 'string', 'max:80'],
            'anio'             => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'color'            => ['nullable', 'string', 'max:50'],
            'vin'              => ['nullable', 'string', 'max:50', $unico('vin')],
            'numero_motor'     => ['nullable', 'string', 'max:100'],
            'foto'             => ['nullable', 'image', 'max:2048'],
            'observaciones'    => ['nullable', 'string'],
            'activo'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'placas.required'  => 'Las placas son obligatorias.',
            'placas.unique'    => 'Ya existe un vehículo con esas placas.',
            'tipo_vehiculo_id.exists' => 'El tipo de vehículo no existe.',
            'foto.image'       => 'La fotografía debe ser un archivo de imagen (jpg, png o webp).',
            'foto.max'         => 'La fotografía no puede superar 2 MB.',
        ];
    }
}
