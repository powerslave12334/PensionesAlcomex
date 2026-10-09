<?php

namespace App\Http\Requests;

use App\Models\ContratoPension;
use App\Models\Tarifa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContratoPensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el permiso se valida en el controlador
    }

    public function rules(): array
    {
        return [
            'cliente_id'   => ['required', 'integer', 'exists:clientes,id'],
            // el vehículo debe pertenecer al cliente elegido
            'vehiculo_id'  => [
                'required', 'integer',
                Rule::exists('vehiculos', 'id')->where('cliente_id', $this->input('cliente_id')),
            ],
            // US-0302: solo tarifas vigentes (si no hay, la lista queda vacía y falla)
            'tarifa_id'    => ['required', 'integer', Rule::in(Tarifa::vigente()->pluck('id')->all())],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'precio'       => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'periodicidad' => ['required', 'in:' . implode(',', ContratoPension::PERIODICIDADES)],
            'dia_cobro'    => ['nullable', 'integer', 'min:1', 'max:31'],
            'deposito'     => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'observaciones'=> ['nullable', 'string', 'max:1000'],
            'espacio_id'   => ['nullable', 'integer', 'exists:espacios,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required'   => 'Selecciona el cliente.',
            'cliente_id.exists'     => 'El cliente seleccionado no existe.',
            'vehiculo_id.required'  => 'Selecciona el vehículo.',
            'vehiculo_id.exists'    => 'El vehículo no pertenece al cliente seleccionado.',
            'tarifa_id.required'    => 'Selecciona la tarifa.',
            'tarifa_id.in'          => 'La tarifa seleccionada no está vigente: elige una tarifa vigente.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'     => 'La fecha de inicio no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'precio.required'       => 'El precio es obligatorio (es el precio congelado del contrato).',
            'precio.numeric'        => 'El precio debe ser un número.',
            'precio.min'            => 'El precio no puede ser negativo.',
            'periodicidad.required' => 'Selecciona la periodicidad.',
            'periodicidad.in'       => 'La periodicidad seleccionada no es válida.',
            'dia_cobro.between'     => 'El día de cobro debe estar entre 1 y 31.',
            'deposito.min'          => 'El depósito no puede ser negativo.',
            'espacio_id.exists'     => 'El cajón seleccionado no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id'   => 'cliente',
            'vehiculo_id'  => 'vehículo',
            'tarifa_id'    => 'tarifa',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin'    => 'fecha final',
            'precio'       => 'precio',
            'periodicidad' => 'periodicidad',
            'dia_cobro'    => 'día de cobro',
            'deposito'     => 'depósito en garantía',
            'espacio_id'   => 'cajón',
        ];
    }
}
