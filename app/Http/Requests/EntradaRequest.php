<?php

namespace App\Http\Requests;

use App\Models\Espacio;
use App\Models\MovimientoAcceso;
use App\Models\Vehiculo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el controlador hace $this->authorize('accesos.registrar')
    }

    public function rules(): array
    {
        return [
            'vehiculo_id' => [
                'required', 'integer', Rule::exists('vehiculos', 'id')->where('activo', true),
                // US-0501: el vehículo debe estar activo y no puede tener otra
                // entrada abierta (una sola pareja entrada/salida).
                function (string $attribute, mixed $value, Closure $fail): void {
                    $vehiculo = Vehiculo::find($value);
                    if (! $vehiculo) {
                        return;
                    }

                    if (! $vehiculo->activo) {
                        $fail("El vehículo {$vehiculo->placas} está dado de baja: no puede entrar.");
                        return;
                    }

                    $abierta = MovimientoAcceso::abiertas()->where('vehiculo_id', $vehiculo->id)->first();
                    if ($abierta) {
                        $fail("El vehículo {$vehiculo->placas} ya está adentro (entrada "
                            . $abierta->fecha_hora->format('d/m/Y H:i') . "). Registra primero su salida.");
                    }
                },
            ],
            'fecha_hora' => [
                'required', 'date',
                // sin viajes en el tiempo (máx. 5 min de tolerancia de reloj)
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || strtotime($value) === false) {
                        return;
                    }
                    if (\Illuminate\Support\Carbon::parse($value)->greaterThan(now()->addMinutes(5))) {
                        $fail('La fecha y hora de entrada no puede ser futura.');
                    }
                },
            ],
            'metodo_acceso' => ['required', 'in:' . implode(',', MovimientoAcceso::METODOS)],
            'espacio_id' => [
                'nullable', 'integer', Rule::exists('espacios', 'id')->where('activo', true),
                // el cajón debe estar libre o ser el asignado al contrato del vehículo
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $espacio = Espacio::find($value);
                    if (! $espacio) {
                        return;
                    }

                    if ($espacio->estado === 'DISPONIBLE') {
                        return;
                    }

                    $vehiculo = Vehiculo::find((int) $this->input('vehiculo_id'));
                    $contrato = $vehiculo
                        ? $vehiculo->contratos()->where('estado', 'ACTIVA')->first()
                        : null;
                    $esElSuyo = $contrato
                        && $contrato->asignaciones()->where('espacio_id', $espacio->id)
                            ->whereNull('fecha_fin')->exists();

                    if (! $esElSuyo) {
                        $fail('El cajón ' . $espacio->codigo . ' está '
                            . strtolower($espacio->estado_label) . ': elige otro.');
                    }
                },
            ],
            'puerta_id' => [
                'nullable', 'integer',
                Rule::exists('puertas', 'id')->where('activo', true),
            ],
            'foto_entrada'  => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehiculo_id.required'   => 'Selecciona el vehículo que entra.',
            'vehiculo_id.exists'     => 'Ese vehículo no existe o está inactivo.',
            'fecha_hora.required'    => 'Indica la fecha y hora de la entrada.',
            'fecha_hora.date'        => 'La fecha y hora de la entrada no es válida.',
            'metodo_acceso.required' => 'Indica cómo se registró la entrada.',
            'metodo_acceso.in'       => 'Ese método de acceso no es válido.',
            'espacio_id.exists'      => 'Ese cajón no existe.',
            'puerta_id.exists'       => 'Esa puerta no existe.',
            'foto_entrada.max'       => 'La URL de la foto no puede exceder 500 caracteres.',
            'observaciones.max'      => 'Las observaciones no pueden exceder 1000 caracteres.',
        ];
    }
}
