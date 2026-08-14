<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 1. البيانات المشتركة (موجودة عند البائع والمشتري)
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|numeric|digits:10|unique:users,phone',
            'password' => 'required|string|min:8|confirmed|regex:/[A-Z]/|regex:/[a-z]/|regex:/[0-9]/',

            // 2. حقول خاصة بكل أنواع البائعين (Vendor + Wholesale)
            'role' => 'required|in:vendor,wholesale',
            'store_name' => 'required|string|max:255',
            'category' => 'required|string',
            'id_card_photo' => 'required|image|max:2048',
            'store_logo' => 'nullable|image|max:2048',

            // 3. حقول "إضافية" فقط لبائع الجملة (Wholesale)
            'tax_number' => [
                'nullable',
                'prohibited_if:role,vendor',
                'required_if:role,wholesale',
                'exclude_if:role,vendor',
                'digits:12'
            ],

            'commercial_registration_number' => [
                'nullable',
                'prohibited_if:role,vendor',
                'required_if:role,wholesale',
                'exclude_if:role,vendor',
                'string',
                'max:255'
            ],

            'commercial_record_photo' => [
                'nullable',
                'prohibited_if:role,vendor',
                'required_if:role,wholesale',
                'exclude_if:role,vendor',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Required fields for Wholesale
            'tax_number.required_if' => 'The tax number field is required for wholesale sellers.',
            'commercial_registration_number.required_if' => 'The commercial registration number is required for wholesale sellers.',
            'commercial_record_photo.required_if' => 'The commercial record photo is required for wholesale sellers.',

            // Prohibited fields for Vendor
            'tax_number.prohibited' => 'The tax number is only allowed for wholesale accounts.',
            'commercial_registration_number.prohibited' => 'The commercial registration number is only allowed for wholesale accounts.',
            'commercial_record_photo.prohibited' => 'The commercial record photo is only allowed for wholesale accounts.',

            // Other validation messages
            'tax_number.digits' => 'The tax number must be exactly 12 digits.',
            'password.regex' => 'The password must contain uppercase and lowercase letters, and at least one number.',
            'phone.digits' => 'The phone number must be exactly 10 digits.',
        ];
    }
}