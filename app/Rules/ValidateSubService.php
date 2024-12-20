<?php

namespace App\Rules;

use App\Models\SubService;
use Illuminate\Contracts\Validation\Rule;

class ValidateSubService implements Rule
{
 /**
            * Create a new rule instance.
            *
            * @return  void
            */

            protected $id;
           protected $errMessage;

           public function __construct($id)
           {
               $this->id = $id;
               $this->errMessage = "";
           }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
      $sub_service = SubService::where([
        "id" => $value,
        "business_id" => auth()->user()->business_id
      ])
      ->first();

      if(empty($sub_service)) {
       return false;
      } 

      return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The validation error message.';
    }
}
