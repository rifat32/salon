<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestUserRegisterRequest;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\ImageUploadRequestInBase64;
use App\Http\Requests\UserCreateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Requests\UserUpdateProfileRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Mail\VerifyMail;
use App\Models\Booking;
use App\Models\User;
use App\Models\UserTranslation;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;


// eeeeee
class UserManagementController extends Controller
{
    use ErrorUtil,UserActivityUtil;



       /**
        *
     * @OA\Post(
     *      path="/v1.0/user-image",
     *      operationId="createUserImage",
     *      tags={"user_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user image ",
     *      description="This method is to store user image",
     *
   *  @OA\RequestBody(
        *   * @OA\MediaType(
*     mediaType="multipart/form-data",
*     @OA\Schema(
*         required={"image"},
*         @OA\Property(
*             description="image to upload",
*             property="image",
*             type="file",
*             collectionFormat="multi",
*         )
*     )
* )



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

    public function createUserImage(ImageUploadRequest $request)
    {
        try{
            $this->storeActivity($request,"");
            // if(!$request->user()->hasPermissionTo('user_create')){
            //      return response()->json([
            //         "message" => "You can not perform this action"
            //      ],401);
            // }

            $request_data = $request->validated();

            $location =  config("setup-config.user_image_location");

            $new_file_name = time() . '_' . str_replace(' ', '_', $request_data["image"]->getClientOriginalName());

            $request_data["image"]->move(public_path($location), $new_file_name);


            return response()->json(["image" => $new_file_name,"location" => $location,"full_location"=>("/".$location."/".$new_file_name)], 200);


        } catch(Exception $e){
            error_log($e->getMessage());
        return $this->sendError($e,500,$request);
        }
    }
    public function createUserImageV2(ImageUploadRequestInBase64 $request)
{
    try {
        $this->storeActivity($request,"");

        // Decode the base64 image
        $base64Image = $request->validated()['image'];

        // Extract base64 string by removing metadata (if present)
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]); // Get the image extension (e.g., jpg, png, gif)
        } else {
            throw new \Exception('Invalid image format');
        }

        // Decode base64 to binary
        $base64Image = base64_decode($base64Image);
        if ($base64Image === false) {
            throw new \Exception('Base64 decode failed');
        }

        // Generate new file name
        $new_file_name = time() . '_' . uniqid() . '.' . $type;

        // Define the location to save the image
        $location = config("setup-config.user_image_location");

        // Save the image to the public path
        $file_path = public_path($location) . '/' . $new_file_name;
        file_put_contents($file_path, $base64Image);

        return response()->json([
            "image" => $new_file_name,
            "location" => $location,
            "full_location" => "/" . $location . "/" . $new_file_name
        ], 200);

    } catch (Exception $e) {
        error_log($e->getMessage());
        return $this->sendError($e, 500, $request);
    }
}






    /**
        *
     * @OA\Post(
     *      path="/v1.0/users",
     *      operationId="createUser",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user",
     *      description="This method is to store user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="Al"),
     *            @OA\Property(property="email", type="string", format="string",example="rifatalashwad0@gmail.com"),

     * *  @OA\Property(property="password", type="string", format="boolean",example="12345678"),
     *  * *  @OA\Property(property="password_confirmation", type="string", format="boolean",example="12345678"),
     *  * *  @OA\Property(property="phone", type="string", format="boolean",example="01771034383"),
     *  * *  @OA\Property(property="address_line_1", type="string", format="boolean",example="dhaka"),
     *  * *  @OA\Property(property="address_line_2", type="string", format="boolean",example="dinajpur"),
     *  * *  @OA\Property(property="country", type="string", format="boolean",example="Bangladesh"),
     *  * *  @OA\Property(property="city", type="string", format="boolean",example="Dhaka"),
     *  * *  @OA\Property(property="postcode", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),
     *  *  * *  @OA\Property(property="role", type="string", format="boolean",example="customer"),
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

    public function createUser(UserCreateRequest $request)
    {

        try{
            $this->storeActivity($request,"");

            if(!$request->user()->hasPermissionTo('user_create')){
                 return response()->json([
                    "message" => "You can not perform this action"
                 ],401);
            }

            $request_data = $request->validated();

            $request_data['password'] = Hash::make($request['password']);
            $request_data['is_active'] = true;
            $request_data['business_id'] = auth()->user()->business_id;

            $request_data['remember_token'] = Str::random(10);

            $user =  User::create($request_data);
            $user->email_verified_at = now();
            $user->save();

            $user->assignRole($request_data['role']);

            // $user->token = $user->createToken('Laravel Password Grant Client')->accessToken;


            $user->roles = $user->roles->pluck('name');

            // $user->permissions  = $user->getAllPermissions()->pluck('name');
            // error_log("cccccc");
            // $data["user"] = $user;
            // $data["permissions"]  = $user->getAllPermissions()->pluck('name');
            // $data["roles"] = $user->roles->pluck('name');
            // $data["token"] = $token;

            UserTranslation::where([
                "user_id" => $user->id
            ])
            ->delete();

            $first_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->first_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($first_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }

            $first_name_translation = $first_name_query['responseData']['translatedText'];

            $last_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->last_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($last_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }
            $last_name_translation = $last_name_query['responseData']['translatedText'];


            UserTranslation::create([
                "user_id" => $user->id,
                "language" => "ar",
                "first_Name" => $first_name_translation,
                "last_Name" => $last_name_translation
            ]);



            return response($user, 201);
        } catch(Exception $e){
            error_log($e->getMessage());
        return $this->sendError($e,500,$request);
        }
    }
/**
 * @OA\Post(
 *      path="/v1.0/walk-in-customers",
 *      operationId="registerWalkInCustomer",
 *      tags={"user_management"},
 *      security={
 *           {"bearerAuth": {}}
 *      },
 *      summary="Register a walk-in customer",
 *      description="This method is to register a walk-in customer",
 *
 *      @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *            required={"first_Name", "last_Name"},
 *            @OA\Property(property="first_Name", type="string", example="Rifat"),
 *            @OA\Property(property="last_Name", type="string", example="Al"),
 *            @OA\Property(property="phone", type="string", example="01771034383"),
 *            @OA\Property(property="email", type="string", example="rifatalashwad0@gmail.com"),
 *            @OA\Property(property="address_line_1", type="string", example="Dhaka"),
 *            @OA\Property(property="address_line_2", type="string", example="Dinajpur"),
 *            @OA\Property(property="country", type="string", example="Bangladesh"),
 *            @OA\Property(property="city", type="string", example="Dhaka"),
 *            @OA\Property(property="postcode", type="string", example="1207"),
 *         ),
 *      ),
 *      @OA\Response(
 *          response=201,
 *          description="Successful registration",
 *          @OA\JsonContent(),
 *      ),
 *      @OA\Response(
 *          response=400,
 *          description="Bad Request",
 *          @OA\JsonContent(),
 *      ),
 *      @OA\Response(
 *          response=422,
 *          description="Unprocessable Content",
 *          @OA\JsonContent(),
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthenticated",
 *          @OA\JsonContent(),
 *      ),
 * )
 */

 public function registerWalkInCustomer(Request $request)
 {
     $validatedData = $request->validate([
         'first_Name' => 'required|string|max:255',
         'last_Name' => 'required|string|max:255',
         'phone' => 'nullable|string',
         'email' => 'nullable|string|email|max:255',
         'address_line_1' => 'nullable|string|max:255',
         'address_line_2' => 'nullable|string|max:255',
         'country' => 'nullable|string|max:255',
         'city' => 'nullable|string|max:255',
         'postcode' => 'nullable|string|max:20',
     ]);

     try {
        $this->storeActivity($request,"");

        if(!$request->user()->hasPermissionTo('user_create')){
             return response()->json([
                "message" => "You can not perform this action"
             ],401);
        }
         $walkInCustomer = new User(); // Assuming you are using the User model for walk-in customers
         $walkInCustomer->business_id = auth()->user()->business_id;

         $walkInCustomer->first_Name = $validatedData['first_Name'];
         $walkInCustomer->last_Name = $validatedData['last_Name'];
         $walkInCustomer->phone = $validatedData['phone'];
         $walkInCustomer->email = $validatedData['email'];
         $walkInCustomer->is_walk_in_customer = 1;
         $walkInCustomer->address_line_1 = $validatedData['address_line_1'];
         $walkInCustomer->address_line_2 = $validatedData['address_line_2'];
         $walkInCustomer->country = $validatedData['country'];
         $walkInCustomer->city = $validatedData['city'];
         $walkInCustomer->postcode = $validatedData['postcode'];
         $walkInCustomer->is_active = true; // Assuming walk-in customers are active by default

         // Set a dummy password
         $dummyPassword = 'dummyPassword'; // You can change this to any default string
         $walkInCustomer->password = Hash::make($dummyPassword); // Hash the dummy password

         $walkInCustomer->save();

         return response()->json($walkInCustomer, 201);
     } catch (Exception $e) {
        error_log($e->getMessage());
        return $this->sendError($e,500,$request);
     }
 }



      /**
     *
     * @OA\Put(
     *      path="/v1.0/customer-users",
     *      operationId="createOrUpdateCustomerUser",
     *      tags={"customer_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to upsert customer user",
     *      description="This method is to upsert customer user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode"},
     *    @OA\Property(property="id", type="number", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="Al"),
     *            @OA\Property(property="email", type="string", format="string",example="rifat@g.c"),
     *  * *  @OA\Property(property="phone", type="string", format="string",example="01771034383"),
     *  * *  @OA\Property(property="address_line_1", type="string", format="string",example="dhaka"),
     *  * *  @OA\Property(property="address_line_2", type="string", format="string",example="dinajpur"),
     *  * *  @OA\Property(property="country", type="string", format="string",example="bangladesh"),
     *  * *  @OA\Property(property="city", type="string", format="string",example="dhaka"),
     *  * *  @OA\Property(property="postcode", type="string", format="string",example="1207"),
     *      *  * *  @OA\Property(property="lat", type="string", format="string",example="1207"),
     *      *  * *  @OA\Property(property="long", type="string", format="string",example="1207"),
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

    public function createOrUpdateCustomerUser(GuestUserRegisterRequest $request)
    {
        try {
            $this->storeActivity($request,"");
            $request_data = $request->validated();

            $request_data['password'] = Hash::make(Str::random(8));
            $request_data['remember_token'] = Str::random(10);

            // if (empty($request_data['email'])) {
            //     $request_data['email'] = "guest_" . '@example.com';
            //     $counter = 1;

            //     while (User::where('email', $request_data['email'])->exists()) {
            //         $request_data['email'] = "guest_" . $counter . '@example.com';
            //         $counter++;
            //     }
            // }



            if (empty($request_data['email']) && empty($request_data['id'])) {
                $maxCounterUser = User::where('email', 'LIKE', 'guest_%')->orderByRaw('SUBSTRING_INDEX(email, "_", -1) + 0 DESC')->first();

                if ($maxCounterUser) {
                    $counter = intval(substr($maxCounterUser->email, strpos($maxCounterUser->email, "_") + 1)) + 1;
                } else {
                    $counter = 1;
                }

                $request_data['email'] = "guest_" . $counter . '@example.com';
            }


            if(!empty($request_data["id"])) {
             $user =  User::where([
                    "id" => $request_data["id"]
                ])
                ->whereHas('roles', function ($query) {
                    return $query->where('name','=', 'customer');
                       })
                ->first();

                if(!$user) {
                    return response()->json(["message" => "user not found",404]);
                }
                if(!$user->email_verified_at) {
                    return response()->json(["message" => "you can not update an active user",404]);
                }
                $user->update(collect($request_data)->only([
                    'first_Name',
                    'last_Name',
                    'email',
                    'phone',
                    'image',
                    'address_line_1',
                    'address_line_2',
                    'country',
                    'city',
                    'postcode',
                    'lat',
                    'long',
                ])->toArray());

                // Optional: If you need to retrieve the updated user with relationships
                $user = $user->fresh();


            }
            else {
                $user =  User::create($request_data);
                // verify email starts
                $otp = random_int(100000, 999999);
                $user->email_verify_token = $otp;
                $user->email_verify_token_expires = Carbon::now()->subDays(-1);
                $user->email_verified_at = now();
                $user->save();


               $user->assignRole("customer");




              if(env("SEND_EMAIL") == true) {
                  Mail::to($user->email)->send(new VerifyMail($user));
              }
            }


            UserTranslation::where([
                "user_id" => $user->id
            ])
            ->delete();

            $first_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->first_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($first_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }

            $first_name_translation = $first_name_query['responseData']['translatedText'];

            $last_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->last_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($last_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }
            $last_name_translation = $last_name_query['responseData']['translatedText'];


            UserTranslation::create([
                "user_id" => $user->id,
                "language" => "ar",
                "first_Name" => $first_name_translation,
                "last_Name" => $last_name_translation
            ]);




// verify email ends

            return response($user, 201);
        } catch (Exception $e) {

            return $this->sendError($e, 500,$request);
        }
    }
    /**
        *
     * @OA\Get(
     *      path="/v1.0/customer-users/get-by-phone/{phone}",
     *      operationId="getCustomerUserByPhone",
     *      tags={"customer_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="phone",
     *         in="path",
     *         description="phone",
     *         required=true,
     *  example="01771034383"
     *      ),

     *      summary="This method is to get customer user by phone",
     *      description="This method is to get customer user by phone",
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

     public function getCustomerUserByPhone($phone,Request $request) {
        try{
            $this->storeActivity($request,"");

            $user = User::with("translation")->where([
                "phone" => $phone
            ])
            ->whereHas('roles', function ($query) {
             return $query->where('name','=', 'customer');
                })
            ->first();

            if(!$user) {
                return response()->json([
                    "message" => "no user found"
                ],404);
            }
            $booking = Booking::where([
              "customer_id" => $user->id
            ])
            ->first();

            $user->booking = $booking;

            return response()->json([$user], 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }


    }



     public function getCustomerUserByPhoneV2(Request $request) {
        try{
            $this->storeActivity($request,"");

            $user = User::with("translation")->where([
                "phone" => $request->phone
            ])
            ->whereHas('roles', function ($query) {
             return $query->where('name','=', 'customer');
                })
            ->first();

            if(!$user) {
                return response()->json([
                    "message" => "no user found"
                ],404);
            }
            $booking = Booking::where([
              "customer_id" => $user->id
            ])
            ->first();
            $user->booking = $booking;

            return response()->json([$user], 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }


 /**
        *
     * @OA\Put(
     *      path="/v1.0/users",
     *      operationId="updateUser",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="email", type="string", format="string",example="How was this?"),

     * *  @OA\Property(property="password", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="password_confirmation", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_1", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_2", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="country", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="city", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="postcode", type="boolean", format="boolean",example="1"),
     *     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),
     *  *  * *  @OA\Property(property="role", type="boolean", format="boolean",example="customer"),
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

    public function updateUser(UserUpdateRequest $request)
    {

        try{
            $this->storeActivity($request,"");
            if(!$request->user()->hasPermissionTo('user_update')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }

           $userQuery = User::
           when(!empty(auth()->user()->business_id), function($query) {
            $query->where("business_id", auth()->user()->business_id);
            })
           ->where([
            "id" => $request["id"]
       ]);

            if($userQuery->first()->hasRole("superadmin") && $request["role"] != "superadmin"){
                return response()->json([
                   "message" => "You can not change the role of super admin"
                ],401);
           }



            $request_data = $request->validated();


            if(!empty($request_data['password'])) {
                $request_data['password'] = Hash::make($request_data['password']);
            } else {
                unset($request_data['password']);
            }
            $request_data['is_active'] = true;
            $request_data['remember_token'] = Str::random(10);
            $user  =  tap(User::where(["id" => $request_data["id"]]))->update(collect($request_data)->only([
                'first_Name' ,
                'last_Name',
                'password',
                'phone',
                'address_line_1',
                'address_line_2',
                'country',
                'city',
                'postcode',
                "lat",
                "long",
                "image",
                'weekly_minimum_days',
                'weekly_minimum_hours',
                'joining_date',
                "experience"

            ])->toArray()
            )
                // ->with("somthing")

                ->first();
                if(!$user) {
                    return response()->json([
                        "message" => "no user found"
                        ],404);

            }

            $user->syncRoles([$request_data['role']]);




            $user->roles = $user->roles->pluck('name');


            UserTranslation::where([
                "user_id" => $user->id
            ])
            ->delete();

            $first_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->first_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($first_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }

            $first_name_translation = $first_name_query['responseData']['translatedText'];

            $last_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->last_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($last_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }
            $last_name_translation = $last_name_query['responseData']['translatedText'];


            UserTranslation::create([
                "user_id" => $user->id,
                "language" => "ar",
                "first_Name" => $first_name_translation,
                "last_Name" => $last_name_translation
            ]);


            return response($user, 201);
        } catch(Exception $e){
            error_log($e->getMessage());
        return $this->sendError($e,500,$request);
        }
    }
     /**
        *
     * @OA\Put(
     *      path="/v1.0/users/toggle-active",
     *      operationId="toggleActiveUser",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle user activity",
     *      description="This method is to toggle user activity",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
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

     public function toggleActiveUser(GetIdRequest $request)
     {

         try{
             $this->storeActivity($request,"");
             if(!$request->user()->hasPermissionTo('user_update')){
                 return response()->json([
                    "message" => "You can not perform this action"
                 ],401);
            }
            $request_data = $request->validated();

            $userQuery  = User::where(["id" => $request_data["id"]])
            ->when(!empty(auth()->user()->business_id), function($query) {
                $query->where("business_id", auth()->user()->business_id);
                });
            if(!auth()->user()->hasRole('superadmin')) {
                $userQuery = $userQuery->where(function ($query) {
                    $query->where('created_by', auth()->user()->id);
                });
            }

            $user =  $userQuery->first();
            if (!$user) {
                return response()->json([
                    "message" => "no user found"
                ], 404);
            }
            if($user->hasRole("superadmin")){
                return response()->json([
                   "message" => "superadmin can not be deactivated"
                ],401);
           }

            $user->update([
                'is_active' => !$user->is_active
            ]);

            return response()->json(['message' => 'User status updated successfully'], 200);


         } catch(Exception $e){
             error_log($e->getMessage());
         return $this->sendError($e,500,$request);
         }
     }

    /**
        *
     * @OA\Put(
     *      path="/v1.0/users/profile",
     *      operationId="updateUserProfile",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user profile",
     *      description="This method is to update user profile",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","first_Name","last_Name","email","password","password_confirmation","phone","address_line_1","address_line_2","country","city","postcode","role"},
     *           @OA\Property(property="id", type="string", format="number",example="1"),
     *             @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *            @OA\Property(property="last_Name", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="email", type="string", format="string",example="How was this?"),

     * *  @OA\Property(property="password", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="password_confirmation", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="phone", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_1", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="address_line_2", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="country", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="city", type="boolean", format="boolean",example="1"),
     *  * *  @OA\Property(property="postcode", type="boolean", format="boolean",example="1"),
     *     *     *  * *  @OA\Property(property="lat", type="string", format="boolean",example="1207"),
     *     *  * *  @OA\Property(property="long", type="string", format="boolean",example="1207"),

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

     public function updateUserProfile(UserUpdateProfileRequest $request)
     {

         try{


             $this->storeActivity($request,"");

             $request_data = $request->validated();


             if(!empty($request_data['password'])) {
                 $request_data['password'] = Hash::make($request_data['password']);
             } else {
                 unset($request_data['password']);
             }
            //  $request_data['is_active'] = true;
            //  $request_data['remember_token'] = Str::random(10);
             $user  =  tap(User::where(["id" => $request->user()->id]))->update(collect($request_data)->only([
                 'first_Name' ,
                 'last_Name',
                 'password',
                 'phone',
                 'address_line_1',
                 'address_line_2',
                 'country',
                 'city',
                 'postcode',
                 "lat",
                 "long",
                 "image"

             ])->toArray()
             )
                 // ->with("somthing")

                 ->first();
                 if(!$user) {
                     return response()->json([
                         "message" => "no user found"
                         ],404);

             }






             $user->roles = $user->roles->pluck('name');


             UserTranslation::where([
                "user_id" => $user->id
            ])
            ->delete();

            $first_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->first_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($first_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }

            $first_name_translation = $first_name_query['responseData']['translatedText'];

            $last_name_query = Http::get('https://api.mymemory.translated.net/get', [
                'q' => $user->last_Name,
                'langpair' => 'en|ar'  // Set the correct source and target language
            ]);

                         // Check for translation errors or unexpected responses
            if ($last_name_query['responseStatus'] !== 200) {
            throw new Exception('Translation failed');
            }
            $last_name_translation = $last_name_query['responseData']['translatedText'];


            UserTranslation::create([
                "user_id" => $user->id,
                "language" => "ar",
                "first_Name" => $first_name_translation,
                "last_Name" => $last_name_translation
            ]);


             return response($user, 201);
         } catch(Exception $e){
             error_log($e->getMessage());
         return $this->sendError($e,500,$request);
         }
     }
  /**
        *
     * @OA\Get(
     *      path="/v1.0/users",
     *      operationId="getUsersV2",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },

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
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

     public function getUsersV2(Request $request) {
        try{
            $this->storeActivity($request,"");
            if(!$request->user()->hasPermissionTo('user_view')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }

            $usersQuery = User::with("roles","translation")
            ->when(!empty(auth()->user()->business_id), function($query) {
            $query->where("business_id", auth()->user()->business_id);
            },
            function($query) use($request) {
                if(!$request->user()->hasRole('superadmin')) {
                  $query->where([
                        "created_by" =>$request->user()->id
                    ]);
                }
            }

        );

            // ->whereHas('roles', function ($query) {
            //     // return $query->where('name','!=', 'customer');
            // });


            if(!empty($request->search_key)) {
                $usersQuery = $usersQuery->where(function($query) use ($request){
                    $term = $request->search_key;
                    $query->where("first_Name", "like", "%" . $term . "%");
                    $query->orWhere("last_Name", "like", "%" . $term . "%");
                    $query->orWhere("email", "like", "%" . $term . "%");
                    $query->orWhere("phone", "like", "%" . $term . "%");
                });

            }

            if (!empty($request->start_date)) {
                $usersQuery = $usersQuery->where('created_at', ">=", $request->start_date);
            }
            if (!empty($request->end_date)) {
                $usersQuery = $usersQuery->where('created_at', "<=", $request->end_date);
            }

            $users = $usersQuery
            ->orderByDesc("id")->get();
            return response()->json($users, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }
   /**
        *
     * @OA\Get(
     *      path="/v1.0/users/{perPage}",
     *      operationId="getUsers",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="perPage",
     *         in="path",
     *         description="perPage",
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
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

     public function getUsers($perPage,Request $request) {
        try{
            $this->storeActivity($request,"");
            if(!$request->user()->hasPermissionTo('user_view')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }

            $usersQuery = User::with("roles","translation")
            ->when(!empty(auth()->user()->business_id), function($query) {
                if(request()->input("role") !== "customer") {
                    $query->where("business_id", auth()->user()->business_id);
                }

            },
            function($query) use($request) {
                if(!$request->user()->hasRole('superadmin')) {
                  $query->where([
                        "created_by" =>$request->user()->id
                    ]);
                }
            }

        )
        ->when(request()->filled("role"), function($query) {
            $query->whereHas('roles', function ($query) {
                   return $query->where('roles.name', request()->input("role"));
             });
            }
        );

            // ->whereHas('roles', function ($query) {
            //     // return $query->where('name','!=', 'customer');
            // });


            if(!empty($request->search_key)) {
                $usersQuery = $usersQuery->where(function($query) use ($request){
                    $term = $request->search_key;
                    $query->where("first_Name", "like", "%" . $term . "%");
                    $query->orWhere("last_Name", "like", "%" . $term . "%");
                    $query->orWhere("email", "like", "%" . $term . "%");
                    $query->orWhere("phone", "like", "%" . $term . "%");
                });

            }

            if (!empty($request->start_date)) {
                $usersQuery = $usersQuery->where('created_at', ">=", $request->start_date);
            }
            if (!empty($request->end_date)) {
                $usersQuery = $usersQuery->where('created_at', "<=", $request->end_date);
            }

            $users = $usersQuery->orderByDesc("id")->paginate($perPage);
            return response()->json($users, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }
  /**
        *
     * @OA\Get(
     *      path="/v1.0/expert-users",
     *      operationId="getExpertUsers",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },

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
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

     public function getExpertUsers(Request $request) {
        try{
            $this->storeActivity($request,"");

            $usersQuery = User::with("translation","expert_feedbacks")
            ->
            whereHas('roles', function($query) {
                $query->where('roles.name', 'business_experts');
            })
            ->when(request()->filled("business_id"), function($query){
                $query->where("business_id", request()->input("business_id"));
            });



            // ->whereHas('roles', function ($query) {
            //     // return $query->where('name','!=', 'customer');
            // });


            if(!empty($request->search_key)) {
                $usersQuery = $usersQuery->where(function($query) use ($request){
                    $term = $request->search_key;
                    $query->where("first_Name", "like", "%" . $term . "%");
                    $query->orWhere("last_Name", "like", "%" . $term . "%");
                    $query->orWhere("email", "like", "%" . $term . "%");
                    $query->orWhere("phone", "like", "%" . $term . "%");
                });

            }

            if (!empty($request->start_date)) {
                $usersQuery = $usersQuery->where('created_at', ">=", $request->start_date);
            }
            if (!empty($request->end_date)) {
                $usersQuery = $usersQuery->where('created_at', "<=", $request->end_date);
            }

            $users = $usersQuery->orderByDesc("id")->get();
            return response()->json($users, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }

    // Add a method to handle the date filtering logic
protected function applyDateFilter($query, $date_filter) {
    switch ($date_filter) {
        case 'today':
            return $query->whereDate('bookings.job_start_date', Carbon::today());
        case 'this_week':
            return $query->whereBetween('bookings.job_start_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        case 'previous_week':
            return $query->whereBetween('bookings.job_start_date', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
        case 'next_week':
            return $query->whereBetween('bookings.job_start_date', [Carbon::now()->addWeek()->startOfWeek(), Carbon::now()->addWeek()->endOfWeek()]);
        case 'this_month':
            return $query->whereMonth('bookings.job_start_date', Carbon::now()->month)
                         ->whereYear('bookings.job_start_date', Carbon::now()->year);
        case 'previous_month':
            return $query->whereMonth('bookings.job_start_date', Carbon::now()->subMonth()->month)
                         ->whereYear('bookings.job_start_date', Carbon::now()->subMonth()->year);
        case 'next_month':
            return $query->whereMonth('bookings.job_start_date', Carbon::now()->addMonth()->month)
                         ->whereYear('bookings.job_start_date', Carbon::now()->addMonth()->year);
        default:
            return;
    }
}
   /**
        *
     * @OA\Get(
     *      path="/v2.0/expert-users",
     *      operationId="getExpertUsersV2",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },

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
     *      summary="This method is to get user",
     *      description="This method is to get user",
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

    public function getExpertUsersV2(Request $request) {
        try{
            $this->storeActivity($request,"");

            $users = User::
            withCount([
                "expert_bookings as completed_bookings" => function($query) use($request) {
                    $query->where("bookings.status", "converted_to_job")
                        ->when($request->date_filter, function($query) use ($request) {
                            $this->applyDateFilter($query, $request->date_filter);
                        });
                },
                "expert_bookings as arrived_bookings" => function($query) use($request) {
                    $query->where("bookings.status", "arrived")
                        ->when($request->date_filter, function($query) use ($request) {
                            $this->applyDateFilter($query, $request->date_filter);
                        });
                },
                "expert_bookings as check_in_bookings" => function($query) use($request) {
                    $query->where("bookings.status", "check_in")
                        ->when($request->date_filter, function($query) use ($request) {
                            $this->applyDateFilter($query, $request->date_filter);
                        });
                },
                "expert_bookings as confirmed_bookings" => function($query) use($request) {
                    $query->where("bookings.status", "confirmed")
                        ->when($request->date_filter, function($query) use ($request) {
                            $this->applyDateFilter($query, $request->date_filter);
                        });
                },
                "expert_bookings as pending_bookings" => function($query) use($request) {
                    $query->where("bookings.status", "pending")
                        ->when($request->date_filter, function($query) use ($request) {
                            $this->applyDateFilter($query, $request->date_filter);
                        });
                },
                'expert_bookings as total_customers' => function ($query) use ($request) {
                    $query->distinct('customer_id'); // Ensure distinct customer IDs
                }
            ])


            ->withSum(['expert_bookings as total_payment_amount' => function($query) use ($request) {
                $query->where('bookings.status', 'converted_to_job')
                      ->join('job_payments', 'bookings.id', '=', 'job_payments.booking_id')
                      ->when($request->date_filter === 'today', function($query) {
                        return $query->whereDate('bookings.job_start_date', Carbon::today());
                    })
                    ->when($request->date_filter === 'this_week', function($query) {
                        return $query->whereBetween('bookings.job_start_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    })
                    ->when($request->date_filter === 'previous_week', function($query) {
                        return $query->whereBetween('bookings.job_start_date', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                    })
                    ->when($request->date_filter === 'next_week', function($query) {
                        return $query->whereBetween('bookings.job_start_date', [Carbon::now()->addWeek()->startOfWeek(), Carbon::now()->addWeek()->endOfWeek()]);
                    })
                    ->when($request->date_filter === 'this_month', function($query) {
                        return $query->whereMonth('bookings.job_start_date', Carbon::now()->month)
                                     ->whereYear('bookings.job_start_date', Carbon::now()->year);
                    })
                    ->when($request->date_filter === 'previous_month', function($query) {
                        return $query->whereMonth('bookings.job_start_date', Carbon::now()->subMonth()->month)
                                     ->whereYear('bookings.job_start_date', Carbon::now()->subMonth()->year);
                    })
                    ->when($request->date_filter === 'next_month', function($query) {
                        return $query->whereMonth('bookings.job_start_date', Carbon::now()->addMonth()->month)
                                     ->whereYear('bookings.job_start_date', Carbon::now()->addMonth()->year);
                    });
            }], 'job_payments.amount')
            ->with("translation")

            ->leftJoin('bookings', 'users.id', '=', 'bookings.expert_id')
            ->leftJoin('job_payments', 'bookings.id', '=', 'job_payments.booking_id') // Join job_payments on
            ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                $query->where('users.id', auth()->user()->id);
            })
            ->select(
                'users.*',
                DB::raw('SUM(
                    CASE
                        WHEN bookings.job_start_date BETWEEN "' . now()->startOfMonth() . '" AND "' . now()->endOfMonth() . '"
                        THEN
                            CASE
                                WHEN bookings.tip_type = "percentage" THEN bookings.final_price * (bookings.tip_amount / 100)
                                ELSE bookings.tip_amount
                            END
                        ELSE 0
                    END
                ) as this_month_revenue'),

                DB::raw('SUM(
                    CASE
                        WHEN bookings.job_start_date BETWEEN "' . now()->subMonth()->startOfMonth() . '" AND "' . now()->subMonth()->endOfMonth() . '"
                        THEN
                            CASE
                                WHEN bookings.tip_type = "percentage" THEN bookings.final_price * (bookings.tip_amount / 100)
                                ELSE bookings.tip_amount
                            END
                        ELSE 0
                    END
                ) as last_month_revenue')
            )
            ->whereHas('roles', function ($query) {
                $query->where('roles.name', 'business_experts');
            })
            ->where("business_id", auth()->user()->business_id)
            ->groupBy('users.id') // Group by user ID (expert)


            ->whereHas('roles', function($query) {
                $query->where('roles.name', 'business_experts');
            })
            ->when(request()->filled("business_id"), function($query){
                $query->where("business_id", request()->input("business_id"));
            })
            ->when(!empty($request->start_date), function ($query) use ($request) {
                $query->where('created_at', ">=", $request->start_date);
            })
            ->when(!empty($request->end_date), function ($query) use ($request) {
                $query->where('created_at', "<=", $request->end_date);
            })

           ->orderBy('this_month_revenue', 'desc') ->get();








            return response()->json($users, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }







       /**
        *
     * @OA\Get(
     *      path="/v1.0/users/get-by-id/{id}",
     *      operationId="getUserById",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
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

    public function getUserById($id,Request $request) {
        try{
            $this->storeActivity($request,"");
            if(!$request->user()->hasPermissionTo('user_view')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }

            $user = User::with("roles","translation")
            ->when(!empty(auth()->user()->business_id), function($query) {
                $query->where("business_id", auth()->user()->business_id);
                })
            ->where([
                "id" => $id
            ])
            ->when(!$request->user()->hasRole('superadmin'), function ($query) use ($request) {
                $query->where('created_by', $request->user()->id);
            })
            ->first();
            // ->whereHas('roles', function ($query) {
            //     // return $query->where('name','!=', 'customer');
            // });
            if(!$user) {
                return response()->json([
                    "message" => "no user found"
                ],404);
            }

            return response()->json($user, 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }
/**
        *
     * @OA\Delete(
     *      path="/v1.0/users/{id}",
     *      operationId="deleteUserById",
     *      tags={"user_management"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to delete user by id",
     *      description="This method is to delete user by id",
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

    public function deleteUserById($id,Request $request) {

        try{
            $this->storeActivity($request,"");

            if(!$request->user()->hasPermissionTo('user_delete')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
           $hasBookings = Booking::where('expert_id', $id)
           ->orWhere('customer_id', $id)
           ->exists();

if ($hasBookings) {
// Return an error response if bookings exist
return response()->json([
   'message' => 'User cannot be deleted because there are existing bookings associated with them.'
], 400);
}

           $user = User::where([
            "id" => $id
       ])
        ->when(!empty(auth()->user()->business_id), function($query) {
            $query->where("business_id", auth()->user()->business_id);
            },
            function($query) {
                $query->where('created_by', auth()->user()->id);
                },
            )


    ->first();
    if(!$user) {
        return response()->json([
            "message" => "no user found"
        ],404);
    }
           if($user->hasRole("superadmin")){
            return response()->json([
               "message" => "superadmin can not be deleted"
            ],401);
       }
       $user->forceDelete();

            return response()->json(["ok" => true], 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }
}
