<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipoId = $this->route('tipo')?->id;

        return [
            'nombre'      => ['required', 'string', 'max:80',
                              Rule::unique('tipos_vehiculo', 'nombre')->ignore($tipoId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tarifa_base' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'activo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'      => 'El nombre del tipo es obligatorio.',
            'nombre.unique'        => 'Ya existe un tipo de vehículo con ese nombre.',
            'tarifa_base.required' => 'La tarifa base es obligatoria.',
            'tarifa_base.numeric'  => 'La tarifa base debe ser un importe.',
        ];
    }
}
