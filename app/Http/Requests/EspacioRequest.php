<?php

namespace App\Http\Requests;

use App\Models\Espacio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EspacioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $espacioId = $this->route('espacio')?->id;
        $zonaId    = $this->input('zona_id');

        return [
            'zona_id'          => ['required', 'exists:zonas,id'],
            'codigo'           => ['required', 'string', 'max:30',
                                   Rule::unique('espacios', 'codigo')
                                       ->where('zona_id', $zonaId)
                                       ->ignore($espacioId)],
            'tipo_vehiculo_id' => ['required', 'exists:tipos_vehiculo,id'],
            'estado'           => ['required', Rule::in(Espacio::ESTADOS)],
            'numero'           => ['nullable', 'string', 'max:20'],
            'piso'             => ['nullable', 'string', 'max:20'],
            'observaciones'    => ['nullable', 'string'],
            'activo'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'zona_id.required'          => 'Seleccione una zona.',
            'zona_id.exists'            => 'La zona seleccionada no existe.',
            'codigo.required'           => 'El código del cajón es obligatorio.',
            'codigo.unique'             => 'Ya existe un cajón con ese código en esta zona.',
            'tipo_vehiculo_id.required' => 'Seleccione el tipo de vehículo compatible.',
            'tipo_vehiculo_id.exists'   => 'El tipo de vehículo no existe.',
            'estado.in'                 => 'Estado no válido.',
        ];
    }
}
