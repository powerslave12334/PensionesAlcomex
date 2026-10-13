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
            ],
            'fecha_pago'     => ['required', 'date'],
            // El periodo lo define el servidor: desde el inicio de la pensión
            // hasta el día del pago (ya no se captura «periodo hasta»).
            'periodo_inicio' => ['required', 'date'],
            'subtotal'       => ['required', 'numeric', 'min:0.01'],
            'descuento'      => ['nullable', 'numeric', 'min:0', 'lte:subtotal'],
            'impuesto'       => ['nullable', 'numeric', 'min:0'],
            'total'          => ['required', 'numeric', 'min:0'],
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
