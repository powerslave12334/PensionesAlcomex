<?php

namespace App\Http\Requests;

use App\Models\MovimientoAcceso;
use App\Models\Tarifa;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SalidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el controlador autoriza: accesos.registrar (+ cobrar si cobra)
    }

    public function rules(): array
    {
        return [
            'fecha_hora' => [
                'required', 'date',
                // nunca antes de la entrada, nunca en el futuro
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || strtotime($value) === false) {
                        return;
                    }

                    $salida = Carbon::parse($value);
                    $entrada = $this->route('acceso');

                    if ($entrada instanceof MovimientoAcceso && $salida->lt($entrada->fecha_hora)) {
                        $fail('La salida no puede ser anterior a la entrada ('
                            . $entrada->fecha_hora->format('d/m/Y H:i') . ').');
                    }

                    if ($salida->greaterThan(now()->addMinutes(5))) {
                        $fail('La fecha y hora de salida no puede ser futura.');
                    }
                },
            ],
            'puerta_id' => ['nullable', 'integer', Rule::exists('puertas', 'id')->where('activo', true)],
            // US-0502: tarifa de visita (si es pensionado el controlador la ignora)
            'tarifa_id' => [
                'nullable', 'integer', Rule::exists('tarifas', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $tarifa = Tarifa::find($value);
                    if (! $tarifa) {
                        return;
                    }

                    if (! in_array($tarifa->tipo_tarifa, ['HORA', 'DIA', 'NOCHE'], true)) {
                        $fail('Solo se pueden aplicar tarifas de permanencia (por hora, día o noche).');
                        return;
                    }

                    if (! $tarifa->esVigente()) {
                        $fail("La tarifa «{$tarifa->nombre}» no está vigente hoy.");
                    }
                },
            ],
            'estado_cobro' => ['required', 'in:' . implode(',', MovimientoAcceso::ESTADOS_COBRO)],
            // solo se llena si el cobro va a quedarse como COBRADO
            'metodo_pago_id' => [
                'required_if:estado_cobro,COBRADO', 'nullable', 'integer',
                Rule::exists('metodos_pago', 'id')->where('activo', true),
            ],
            'foto_salida'   => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_hora'     => 'fecha y hora de salida',
            'estado_cobro'   => 'estado del cobro',
            'metodo_pago_id' => 'método de pago',
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_hora.required'        => 'Indica la fecha y hora de la salida.',
            'fecha_hora.date'            => 'La fecha y hora de la salida no es válida.',
            'puerta_id.exists'           => 'Esa puerta no existe.',
            'tarifa_id.exists'           => 'Esa tarifa no existe.',
            'estado_cobro.required'      => 'Indica el estado del cobro.',
            'estado_cobro.in'            => 'Ese estado de cobro no es válido.',
            'metodo_pago_id.required_if' => 'Selecciona con qué método se cobró.',
            'metodo_pago_id.exists'      => 'Ese método de pago no existe o está inactivo.',
            'foto_salida.max'            => 'La URL de la foto no puede exceder 500 caracteres.',
            'observaciones.max'          => 'Las observaciones no pueden exceder 1000 caracteres.',
        ];
    }
}
