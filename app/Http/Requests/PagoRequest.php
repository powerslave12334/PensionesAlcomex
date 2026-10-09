<?php

namespace App\Http\Requests;

use App\Models\ContratoPension;
use App\Models\MetodoPago;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el controlador hace $this->authorize('pagos.crear')
    }

    public function rules(): array
    {
        return [
            'contrato_pension_id' => [
                'required', 'integer', 'exists:contratos_pension,id',
                // US-0406 / integridad: no se cobra a un contrato cerrado
                function (string $attribute, mixed $value, Closure $fail): void {
                    $contrato = ContratoPension::find($value);
                    if ($contrato && in_array($contrato->estado, ['CANCELADA', 'FINALIZADA'], true)) {
                        $fail("El contrato {$contrato->numero_contrato} está cerrado: no se le puede cobrar.");
                    }
                },
            ],
            'metodo_pago_id' => [
                'required', 'integer',
                Rule::exists('metodos_pago', 'id')->where('activo', true),
                // US-0402: la referencia es obligatoria si el método lo pide.
                // Ojo: la regla vive aquí (y no en `referencia`) porque Laravel
                // omite las validaciones de un atributo vacío (null) salvo que
                // sean implícitas; `referencia` vacía no dispararía el closure.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $metodo = MetodoPago::find($value);
                    $valor  = trim((string) $this->input('referencia'));
                    if ($metodo?->requiere_referencia && $valor === '') {
                        $fail("El método «{$metodo->nombre}» requiere número de referencia o autorización.");
                    }
                },
            ],
            'fecha_pago'     => ['required', 'date'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fin'    => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'subtotal'       => ['required', 'numeric', 'min:0.01'],
            'descuento'      => ['nullable', 'numeric', 'min:0', 'lte:subtotal'],
            'impuesto'       => ['nullable', 'numeric', 'min:0'],
            'total'          => ['required', 'numeric', 'min:0'],
            // US-0402: la referencia es obligatoria si el método lo pide
            'referencia'     => ['nullable', 'string', 'max:100'],
            'estado'        => ['required', 'in:PENDIENTE,PAGADO'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'contrato_pension_id.required' => 'Selecciona el contrato que se está cobrando.',
            'contrato_pension_id.exists'   => 'Ese contrato no existe.',
            'metodo_pago_id.required'      => 'Selecciona el método de pago.',
            'metodo_pago_id.exists'        => 'Ese método de pago no existe o está inactivo.',
            'fecha_pago.required'          => 'Indica la fecha del pago.',
            'fecha_pago.date'              => 'La fecha del pago no es válida.',
            'periodo_inicio.required'      => 'Indica el inicio del periodo que cubre el pago.',
            'periodo_fin.required'         => 'Indica el fin del periodo que cubre el pago.',
            'periodo_fin.after_or_equal'   => 'El fin del periodo debe ser igual o posterior al inicio.',
            'subtotal.required'            => 'El subtotal no puede estar vacío.',
            'subtotal.numeric'             => 'El subtotal debe ser un importe numérico.',
            'subtotal.min'                 => 'El subtotal debe ser mayor que cero.',
            'descuento.numeric'            => 'El descuento debe ser un importe numérico.',
            'descuento.lte'                => 'El descuento no puede superar el subtotal.',
            'impuesto.numeric'             => 'El impuesto debe ser un importe numérico.',
            'total.numeric'                => 'El total debe ser un importe numérico.',
            'total.min'                    => 'El total no puede ser negativo.',
            'referencia.max'               => 'La referencia no puede exceder 100 caracteres.',
            'estado.required'              => 'Indica el estado del cobro.',
            'estado.in'                    => 'Ese estado de pago no es válido.',
        ];
    }
}
