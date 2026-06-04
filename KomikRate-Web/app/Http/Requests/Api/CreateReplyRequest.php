<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * Hanya user yang sudah login (dijamin oleh middleware auth:sanctum)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'content'         => ['required', 'string', 'min:1', 'max:500'],
            'parent_reply_id' => ['nullable', 'integer', 'exists:replies,id'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'content.required'      => 'Konten reply wajib diisi.',
            'content.min'           => 'Konten reply minimal 1 karakter.',
            'content.max'           => 'Konten reply maksimal 500 karakter.',
            'parent_reply_id.exists' => 'Reply parent tidak ditemukan.',
        ];
    }


    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
    /**
     * Prepare the data for validation.
     */

    
    protected function prepareForValidation(): void
    {
        $content = $this->content ?? '';
        
        // Kalau content adalah JSON string seperti {"content":"tes"}, decode dulu
        if (str_starts_with($content, '{')) {
            $decoded = json_decode($content, true);
            if (isset($decoded['content'])) {
                $content = $decoded['content'];
            }
        }
    
    $this->merge([
        'content' => trim($content),
    ]);
}
}