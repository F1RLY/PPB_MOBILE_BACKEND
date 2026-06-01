<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya user yang sudah login (dijamin oleh middleware auth:sanctum di route)
        return true;
    }

    public function rules(): array
    {
        return [
            'rating'      => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Rating wajib diisi.',
            'rating.min'      => 'Rating minimal 1 bintang.',
            'rating.max'      => 'Rating maksimal 5 bintang.',
        ];
    }

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
}