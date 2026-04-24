<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VendorRegisterRequest extends FormRequest
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
            'display_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => [
                'required',
                'numeric',           // يضمن أن القيمة أرقام فقط
                'digits:10',         // يضمن أن يكون العدد 10 أرقام بالضبط
                'unique:users,phone' // التأكد أنه غير مكرر في جدول المستخدمين
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',      // حرف كبير واحد على الأقل
                'regex:/[a-z]/',      // حرف صغير واحد على الأقل
                'regex:/[0-9]/',      // رقم واحد على الأقل
            ],
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
            'category' => 'required|string',

            'payout_method' => [
                'required',
                'in:wallet,cash'
            ],

            'payout_account' => [
                'required',
                'string',
                // إذا اختار wallet: يجب أن يكون رقم (numeric) ومكون من 10 خانات (digits:10)
                // والأهم: يجب أن يطابق تماماً الرقم الموجود في حقل الـ phone
                $this->payout_method === 'wallet' ? 'numeric' : '',
                $this->payout_method === 'wallet' ? 'digits:10' : '',
                $this->payout_method === 'wallet' ? 'same:phone' : '', // 👈 هذا هو السطر المطلوب

                // إذا اختار cash: يجب أن يكتب manual
                $this->payout_method === 'cash' ? 'in:manual,Manual' : '',
            ],

            'wallet_pin' => 'required|digits:4|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            // في حال كان الدفع كاش، يجب أن يحتوي الحقل على كلمة manual حصراً
            'payout_account.in' => 'For cash payments, the account field must contain the word "manual".',

            // في حال كان الدفع عبر المحفظة، يجب أن يكون الحساب عبارة عن رقم موبايل من 10 خانات
            'payout_account.digits' => 'For wallet payments, the account must be a valid 10-digit mobile number.',

            // في حال كان الدفع عبر المحفظة، يجب التأكد أن المدخلات أرقام فقط وليست أحرفاً
            'payout_account.numeric' => 'For wallet payments, the account must contain numbers only.',

            // شرط قوة كلمة المرور: يجب أن تحتوي على حرف كبير، حرف صغير، ورقم
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',

            // يجب أن يكون رقم الهاتف المعتمد في الحساب مكوناً من 10 أرقام بالضبط
            'phone.digits' => 'The phone number must be exactly 10 digits.',
        ];
    }
}
