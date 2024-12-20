<?php

namespace App\Http\Requests;

use App\Rules\TimeValidation;
use App\Rules\ValidateExpert;
use Illuminate\Foundation\Http\FormRequest;

class BookingCreateRequest extends FormRequest
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
            'expert_id' => [
                'required',
                'numeric',
                 new ValidateExpert(NULL)
            ],
            "next_visit_date" => "nullable|date",
            "send_notification" => "nullable|boolean",

"payment_method" => "nullable|string",
   'booked_slots' => [
    'required',
    'array',
],
'booked_slots.*' => [
    'required',
    'date_format:g:i A',
],
"reason" => "nullable|string",

            'customer_id' => 'nullable|numeric|exists:users,id',
'first_Name' => 'nullable|required_without:customer_id|string|max:255',
'last_Name' => 'nullable|required_without:customer_id|string|max:255',
'phone' => 'nullable|string',

'booking_from' => [
    'nullable',
    'string',
],

            "garage_id" => "required|numeric|exists:garages,id",

            "additional_information" => "nullable|string",
            "expert_note" => "nullable|string",
            "receptionist_note" => "nullable|string",
            // "status",
            "job_start_date" => "required|date_format:Y-m-d",
            "job_start_time" => ['nullable','date_format:H:i', new TimeValidation
        ],
            // "job_end_date" => "required|date",
            "coupon_code" => "nullable|string",

    'booking_sub_service_ids' => 'nullable|array',
    'booking_sub_service_ids.*' => 'nullable|numeric',

    'booking_garage_package_ids' => 'nullable|array',
    'booking_garage_package_ids.*' => 'nullable|numeric',


"discount_type" => "nullable|string|in:fixed,percentage",
"discount_amount" => "required_if:discount_type,!=,null|numeric|min:0",

"tip_type" => "nullable|string|in:fixed,percentage",
"tip_amount" => "required_if:tip_type,!=,null|numeric|min:0",

        ];
    }
}
