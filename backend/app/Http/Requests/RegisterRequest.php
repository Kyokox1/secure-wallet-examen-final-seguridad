<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// RS-04: validación estricta, whitelist de campos, rechazo de mass assignment no esperado.
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_completo' => ['required', 'string', 'max:150'],
            'ci' => ['required', 'string', 'max:20', 'unique:users,ci'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'telefono' => ['required', 'string', 'max:20', 'unique:users,telefono'],
            // RS-07: política de contraseñas (min 10, mayus, minus, dígito, símbolo)
            'password' => [
                'required', 'string', 'min:10',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
            ],
            'captcha_token' => ['required', 'string'], // RS-08
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'La contraseña debe incluir mayúscula, minúscula, dígito y símbolo.',
        ];
    }
}
