<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobPaymentCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "booking_id" => "required|numeric",
            "garage_id" => "required|numeric",
            "payments" => "required|array",
            "payments.*.payment_type" => "required|string",
            "payments.*.amount" => "required|numeric",
        ];
    }
}
