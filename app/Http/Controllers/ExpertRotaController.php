<?php





namespace App\Http\Controllers;

use App\Http\Requests\ExpertRotaCreateRequest;
use App\Http\Requests\ExpertRotaUpdateRequest;
use App\Http\Requests\GetIdRequest;
use App\Http\Utils\BasicUtil;
use App\Http\Utils\BusinessUtil;
use Illuminate\Support\Facades\Validator;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Booking;
use App\Models\ExpertRota;
use App\Models\DisabledExpertRota;
use App\Models\ExpertRotaTime;
use App\Models\GarageTime;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpertRotaController extends Controller
{

    use ErrorUtil, UserActivityUtil, BusinessUtil, BasicUtil;


    /**
     *
     * @OA\Post(
     *      path="/v1.0/expert-rotas",
     *      operationId="createExpertRota",
     *      tags={"expert_rotas"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store expert rotas",
     *      description="This method is to store expert rotas",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     * @OA\Property(property="expert_id", type="string", format="string", example="expert_id"),
     * @OA\Property(property="date", type="string", format="string", example="date"),
     * @OA\Property(property="busy_slots", type="string", format="string", example="busy_slots"),
     *
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

    public function createExpertRota(ExpertRotaCreateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!auth()->user()->hasPermissionTo('expert_rota_create')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }

                $request_data = $request->validated();

                $request_data["is_active"] = 1;

                $request_data["created_by"] = auth()->user()->id;
                $request_data["business_id"] = auth()->user()->business_id;

                if (empty(auth()->user()->business_id)) {
                    $request_data["business_id"] = NULL;
                    if (auth()->user()->hasRole('superadmin')) {
                        $request_data["is_default"] = 1;
                    }
                }
                $bookings = Booking::whereDate("job_start_date", $request_data["date"])
                    ->whereNotIn("status", ["rejected_by_client", "rejected_by_garage_owner"])
                    ->where([
                        "expert_id" => $request_data["expert_id"]
                    ])
                    ->get();

                // Initialize an array to store overlapping bookings with individual overlapping slots
                $overlappingBookings = [];

                // Initialize an array to store all overlapping slots at once
                $allOverlappingSlots = [];

                foreach ($bookings as $booking) {
                    // Find overlapping slots between the booking and the requested busy slots
                    $overlappingSlots = array_intersect($request_data["busy_slots"], $booking->booked_slots);

                    // If there's an overlap, add the booking info and overlapping slots
                    if (!empty($overlappingSlots)) {
                        $overlappingBookings[] = [
                            'booking' => $booking, // Full booking information
                            'overlapping_slots' => $overlappingSlots // Specific overlapping times
                        ];

                        // Merge the overlapping slots into the allOverlappingSlots array
                        $allOverlappingSlots = array_merge($allOverlappingSlots, $overlappingSlots);
                    }
                }

                // Remove duplicate slots from allOverlappingSlots if needed
                $allOverlappingSlots = array_unique($allOverlappingSlots);

                // If there are overlapping bookings, return the booking info and their overlapping slots
                if (!empty($overlappingBookings)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Some slots are already booked.',
                        'overlapping_bookings' => $overlappingBookings, // Contains booking info and individual overlapping slots
                        'all_overlapping_slots' => $allOverlappingSlots // All overlapping slots combined
                    ], 409);
                }









                $expert_rota =  ExpertRota::where([
                    "expert_id" => $request_data["expert_id"],
                ])
                    ->whereDate("date", $request_data["date"])
                    ->first();


                if (!empty($expert_rota)) {
                    $expert_rota->fill(collect($request_data)->only([
                        "expert_id",
                        "date",
                        "busy_slots",
                        // "is_default",
                        // "is_active",
                        // "business_id",
                        // "created_by"
                    ])->toArray());

                    $expert_rota->save();
                } else {
                    $expert_rota =  ExpertRota::create($request_data);
                }

                $businessSetting = $this->get_business_setting(auth()->user()->business_id);
                $processedSlotInformation =  $this->processSlots($businessSetting->slot_duration, $request_data["busy_slots"]);

                ExpertRotaTime::where([
                    "expert_rota_id" => $expert_rota->id,
                ])
                    ->delete();

                if (!empty($processedSlotInformation)) {

                    foreach ($processedSlotInformation as $slot) {
                        $this->validateGarageTimes($expert_rota->business_id, $expert_rota->date, $slot["start_time"], $slot["end_time"]);
                        ExpertRotaTime::create(
                            [
                                "expert_rota_id" => $expert_rota->id,
                                "start_time" => $slot["start_time"],
                                "end_time" => $slot["end_time"]
                            ]
                        );
                    }
                }


                return response($expert_rota, 201);
            });
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Put(
     *      path="/v1.0/expert-rotas",
     *      operationId="updateExpertRota",
     *      tags={"expert_rotas"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update expert rotas ",
     *      description="This method is to update expert rotas ",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *      @OA\Property(property="id", type="number", format="number", example="1"),
     * @OA\Property(property="expert_id", type="string", format="string", example="expert_id"),
     * @OA\Property(property="date", type="string", format="string", example="date"),
     * @OA\Property(property="busy_slots", type="string", format="string", example="busy_slots"),
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

    public function updateExpertRota(ExpertRotaUpdateRequest $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            return DB::transaction(function () use ($request) {
                if (!auth()->user()->hasPermissionTo('expert_rota_update')) {
                    return response()->json([
                        "message" => "You can not perform this action"
                    ], 401);
                }
                $request_data = $request->validated();



                $expert_rota_query_params = [
                    "id" => $request_data["id"],
                ];

                $expert_rota = ExpertRota::where($expert_rota_query_params)->first();

                if ($expert_rota) {
                    $expert_rota->fill(collect($request_data)->only([

                        "expert_id",
                        "date",
                        "busy_slots",
                        // "is_default",
                        // "is_active",
                        // "business_id",
                        // "created_by"
                    ])->toArray());
                    $expert_rota->save();
                } else {
                    return response()->json([
                        "message" => "something went wrong."
                    ], 500);
                }
                ExpertRotaTime::where([
                    "expert_rota_id" => $expert_rota->id,
                ])
                    ->delete();

                if (!empty($processedSlotInformation)) {

                    foreach ($processedSlotInformation as $slot) {

                        $this->validateGarageTimes($expert_rota->business_id, $expert_rota->date, $slot["start_time"], $slot["end_time"]);

                        ExpertRotaTime::create(
                            [
                                "expert_rota_id" => $expert_rota->id,
                                "start_time" => $slot["start_time"],
                                "end_time" => $slot["end_time"]
                            ]
                        );
                    }
                }


                return response($expert_rota, 201);
            });
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Put(
     *      path="/v1.0/expert-rotas/toggle-active",
     *      operationId="toggleActiveExpertRota",
     *      tags={"expert_rotas"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle expert rotas",
     *      description="This method is to toggle expert rotas",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(

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

    public function toggleActiveExpertRota(GetIdRequest $request)
    {

        try {

            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('expert_rota_activate')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $request_data = $request->validated();

            $expert_rota =  ExpertRota::where([
                "id" => $request_data["id"],
            ])
                ->first();
            if (!$expert_rota) {

                return response()->json([
                    "message" => "no data found"
                ], 404);
            }

            $expert_rota->update([
                'is_active' => !$expert_rota->is_active
            ]);




            return response()->json(['message' => 'expert rota status updated successfully'], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/expert-rotas",
     *      operationId="getExpertRotas",
     *      tags={"expert_rotas"},
     *       security={
     *           {"bearerAuth": {}}
     *       },


     *         @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=true,
     *  example="6"
     *      ),
     *         @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=true,
     *  example="6"
     *      ),




     *         @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),

     *     @OA\Parameter(
     * name="is_active",
     * in="query",
     * description="is_active",
     * required=true,
     * example="1"
     * ),
     *     @OA\Parameter(
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
     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),
     * *  @OA\Parameter(
     * name="id",
     * in="query",
     * description="id",
     * required=true,
     * example="ASC"
     * ),
     * *  @OA\Parameter(
     * name="expert_id",
     * in="query",
     * description="expert_id",
     * required=true,
     * example="ASC"
     * ),





     *      summary="This method is to get expert rotas  ",
     *      description="This method is to get expert rotas ",
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

    public function getExpertRotas(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('expert_rota_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $expert_rotas = ExpertRota::where('expert_rotas.business_id', auth()->user()->business_id)

                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('expert_rotas.date', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('expert_rotas.date', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->expert_id), function ($query) use ($request) {
                    return $query->where('expert_rotas.expert_id', $request->expert_id);
                })

                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query;
                    });
                })



                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("expert_rotas.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("expert_rotas.id", "DESC");
                })
                ->when($request->filled("id"), function ($query) use ($request) {
                    return $query
                        ->where("expert_rotas.id", $request->input("id"))
                        ->first();
                }, function ($query) {
                    return $query->when(!empty(request()->per_page), function ($query) {
                        return $query->paginate(request()->per_page);
                    }, function ($query) {
                        return $query->get();
                    });
                });

            if ($request->filled("id") && empty($expert_rotas)) {
                throw new Exception("No data found", 404);
            }


            return response()->json($expert_rotas, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Get(
     *      path="/v1.0/client/expert-rotas",
     *      operationId="getExpertRotasClient",
     *      tags={"expert_rotas"},
     *       security={
     *           {"bearerAuth": {}}
     *       },


     *         @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=true,
     *  example="6"
     *      ),
     *         @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=true,
     *  example="6"
     *      ),




     *         @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),

     *     @OA\Parameter(
     * name="is_active",
     * in="query",
     * description="is_active",
     * required=true,
     * example="1"
     * ),
     *     @OA\Parameter(
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
     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),
     * *  @OA\Parameter(
     * name="id",
     * in="query",
     * description="id",
     * required=true,
     * example="ASC"
     * ),
     * *  @OA\Parameter(
     * name="expert_id",
     * in="query",
     * description="expert_id",
     * required=true,
     * example="ASC"
     * ),





     *      summary="This method is to get expert rotas  ",
     *      description="This method is to get expert rotas ",
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

    public function getExpertRotasClient(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");




            $expert_rotas = ExpertRota::when(!empty($request->start_date), function ($query) use ($request) {
                return $query->where('expert_rotas.date', ">=", $request->start_date);
            })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('expert_rotas.date', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->expert_id), function ($query) use ($request) {
                    return $query->where('expert_rotas.expert_id', $request->expert_id);
                })

                ->when(!empty($request->search_key), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        $term = $request->search_key;
                        $query;
                    });
                })


                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("expert_rotas.id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("expert_rotas.id", "DESC");
                })
                ->when($request->filled("id"), function ($query) use ($request) {
                    return $query
                        ->where("expert_rotas.id", $request->input("id"))
                        ->first();
                }, function ($query) {
                    return $query->when(!empty(request()->per_page), function ($query) {
                        return $query->paginate(request()->per_page);
                    }, function ($query) {
                        return $query->get();
                    });
                });

            if ($request->filled("id") && empty($expert_rotas)) {
                throw new Exception("No data found", 404);
            }


            return response()->json($expert_rotas, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     *     @OA\Delete(
     *      path="/v1.0/expert-rotas/{ids}",
     *      operationId="deleteExpertRotasByIds",
     *      tags={"expert_rotas"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="ids",
     *         in="path",
     *         description="ids",
     *         required=true,
     *  example="1,2,3"
     *      ),
     *      summary="This method is to delete expert rota by id",
     *      description="This method is to delete expert rota by id",
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

    public function deleteExpertRotasByIds(Request $request, $ids)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('expert_rota_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);
            $existingIds = ExpertRota::whereIn('id', $idsArray)
                ->where('expert_rotas.business_id', auth()->user()->business_id)
                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();

            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {
                return response()->json([
                    "message" => "Some or all of the specified data do not exist."
                ], 404);
            }

            ExpertRota::destroy($existingIds);

            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/expert-attendances",
     *      operationId="getExpertAttendances",
     *      tags={"expert_attendances"},
     *       security={
     *           {"bearerAuth": {}}
     *       },


     *         @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     *      ),
     *         @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     *      ),
     * *  @OA\Parameter(
     * name="expert_id",
     * in="query",
     * description="expert_id",
     * required=false,
     * example="ASC",
     * ),

     *      summary="This method is to get expert rotas  ",
     *      description="This method is to get expert rotas ",
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

     public function getExpertAttendances(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");
             if (!$request->user()->hasPermissionTo('expert_rota_view')) {
                 return response()->json([
                     "message" => "You can not perform this action"
                 ], 401);
             }

             // Validate required fields



             $validator = Validator::make($request->all(), [
                 'start_date' => 'required|date',
                 'end_date' => 'required|date',

             ], [
                 'start_date.required' => 'The start date is required.',
                 'end_date.required' => 'The end date is required.'
             ]);

             if ($validator->fails()) {
                 return response()->json([
                     'errors' => $validator->errors()
                 ], 422);
             }

             // Retrieve validated data
             $validated = $validator->validated();

             $businessSetting = $this->get_business_setting(auth()->user()->business_id);


             $startDate = Carbon::parse($validated['start_date']);
             $endDate = Carbon::parse($validated['end_date']);

             $experts =  User::with("translation")
                 ->whereHas('roles', function ($query) {
                     $query->where('roles.name', 'business_experts');
                 })
                 ->where('users.business_id', auth()->user()->business_id)
                 ->when(request()->filled("expert_id"), function ($query) {
                     $query->where("users.expert_id", request()->input("expert_id"));
                 })
                 ->get();
             foreach ($experts as $expert) {
                 $expert_rotas = ExpertRota::where('expert_rotas.business_id', auth()->user()->business_id)
                     ->whereDate('expert_rotas.date', ">=", $request->start_date)
                     ->whereDate('expert_rotas.date', "<=", $request->end_date)
                     ->where('expert_rotas.expert_id', $request->expert_id)


                     ->orderBy("expert_rotas.id", "DESC")
                     ->get();

                 $bookings =  Booking::where("garage_id", auth()->user()->business_id)
                     ->whereDate('bookings.job_start_date', ">=", $request->start_date)
                     ->whereDate('bookings.job_start_date', "<=", $request->end_date)
                     ->where('bookings.expert_id', $request->expert_id)
                     ->whereNotIn("status", ["rejected_by_client", "rejected_by_garage_owner"])
                     ->get();


                 $date_range = $startDate->isSameDay($endDate) ? [$startDate] : $startDate->daysUntil($endDate->addDay());

                 $attendances = collect();

                 foreach ($date_range as $date) {
                     $date = Carbon::parse($date);

                     $expert_rota = $expert_rotas->first(function ($rota) use ($date) {
                         $rota_date = Carbon::parse($rota->date);
                         return $rota_date->isSameDay($date);
                     });

                     $this_dates_bookings = $bookings->filter(function ($booking) use ($date) {
                         $job_start_date = Carbon::parse($booking->job_start_date);
                         return $job_start_date->isSameDay($date);
                     });


                     $total_booked_slots = $this_dates_bookings->sum(function ($booking) {
                         return count($booking->booked_slots); // Adjust this if "count" is a property inside "booked_slots"
                     });

                     if (!empty($expert_rota)) {
                         $attendances->push([
                             "worked_hours" => (53 - count($expert_rota->busy_slots)) * $businessSetting->slot_duration,
                             "served_hours" => $total_booked_slots * $businessSetting->slot_duration,
                             "date" => $date->toDateString()
                         ]);
                     } else {
                         $attendances->push([
                             "worked_hours" => 53 * $businessSetting->slot_duration,
                             "served_hours" => $total_booked_slots * $businessSetting->slot_duration,
                             "date" => $date->toDateString()
                         ]);
                     }
                 }

                 $expert->attendances = $attendances->toArray();
             }









             return response()->json($experts, 200);
         } catch (Exception $e) {

             return $this->sendError($e, 500, $request);
         }
     }

    /**
     *
     * @OA\Get(
     *      path="/v2.0/expert-attendances",
     *      operationId="getExpertAttendancesV2",
     *      tags={"expert_attendances"},
     *       security={
     *           {"bearerAuth": {}}
     *       },


     *         @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     *      ),
     *         @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     *      ),
     * *  @OA\Parameter(
     * name="expert_id",
     * in="query",
     * description="expert_id",
     * required=false,
     * example="ASC",
     * ),

     *      summary="This method is to get expert rotas  ",
     *      description="This method is to get expert rotas ",
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

    public function getExpertAttendancesV2(Request $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('expert_rota_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            // Validate required fields

            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date',

            ], [
                'start_date.required' => 'The start date is required.',
                'end_date.required' => 'The end date is required.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            // Retrieve validated data
            $validated = $validator->validated();

            $businessSetting = $this->get_business_setting(auth()->user()->business_id);


            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);

            $experts =  User::with("translation")
                ->whereHas('roles', function ($query) {
                    $query->where('roles.name', 'business_experts');
                })
                ->where('users.business_id', auth()->user()->business_id)
                ->when(request()->filled("expert_id"), function ($query) {
                    $query->where("users.expert_id", request()->input("expert_id"));
                })
                ->get();
            foreach ($experts as $expert) {
                $expert_rotas = ExpertRota::where('expert_rotas.business_id', auth()->user()->business_id)
                    ->whereDate('expert_rotas.date', ">=", $request->start_date)
                    ->whereDate('expert_rotas.date', "<=", $request->end_date)
                    ->where('expert_rotas.expert_id', $request->expert_id)


                    ->orderBy("expert_rotas.id", "DESC")
                    ->get();

                $bookings =  Booking::where("garage_id", auth()->user()->business_id)
                    ->whereDate('bookings.job_start_date', ">=", $request->start_date)
                    ->whereDate('bookings.job_start_date', "<=", $request->end_date)
                    ->where('bookings.expert_id', $request->expert_id)
                    ->whereNotIn("status", ["rejected_by_client", "rejected_by_garage_owner"])
                    ->get();


                $date_range = $startDate->isSameDay($endDate) ? [$startDate] : $startDate->daysUntil($endDate->addDay());

                $attendances = collect();

                foreach ($date_range as $date) {
                    $date = Carbon::parse($date);
                    $dayOfWeek = $date->dayOfWeek;
                    $garageTime = GarageTime::
            where("garage_id", auth()->user()->business_id)
            ->where("day", $dayOfWeek)
            ->first();

            $total_slots = count($garageTime->time_slots);

                    $expert_rota = $expert_rotas->first(function ($rota) use ($date) {
                        $rota_date = Carbon::parse($rota->date);
                        return $rota_date->isSameDay($date);
                    });

                    $this_dates_bookings = $bookings->filter(function ($booking) use ($date) {
                        $job_start_date = Carbon::parse($booking->job_start_date);
                        return $job_start_date->isSameDay($date);
                    });


                    $total_booked_slots = $this_dates_bookings->sum(function ($booking) {
                        return count($booking->booked_slots); // Adjust this if "count" is a property inside "booked_slots"
                    });

                    if (!empty($expert_rota)) {
                        $attendances->push([
                            "worked_minutes" => $expert_rota->worked_minutes,
                            "served_hours" => $total_booked_slots * $businessSetting->slot_duration,
                            "date" => $date->toDateString()
                        ]);
                    } else {
                        $attendances->push([
                            "worked_hours" => $total_slots * $businessSetting->slot_duration,
                            "served_hours" => $total_booked_slots * $businessSetting->slot_duration,
                            "date" => $date->toDateString()
                        ]);
                    }
                }

                $expert->attendances = $attendances->toArray();
            }









            return response()->json($experts, 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }





}
