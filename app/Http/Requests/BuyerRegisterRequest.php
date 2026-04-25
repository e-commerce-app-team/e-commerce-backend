<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BuyerRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => [
                'required',
                'numeric',           // يضمن أن القيمة أرقام فقط
                'digits:10',         // يضمن أن يكون العدد 10 أرقام بالضبط
                'unique:users,phone' // التأكد أنه غير مكرر في جدول المستخدمين
            ],
            // التعديل الجديد على الـ password
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',      // حرف كبير واحد على الأقل
                'regex:/[a-z]/',      // حرف صغير واحد على الأقل
                'regex:/[0-9]/',      // رقم واحد على الأقل
            ],

            // التعديل الجديد على الـ profile_photo
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,gif,webp',
                'max:2048',
            ],
            'id_card_photo' => [
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,gif,webp',
                'max:2048',
            ],


        ];
    }
}