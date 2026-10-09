<?php

namespace App\Http\Requests;

use App\Models\Tarifa;
use Illuminate\Foundation\Http\FormRequest;

class TarifaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el permiso se valida en el controlador
    }

    public function rules(): array
    {
        return [
            'nombre'           => ['required', 'string', 'max:100'],
            'tipo_vehiculo_id' => ['required', 'integer', 'exists:tipos_vehiculo,id'],
            'tipo_tarifa'      => ['required', 'in:' . implode(',', Tarifa::TIPOS)],
            'precio'           => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'periodo'          => ['nullable', 'string', 'max:30'],
            'hora_inicio'      => ['nullable', 'date_format:H:i'],
            'hora_fin'         => ['nullable', 'date_format:H:i', 'after_or_equal:hora_inicio'],
            'vigencia_desde'   => ['nullable', 'date'],
            'vigencia_hasta'   => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'activo'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'            => 'El nombre de la tarifa es obligatorio.',
            'nombre.max'                 => 'El nombre no puede superar los 100 caracteres.',
            'tipo_vehiculo_id.required'  => 'Selecciona el tipo de vehículo.',
            'tipo_vehiculo_id.exists'    => 'El tipo de vehículo seleccionado no existe.',
            'tipo_tarifa.required'       => 'Selecciona el periodo de la tarifa.',
            'tipo_tarifa.in'             => 'El periodo seleccionado no es válido.',
            'precio.required'            => 'El precio es obligatorio.',
            'precio.numeric'             => 'El precio debe ser un número.',
            'precio.min'                 => 'El precio no puede ser negativo.',
            'hora_fin.after_or_equal'    => 'La hora final debe ser igual o posterior a la inicial.',
            'vigencia_hasta.after_or_equal' => 'La vigencia final debe ser igual o posterior al inicio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre'           => 'nombre de la tarifa',
            'tipo_vehiculo_id' => 'tipo de vehículo',
            'tipo_tarifa'      => 'periodo',
            'precio'           => 'precio',
            'periodo'          => 'descripción del período',
            'hora_inicio'      => 'hora inicial',
            'hora_fin'         => 'hora final',
            'vigencia_desde'   => 'vigencia desde',
            'vigencia_hasta'   => 'vigencia hasta',
        ];
    }
}
