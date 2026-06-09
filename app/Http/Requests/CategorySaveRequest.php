<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CategorySaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'super_admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('id') !== null;
        return [
            'name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id', 
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', 
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:1048', 
            'is_visible' => 'sometimes|boolean',
            'order_position' => 'sometimes|integer',
        ];
    }
}
