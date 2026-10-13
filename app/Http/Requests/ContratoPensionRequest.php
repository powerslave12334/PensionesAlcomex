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
            // Cliente: tres campos planos (sin buscador ni alta rápida);
            // el servidor liga al cliente por nombre + apellidos o lo crea
            'cliente_nombre'   => ['required', 'string', 'max:100'],
            'cliente_apellido' => ['required', 'string', 'max:100'],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],

            // Datos de la empresa que paga la unidad (ajuste del alta)
            'nombre_empresa'    => ['required', 'string', 'max:150'],
            'contacto_empresa'  => ['required', 'string', 'max:100'],
            'direccion_empresa' => ['required', 'string', 'max:255'],

            // Unidad capturada en línea (se crea o reutiliza por placas)
            'tipo_vehiculo_id' => ['required', 'integer', 'exists:tipos_vehiculo,id'],
            'placas'           => ['required', 'string', 'max:20'],
            'marca'            => ['required', 'string', 'max:80'],
            'modelo'           => ['required', 'string', 'max:80'],
            'color'            => ['required', 'string', 'max:50'],

            // Documentación entregada: una sola de las tres
            'documentacion' => ['required', Rule::in(ContratoPension::DOCUMENTACIONES)],

            // Entrada automática (se llena con now() si viene vacía)
            'fecha_entrada' => ['nullable', 'date'],

            // US-0302: solo tarifas vigentes (si no hay, la lista queda vacía y falla)
            'tarifa_id'    => ['required', 'integer', Rule::in(Tarifa::vigente()->pluck('id')->all())],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'precio'       => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'deposito'     => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'observaciones'=> ['nullable', 'string', 'max:1000'],
            'espacio_id'   => ['nullable', 'integer', 'exists:espacios,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_nombre.required'   => 'Escribe el nombre del cliente.',
            'cliente_apellido.required' => 'Escribe los apellidos del cliente.',
            'nombre_empresa.required'        => 'El nombre de la empresa es obligatorio.',
            'contacto_empresa.required'      => 'Escribe el número de la empresa o del encargado de la unidad.',
            'direccion_empresa.required'     => 'La dirección es obligatoria.',
            'tipo_vehiculo_id.required'      => 'Selecciona el tipo de unidad.',
            'tipo_vehiculo_id.exists'        => 'El tipo de unidad no existe.',
            'placas.required'                => 'Las placas son obligatorias.',
            'marca.required'                 => 'La marca es obligatoria.',
            'modelo.required'                => 'El modelo es obligatorio.',
            'color.required'                 => 'El color es obligatorio.',
            'documentacion.required'         => 'Selecciona la documentación entregada.',
            'documentacion.in'               => 'La documentación seleccionada no es válida.',
            'tarifa_id.required'             => 'Selecciona la tarifa.',
            'tarifa_id.in'                   => 'La tarifa seleccionada no está vigente: elige una tarifa vigente.',
            'fecha_inicio.required'          => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'              => 'La fecha de inicio no es válida.',
            'fecha_entrada.date'             => 'La fecha y hora de entrada no es válida.',
            'fecha_fin.after_or_equal'       => 'La fecha final debe ser igual o posterior a la inicial.',
            'precio.required'                => 'El precio es obligatorio (es el precio congelado del contrato).',
            'precio.numeric'                 => 'El precio debe ser un número.',
            'precio.min'                     => 'El precio no puede ser negativo.',
            'deposito.min'                   => 'El depósito no puede ser negativo.',
            'espacio_id.exists'              => 'El cajón seleccionado no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_nombre'   => 'nombre del cliente',
            'cliente_apellido' => 'apellidos del cliente',
            'cliente_telefono' => 'teléfono del cliente',
            'nombre_empresa'     => 'nombre de la empresa',
            'contacto_empresa'   => 'número de la empresa o encargado',
            'direccion_empresa'  => 'dirección',
            'tipo_vehiculo_id'   => 'tipo de unidad',
            'placas'             => 'placas',
            'marca'              => 'marca',
            'modelo'             => 'modelo',
            'color'              => 'color',
            'documentacion'      => 'documentación',
            'fecha_entrada'      => 'fecha y hora de entrada',
            'tarifa_id'          => 'tarifa',
            'fecha_inicio'       => 'fecha de inicio',
            'fecha_fin'          => 'fecha final',
            'precio'             => 'precio',
            'deposito'           => 'depósito en garantía',
            'espacio_id'         => 'cajón',
        ];
    }
}
