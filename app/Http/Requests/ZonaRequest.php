<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $zonaId = $this->route('zona')?->id;

        return [
            'nombre'      => ['required', 'string', 'max:80',
                              Rule::unique('zonas', 'nombre')->ignore($zonaId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la zona es obligatorio.',
            'nombre.unique'   => 'Ya existe una zona con ese nombre.',
        ];
    }
}
