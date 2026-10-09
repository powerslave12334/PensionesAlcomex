<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la autorización se aplica en rutas/vistas con @can
    }

    public function rules(): array
    {
        $clienteId = $this->route('cliente')?->id;

        return [
            'nombre'           => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'telefono'         => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:150', 'unique:clientes,email' . ($clienteId ? ",{$clienteId}" : '')],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'ciudad'           => ['nullable', 'string', 'max:100'],
            'estado'           => ['nullable', 'string', 'max:100'],
            'codigo_postal'    => ['nullable', 'string', 'max:10'],
            'rfc'              => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'observaciones'    => ['nullable', 'string'],
            'activo'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe un cliente con ese correo.',
        ];
    }
}
