<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuyerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // مسموح للجميع بالوصول لهذا الـ Request
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => [
                'required',
                'numeric',
                'digits:10',
                'unique:users,phone'
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',      // حرف كبير
                'regex:/[a-z]/',      // حرف صغير
                'regex:/[0-9]/',      // رقم
            ],
            'profile_photo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'id_card_photo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'phone.unique' => 'This phone number is already registered with us.',
            'email.unique' => 'This email address is already registered with us.',
            'phone.digits' => 'The phone number must be exactly 10 digits.',
        ];
    }
}
