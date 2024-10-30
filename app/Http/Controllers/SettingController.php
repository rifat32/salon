<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessSettingRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\BusinessSetting;
use Exception;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ErrorUtil,UserActivityUtil;
    /**
   *
   * @OA\Put(
   *      path="/v1.0/business-settingss",
   *      operationId="updateBusinessSettings",
   *      tags={"setting"},
   *       security={
   *           {"bearerAuth": {}}
   *       },
   *      summary="This method is to update busuness setting",
   *      description="This method is to update busuness setting",
   *
   *  @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *
   * *         @OA\Property(property="STRIPE_KEY", type="string", format="string",example="STRIPE_KEY"),
   *           @OA\Property(property="STRIPE_SECRET", type="string", format="string",example="STRIPE_SECRET"),
   *  *   @OA\Property(property="stripe_enabled", type="boolean", example=true),
   *      @OA\Property(property="is_expert_price", type="boolean", example=true),
 *   @OA\Property(property="allow_pay_after_service", type="boolean", example=false),
 *   @OA\Property(property="allow_expert_booking", type="boolean", example=true),
 *   @OA\Property(property="allow_expert_self_busy", type="boolean", example=true),
 *   @OA\Property(property="allow_expert_booking_cancel", type="boolean", example=false),
 *   @OA\Property(property="allow_expert_view_revenue", type="boolean", example=true),
 *   @OA\Property(property="allow_expert_view_customer_details", type="boolean", example=false),
 *   @OA\Property(property="allow_receptionist_add_question", type="boolean", example=true),
 *   @OA\Property(property="default_currency", type="string", format="string", example="USD"),
 *   @OA\Property(property="default_language", type="string", format="string", example="en"),
 *   @OA\Property(property="vat_enabled", type="boolean", example=true),
 *   @OA\Property(property="vat_percentage", type="number", format="float", example=15.00)
   *
   *
   *         ),
   *      ),
   *      @OA\Response(
   *          response=200,
   *          description="Successful operation",
   *       @OA\JsonContent(),
   *       ),
   *      @OA\Response(
   *          response=401,
   *          description="Unauthenticated",
   * @OA\JsonContent(),
   *      ),
   *        @OA\Response(
   *          response=422,
   *          description="Unprocesseble Content",
   *    @OA\JsonContent(),
   *      ),
   *      @OA\Response(
   *          response=403,
   *          description="Forbidden",
   *   @OA\JsonContent()
   * ),
   *  * @OA\Response(
   *      response=400,
   *      description="Bad Request",
   *   *@OA\JsonContent()
   *   ),
   * @OA\Response(
   *      response=404,
   *      description="not found",
   *   *@OA\JsonContent()
   *   )
   *      )
   *     )
   */

   public function updateBusinessSettings(UpdateBusinessSettingRequest $request)
   {

       try {
           $this->storeActivity($request, "DUMMY activity","DUMMY description");
           if (!$request->user()->hasPermissionTo('business_setting_update')) {
               return response()->json([
                   "message" => "You can not perform this action"
               ], 401);
           }
           $request_data = $request->validated();




           if(!empty($request_data['stripe_enabled'])){
 // Verify the Stripe credentials before updating
$stripeValid = false;

try {
    // Set Stripe client with the provided secret
    $stripe = new \Stripe\StripeClient($request_data['STRIPE_SECRET']);

    // Make a test API call (for example, retrieve account details)
    $account = $stripe->accounts->retrieve('me');

    // Validate the provided STRIPE_KEY by checking if it matches the account's public key
    if ($account->keys->secret === $request_data['STRIPE_KEY']) {
        // If the request is successful and the keys match, mark the Stripe credentials as valid
        $stripeValid = true;
    } else {
        return response()->json([
            "message" => "Invalid Stripe keys: The provided STRIPE_KEY does not match the associated account."
        ], 401);
    }
} catch (\Stripe\Exception\AuthenticationException $e) {
    // Handle invalid API key or secret
    return response()->json([
        "message" => "Invalid Stripe credentials: " . $e->getMessage()
    ], 401);
} catch (\Stripe\Exception\ApiConnectionException $e) {
    // Handle issues with connecting to Stripe's API
    return response()->json([
        "message" => "Network error while connecting to Stripe: " . $e->getMessage()
    ], 502);
} catch (\Stripe\Exception\InvalidRequestException $e) {
    // Handle invalid requests sent to Stripe
    return response()->json([
        "message" => "Invalid request to Stripe: " . $e->getMessage()
    ], 400);
} catch (\Exception $e) {
    // Handle other exceptions related to Stripe
    return response()->json([
        "message" => "An error occurred while verifying Stripe credentials: " . $e->getMessage()
    ], 500);
}
           }


$busunessSetting = BusinessSetting::
where([
  "business_id" => auth()->user()->business_id
])
->first();

if (!$busunessSetting) {
    BusinessSetting::create($request_data);
}

              $busunessSetting->fill(collect($request_data)->only([
                'STRIPE_KEY',
                "STRIPE_SECRET",
                "business_id",
                'stripe_enabled',
                'is_expert_price',
                'is_auto_booking_approve',
                'allow_pay_after_service',
                'allow_expert_booking',
                'allow_expert_self_busy',
                'allow_expert_booking_cancel',
                'allow_expert_view_revenue',
                'allow_expert_view_customer_details',
                'allow_receptionist_add_question',
                'default_currency',
                'default_language',
                'vat_enabled',
                'vat_percentage'
              ])->toArray());
              $busunessSetting->save();

              $busunessSettingArray = $busunessSetting->toArray();

              $busunessSettingArray["STRIPE_KEY"] = $busunessSetting->STRIPE_KEY;
              $busunessSettingArray["STRIPE_SECRET"] = $busunessSetting->STRIPE_SECRET;

           return response()->json($busunessSettingArray, 200);
       } catch (Exception $e) {
           error_log($e->getMessage());
           return $this->sendError($e, 500, $request);
       }
   }

/**
   *
   * @OA\Get(
   *      path="/v1.0/business-settingss",
   *      operationId="getBusinessSettings",
   *      tags={"setting"},
   *       security={
   *           {"bearerAuth": {}}
   *       },
   *      summary="This method is to get busuness _setting",
   *      description="This method is to get busuness setting",
   *
   *      @OA\Response(
   *          response=200,
   *          description="Successful operation",
   *       @OA\JsonContent(),
   *       ),
   *      @OA\Response(
   *          response=401,
   *          description="Unauthenticated",
   * @OA\JsonContent(),
   *      ),
   *        @OA\Response(
   *          response=422,
   *          description="Unprocesseble Content",
   *    @OA\JsonContent(),
   *      ),
   *      @OA\Response(
   *          response=403,
   *          description="Forbidden",
   *   @OA\JsonContent()
   * ),
   *  * @OA\Response(
   *      response=400,
   *      description="Bad Request",
   *   *@OA\JsonContent()
   *   ),
   * @OA\Response(
   *      response=404,
   *      description="not found",
   *   *@OA\JsonContent()
   *   )
   *      )
   *     )
   */

   public function getBusinessSettings(Request $request)
   {
       try {
           $this->storeActivity($request, "DUMMY activity","DUMMY description");
           if (!$request->user()->hasPermissionTo('business_setting_view')) {
               return response()->json([
                   "message" => "You can not perform this action"
               ], 401);
           }


           $busunessSetting = BusinessSetting::
           where([
               "business_id" => auth()->user()->business_id
           ])
           ->first();

           $busunessSettingArray = $busunessSetting->toArray();

           $busunessSettingArray["STRIPE_KEY"] = $busunessSetting->STRIPE_KEY;
           $busunessSettingArray["STRIPE_SECRET"] = $busunessSetting->STRIPE_SECRET;


           return response()->json($busunessSettingArray, 200);
       } catch (Exception $e) {

           return $this->sendError($e, 500, $request);
       }
   }

/**
   *
   * @OA\Get(
   *      path="/v1.0/client/business-settingss",
   *      operationId="getBusinessSettingsClient",
   *      tags={"setting"},
   *       security={
   *           {"bearerAuth": {}}
   *       },

   *              @OA\Parameter(
   *         name="per_page",
   *         in="query",
   *         description="per_page",
   *         required=true,
   *  example="6"
   *      ),
   *      * *  @OA\Parameter(
   * name="start_date",
   * in="query",
   * description="start_date",
   * required=true,
   * example="2019-06-29"
   * ),
   * *  @OA\Parameter(
   * name="end_date",
   * in="query",
   * description="end_date",
   * required=true,
   * example="2019-06-29"
   * ),
   * *  @OA\Parameter(
   * name="search_key",
   * in="query",
   * description="search_key",
   * required=true,
   * example="search_key"
   * ),
   *    * *  @OA\Parameter(
   * name="business_tier_id",
   * in="query",
   * description="business_tier_id",
   * required=true,
   * example="1"
   * ),
   * *  @OA\Parameter(
   * name="order_by",
   * in="query",
   * description="order_by",
   * required=true,
   * example="ASC"
   * ),

   *      summary="This method is to get Business_setting",
   *      description="This method is to get Business_setting",
   *

   *      @OA\Response(
   *          response=200,
   *          description="Successful operation",
   *       @OA\JsonContent(),
   *       ),
   *      @OA\Response(
   *          response=401,
   *          description="Unauthenticated",
   * @OA\JsonContent(),
   *      ),
   *        @OA\Response(
   *          response=422,
   *          description="Unprocesseble Content",
   *    @OA\JsonContent(),
   *      ),
   *      @OA\Response(
   *          response=403,
   *          description="Forbidden",
   *   @OA\JsonContent()
   * ),
   *  * @OA\Response(
   *      response=400,
   *      description="Bad Request",
   *   *@OA\JsonContent()
   *   ),
   * @OA\Response(
   *      response=404,
   *      description="not found",
   *   *@OA\JsonContent()
   *   )
   *      )
   *     )
   */

   public function getBusinessSettingsClient(Request $request)
   {
       try {
           $this->storeActivity($request, "DUMMY activity","DUMMY description");

           $busunessSetting = BusinessSetting::first();


           $busunessSettingArray["STRIPE_KEY"] = $busunessSetting->STRIPE_KEY;

           return response()->json($busunessSetting, 200);

       } catch (Exception $e) {

           return $this->sendError($e, 500, $request);
       }
   }








}
