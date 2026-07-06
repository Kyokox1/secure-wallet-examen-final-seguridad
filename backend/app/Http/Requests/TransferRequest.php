<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'destinatario' => ['required', 'string', 'max:150'],
            'monto' => ['required', 'numeric', 'min:1.00', 'max:5000.00'],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ];
    }
}
