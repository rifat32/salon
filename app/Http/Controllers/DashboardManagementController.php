<?php

namespace App\Http\Controllers;

use App\Http\Utils\BasicUtil;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\GarageUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\Affiliation;
use App\Models\Booking;
use App\Models\FuelStation;
use App\Models\Garage;
use App\Models\GarageAffiliation;
use App\Models\Job;
use App\Models\JobPayment;
use App\Models\PreBooking;
use App\Models\ReviewNew;
use App\Models\Service;
use App\Models\SubService;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardManagementController extends Controller
{
    use ErrorUtil, GarageUtil, UserActivityUtil, BasicUtil;

    /**
     *
     * @OA\Get(
     *      path="/v1.0/garage-owner-dashboard/jobs-in-area/{garage_id}",
     *      operationId="getGarageOwnerDashboardDataJobList",
     *      tags={"dashboard_management.garage_owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="garage_id",
     *         in="path",
     *         description="garage_id",
     *         required=true,
     *  example="1"
     *      ),
     *      *      * *  @OA\Parameter(
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
     *      summary="This should return list of jobs posted by drivers within same city and which are still not finalised and this garage owner have not applied yet.",
     *      description="This should return list of jobs posted by drivers within same city and which are still not finalised and this garage owner have not applied yet.",
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

    public function getGarageOwnerDashboardDataJobList($garage_id, Request $request)
    {
        try {
            $this->storeActivity($request, "");
            $garage = Garage::where([
                "id" => $garage_id,
                "owner_id" => $request->user()->id
            ])
                ->first();
            if (!$garage) {
                return response()->json([
                    "message" => "you are not the owner of the garage or the request garage does not exits"
                ], 404);
            }

            $prebookingQuery = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
                ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
                ->where([
                    "users.city" => $garage->city
                ])
                ->whereNotIn('job_bids.garage_id', [$garage->id])
                ->where('pre_bookings.status', "pending");


            if (!empty($request->start_date)) {
                $prebookingQuery = $prebookingQuery->where('pre_bookings.created_at', ">=", $request->start_date);
            }
            if (!empty($request->end_date)) {
                $prebookingQuery = $prebookingQuery->where('pre_bookings.created_at', "<=", $request->end_date);
            }
            $data = $prebookingQuery->groupBy("pre_bookings.id")
                ->select(
                    "pre_bookings.*",
                    DB::raw('(SELECT COUNT(job_bids.id) FROM job_bids WHERE job_bids.pre_booking_id = pre_bookings.id) AS job_bids_count'),

                    DB::raw('(SELECT COUNT(job_bids.id) FROM job_bids
        WHERE
        job_bids.pre_booking_id = pre_bookings.id
        AND
        job_bids.garage_id = ' . $garage->id . '

        ) AS garage_applied')

                )
                ->havingRaw('(SELECT COUNT(job_bids.id) FROM job_bids WHERE job_bids.pre_booking_id = pre_bookings.id)  < 4')

                ->get();
            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/garage-owner-dashboard/jobs-application/{garage_id}",
     *      operationId="getGarageOwnerDashboardDataJobApplications",
     *      tags={"dashboard_management.garage_owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="garage_id",
     *         in="path",
     *         description="garage_id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="Total number of Jobs in the area and out of which total number of jobs this garage owner have applied",
     *      description="Total number of Jobs in the area and out of which total number of jobs this garage owner have applied",
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

    public function getGarageOwnerDashboardDataJobApplications($garage_id, Request $request)
    {
        try {
            $this->storeActivity($request, "");
            $garage = Garage::where([
                "id" => $garage_id,
                "owner_id" => $request->user()->id
            ])
                ->first();
            if (!$garage) {
                return response()->json([
                    "message" => "you are not the owner of the garage or the request garage does not exits"
                ], 404);
            }

            $data["total_jobs"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
                ->where([
                    "users.city" => $garage->city
                ])
                //  ->whereNotIn('job_bids.garage_id', [$garage->id])
                ->where('pre_bookings.status', "pending")
                ->groupBy("pre_bookings.id")


                ->count();

            $data["weekly_jobs"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
                ->where([
                    "users.city" => $garage->city
                ])
                //  ->whereNotIn('job_bids.garage_id', [$garage->id])
                ->where('pre_bookings.status', "pending")
                ->whereBetween('pre_bookings.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->groupBy("pre_bookings.id")
                ->count();
            $data["monthly_jobs"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
                ->where([
                    "users.city" => $garage->city
                ])
                //  ->whereNotIn('job_bids.garage_id', [$garage->id])
                ->where('pre_bookings.status', "pending")
                ->whereBetween('pre_bookings.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->groupBy("pre_bookings.id")
                ->count();




            $data["applied_total_jobs"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
                ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
                ->where([
                    "users.city" => $garage->city
                ])
                ->whereIn('job_bids.garage_id', [$garage->id])
                ->where('pre_bookings.status', "pending")
                ->groupBy("pre_bookings.id")

                ->count();
            $data["applied_weekly_jobs"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
                ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
                ->where([
                    "users.city" => $garage->city
                ])
                ->whereIn('job_bids.garage_id', [$garage->id])
                ->where('pre_bookings.status', "pending")
                ->whereBetween('pre_bookings.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->groupBy("pre_bookings.id")

                ->count();
            $data["applied_monthly_jobs"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
                ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
                ->where([
                    "users.city" => $garage->city
                ])
                ->whereIn('job_bids.garage_id', [$garage->id])
                ->where('pre_bookings.status', "pending")
                ->whereBetween('pre_bookings.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->groupBy("pre_bookings.id")

                ->count();

            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/garage-owner-dashboard/winned-jobs-application/{garage_id}",
     *      operationId="getGarageOwnerDashboardDataWinnedJobApplications",
     *      tags={"dashboard_management.garage_owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="garage_id",
     *         in="path",
     *         description="garage_id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="Total Job Won( Total job User have selcted this garage )",
     *      description="Total Job Won( Total job User have selcted this garage )",
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

    public function getGarageOwnerDashboardDataWinnedJobApplications($garage_id, Request $request)
    {
        try {
            $this->storeActivity($request, "");
            $garage = Garage::where([
                "id" => $garage_id,
                "owner_id" => $request->user()->id
            ])
                ->first();
            if (!$garage) {
                return response()->json([
                    "message" => "you are not the owner of the garage or the request garage does not exits"
                ], 404);
            }

            $data["total"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
                ->where([
                    "bookings.garage_id" => $garage->id
                ])

                ->where('pre_bookings.status', "booked")
                ->groupBy("pre_bookings.id")
                ->count();

            $data["weekly"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
                ->where([
                    "bookings.garage_id" => $garage->id
                ])
                ->where('pre_bookings.status', "booked")
                ->whereBetween('pre_bookings.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->groupBy("pre_bookings.id")
                ->count();

            $data["monthly"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
                ->where([
                    "bookings.garage_id" => $garage->id
                ])

                ->where('pre_bookings.status', "booked")
                ->whereBetween('pre_bookings.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->groupBy("pre_bookings.id")
                ->count();


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/garage-owner-dashboard/completed-bookings/{garage_id}",
     *      operationId="getGarageOwnerDashboardDataCompletedBookings",
     *      tags={"dashboard_management.garage_owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="garage_id",
     *         in="path",
     *         description="garage_id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="Total completed Bookings Total Bookings completed by this garage owner",
     *      description="Total completed Bookings Total Bookings completed by this garage owner",
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

    public function getGarageOwnerDashboardDataCompletedBookings($garage_id, Request $request)
    {
        try {
            $this->storeActivity($request, "");
            $garage = Garage::where([
                "id" => $garage_id,
                "owner_id" => $request->user()->id
            ])
                ->first();
            if (!$garage) {
                return response()->json([
                    "message" => "you are not the owner of the garage or the request garage does not exits"
                ], 404);
            }

            $data["total"] = Booking::where([
                "bookings.status" => "converted_to_job",
                "bookings.garage_id" => $garage->id

            ])
                ->count();
            $data["weekly"] = Booking::where([
                "bookings.status" => "converted_to_job",
                "bookings.garage_id" => $garage->id

            ])
                ->whereBetween('bookings.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->count();
            $data["monthly"] = Booking::where([
                "bookings.status" => "converted_to_job",
                "bookings.garage_id" => $garage->id

            ])
                ->whereBetween('bookings.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->count();




            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }




    /**
     *
     * @OA\Get(
     *      path="/v1.0/garage-owner-dashboard/upcoming-jobs/{garage_id}/{duration}",
     *      operationId="getGarageOwnerDashboardDataUpcomingJobs",
     *      tags={"dashboard_management.garage_owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="garage_id",
     *         in="path",
     *         description="garage_id",
     *         required=true,
     *  example="1"
     *      ),
     *   *              @OA\Parameter(
     *         name="duration",
     *         in="path",
     *         description="duration",
     *         required=true,
     *  example="7"
     *      ),
     *      summary="Total completed Bookings Total Bookings completed by this garage owner",
     *      description="Total completed Bookings Total Bookings completed by this garage owner",
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

    public function getGarageOwnerDashboardDataUpcomingJobs($garage_id, $duration, Request $request)
    {
        try {
            $this->storeActivity($request, "");
            $garage = Garage::where([
                "id" => $garage_id,
                "owner_id" => $request->user()->id
            ])
                ->first();
            if (!$garage) {
                return response()->json([
                    "message" => "you are not the owner of the garage or the request garage does not exits"
                ], 404);
            }
            $startDate = now();
            $endDate = $startDate->copy()->addDays($duration);


            $data = Job::where([
                "jobs.status" => "pending",
                "jobs.garage_id" => $garage->id

            ])
                ->whereBetween('jobs.job_start_date', [$startDate, $endDate])




                ->count();



            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/garage-owner-dashboard/expiring-affiliations/{garage_id}/{duration}",
     *      operationId="getGarageOwnerDashboardDataExpiringAffiliations",
     *      tags={"dashboard_management.garage_owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="garage_id",
     *         in="path",
     *         description="garage_id",
     *         required=true,
     *  example="1"
     *      ),
     *   *              @OA\Parameter(
     *         name="duration",
     *         in="path",
     *         description="duration",
     *         required=true,
     *  example="7"
     *      ),
     *      summary="Total completed Bookings Total Bookings completed by this garage owner",
     *      description="Total completed Bookings Total Bookings completed by this garage owner",
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

    public function getGarageOwnerDashboardDataExpiringAffiliations($garage_id, $duration, Request $request)
    {
        try {
            $this->storeActivity($request, "");
            $garage = Garage::where([
                "id" => $garage_id,
                "owner_id" => $request->user()->id
            ])
                ->first();
            if (!$garage) {
                return response()->json([
                    "message" => "you are not the owner of the garage or the request garage does not exits"
                ], 404);
            }
            $startDate = now();
            $endDate = $startDate->copy()->addDays($duration);


            $data = GarageAffiliation::with("affiliation")
                ->where('garage_affiliations.end_date', "<",  $endDate)
                ->count();



            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }




    public function applied_jobs($garage)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);

        $data["total_count"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
            ->where([
                "users.city" => $garage->city
            ])
            ->whereIn('job_bids.garage_id', [$garage->id])
            ->where('pre_bookings.status', "pending")
            ->groupBy("pre_bookings.id")
            ->count();





        $data["this_week_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
            ->where([
                "users.city" => $garage->city
            ])
            ->whereIn('job_bids.garage_id', [$garage->id])
            ->where('pre_bookings.status', "pending")

            ->whereBetween('pre_bookings.created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->groupBy("pre_bookings.id")
            ->select("job_bids.id", "job_bids.created_at", "job_bids.updated_at")
            ->get();

        $data["previous_week_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
            ->where([
                "users.city" => $garage->city
            ])
            ->whereIn('job_bids.garage_id', [$garage->id])
            ->where('pre_bookings.status', "pending")

            ->whereBetween('pre_bookings.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->groupBy("pre_bookings.id")
            ->select("job_bids.id", "job_bids.created_at", "job_bids.updated_at")
            ->get();



        $data["this_month_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
            ->where([
                "users.city" => $garage->city
            ])
            ->whereIn('job_bids.garage_id', [$garage->id])
            ->where('pre_bookings.status', "pending")
            ->whereBetween('pre_bookings.created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->groupBy("pre_bookings.id")
            ->select("job_bids.id", "job_bids.created_at", "job_bids.updated_at")
            ->get();

        $data["previous_month_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->leftJoin('job_bids', 'pre_bookings.id', '=', 'job_bids.pre_booking_id')
            ->where([
                "users.city" => $garage->city
            ])
            ->whereIn('job_bids.garage_id', [$garage->id])
            ->where('pre_bookings.status', "pending")
            ->whereBetween('pre_bookings.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->groupBy("pre_bookings.id")
            ->select("job_bids.id", "job_bids.created_at", "job_bids.updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();

        return $data;
    }
    public function pre_bookings($garage)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);

        $data["total_count"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')

            ->where([
                "users.city" => $garage->city
            ])
            //  ->whereNotIn('job_bids.garage_id', [$garage->id])
            ->where('pre_bookings.status', "pending")
            ->count();



        $data["this_week_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')

            ->where([
                "users.city" => $garage->city
            ])

            ->where('pre_bookings.status', "pending")
            ->whereBetween('pre_bookings.created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("pre_bookings.id", "pre_bookings.created_at", "pre_bookings.updated_at")
            ->get();

        $data["previous_week_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->where([
                "users.city" => $garage->city
            ])

            ->where('pre_bookings.status', "pending")
            ->whereBetween('pre_bookings.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("pre_bookings.id", "pre_bookings.created_at", "pre_bookings.updated_at")
            ->get();



        $data["this_month_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->where([
                "users.city" => $garage->city
            ])

            ->where('pre_bookings.status', "pending")
            ->whereBetween('pre_bookings.created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("pre_bookings.id", "pre_bookings.created_at", "pre_bookings.updated_at")
            ->get();

        $data["previous_month_data"] = PreBooking::leftJoin('users', 'pre_bookings.customer_id', '=', 'users.id')
            ->where([
                "users.city" => $garage->city
            ])

            ->where('pre_bookings.status', "pending")
            ->whereBetween('pre_bookings.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("pre_bookings.id", "pre_bookings.created_at", "pre_bookings.updated_at")
            ->get();


        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();

        return $data;
    }

    public function winned_jobs($garage)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);
        $data["total_data_count"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
            ->where([
                "bookings.garage_id" => $garage->id
            ])

            ->where('pre_bookings.status', "booked")
            ->groupBy("pre_bookings.id")
            ->count();







        $data["this_week_data"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
            ->where([
                "bookings.garage_id" => $garage->id
            ])
            ->where('pre_bookings.status', "booked")
            ->whereBetween('pre_bookings.created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->groupBy("pre_bookings.id")
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();
        $data["previous_week_data"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
            ->where([
                "bookings.garage_id" => $garage->id
            ])
            ->where('pre_bookings.status', "booked")
            ->whereBetween('pre_bookings.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->groupBy("pre_bookings.id")
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();



        $data["this_month_data"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
            ->where([
                "bookings.garage_id" => $garage->id
            ])
            ->where('pre_bookings.status', "booked")
            ->whereBetween('pre_bookings.created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->groupBy("pre_bookings.id")
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();

        $data["previous_month_data"] = PreBooking::leftJoin('bookings', 'pre_bookings.id', '=', 'bookings.pre_booking_id')
            ->where([
                "bookings.garage_id" => $garage->id
            ])
            ->where('pre_bookings.status', "booked")
            ->whereBetween('pre_bookings.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->groupBy("pre_bookings.id")
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();

        return $data;
    }


    public function completed_bookings($garage)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);

        $data["total_data_count"] = Booking::where([
            "bookings.status" => "converted_to_job",
            "bookings.garage_id" => $garage->id

        ])
            ->count();






        $data["this_week_data"] = Booking::where([
            "bookings.status" => "converted_to_job",
            "bookings.garage_id" => $garage->id

        ])
            ->whereBetween('bookings.created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();
        $data["previous_week_data"] = Booking::where([
            "bookings.status" => "converted_to_job",
            "bookings.garage_id" => $garage->id

        ])
            ->whereBetween('bookings.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();



        $data["this_month_data"] = Booking::where([
            "bookings.status" => "converted_to_job",
            "bookings.garage_id" => $garage->id

        ])
            ->whereBetween('bookings.created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();
        $data["previous_month_data"] = Booking::where([
            "bookings.status" => "converted_to_job",
            "bookings.garage_id" => $garage->id

        ])
            ->whereBetween('bookings.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

    public function upcoming_jobs($garage)
    {
        $startDate = now();

        // $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfNextMonth = Carbon::now()->startOfMonth()->addMonth(1);
        $endDateOfNextMonth = Carbon::now()->endOfMonth()->addMonth(1);

        // $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfNextWeek = Carbon::now()->startOfWeek()->addWeek(1);
        $endDateOfNextWeek = Carbon::now()->endOfWeek()->addWeek(1);



        // $weeklyEndDate = $startDate->copy()->addDays(7);
        // $secondWeeklyStartDate = $startDate->copy()->addDays(8);
        // $secondWeeklyEndDate = $startDate->copy()->addDays(14);
        // $monthlyEndDate = $startDate->copy()->addDays(30);
        // $secondMonthlyStartDate = $startDate->copy()->addDays(31);
        // $secondMonthlyStartDate = $startDate->copy()->addDays(60);






        $data["total_data_count"] = Job::where([
            "jobs.status" => "pending",
            "jobs.garage_id" => $garage->id

        ])
            ->count();


        $data["this_week_data"] = Job::where([
            "jobs.status" => "pending",
            "jobs.garage_id" => $garage->id

        ])->whereBetween('jobs.job_start_date', [$startDate, $endDateOfThisWeek])
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();
        $data["next_week_data"] = Job::where([
            "jobs.status" => "pending",
            "jobs.garage_id" => $garage->id

        ])->whereBetween('jobs.job_start_date', [$startDateOfNextWeek, $endDateOfNextWeek])
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();

        $data["this_month_data"] = Job::where([
            "jobs.status" => "pending",
            "jobs.garage_id" => $garage->id

        ])->whereBetween('jobs.job_start_date', [$startDate, $endDateOfThisMonth])
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();
        $data["next_month_data"] = Job::where([
            "jobs.status" => "pending",
            "jobs.garage_id" => $garage->id

        ])->whereBetween('jobs.job_start_date', [$startDateOfNextMonth, $endDateOfNextMonth])
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();


        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["next_week_data_count"] = $data["next_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["next_month_data_count"] = $data["next_month_data"]->count();

        return $data;
    }
    public function affiliation_expirings($garage)
    {
        $startDate = now();

        // $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfNextMonth = Carbon::now()->startOfMonth()->addMonth(1);
        $endDateOfNextMonth = Carbon::now()->endOfMonth()->addMonth(1);

        // $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfNextWeek = Carbon::now()->startOfWeek()->addWeek(1);
        $endDateOfNextWeek = Carbon::now()->endOfWeek()->addWeek(1);


        $data["total_data_count"] = GarageAffiliation::where([
            "garage_affiliations.garage_id" => $garage->id
        ])
            ->count();


        $data["this_week_data"] = GarageAffiliation::where([
            "garage_affiliations.garage_id" => $garage->id
        ])
            ->whereBetween('garage_affiliations.end_date', [$startDate, $endDateOfThisWeek])

            ->select("garage_affiliations.id", "garage_affiliations.created_at", "garage_affiliations.updated_at")
            ->get();
        $data["next_week_data"] = GarageAffiliation::where([
            "garage_affiliations.garage_id" => $garage->id
        ])
            ->whereBetween('garage_affiliations.end_date', [$startDateOfNextWeek, $endDateOfNextWeek])

            ->select("garage_affiliations.id", "garage_affiliations.created_at", "garage_affiliations.updated_at")
            ->get();

        $data["this_month_data"] = GarageAffiliation::where([
            "garage_affiliations.garage_id" => $garage->id
        ])
            ->whereBetween('garage_affiliations.end_date', [$startDate, $endDateOfThisMonth])
            ->select("garage_affiliations.id", "garage_affiliations.created_at", "garage_affiliations.updated_at")
            ->get();

        $data["next_month_data"] = GarageAffiliation::where([
            "garage_affiliations.garage_id" => $garage->id
        ])
            ->whereBetween('garage_affiliations.end_date', [$startDateOfNextMonth, $endDateOfNextMonth])
            ->select("garage_affiliations.id", "garage_affiliations.created_at", "garage_affiliations.updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["next_week_data_count"] = $data["next_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["next_month_data_count"] = $data["next_month_data"]->count();


        return $data;
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/garage-owner-dashboard/{garage_id}",
     *      operationId="getGarageOwnerDashboardData",
     *      tags={"dashboard_management.garage_owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="garage_id",
     *         in="path",
     *         description="garage_id",
     *         required=true,
     *  example="1"
     *      ),

     *      summary="get all dashboard data combined",
     *      description="get all dashboard data combined",
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

    public function getGarageOwnerDashboardData($garage_id, Request $request)
    {

        try {
            $this->storeActivity($request, "");
            if (!$request->user()->hasRole('garage_owner')) {
                return response()->json([
                    "message" => "You are not a garage owner"
                ], 401);
            }
            $garage = Garage::where([
                "id" => $garage_id,
                "owner_id" => $request->user()->id
            ])
                ->first();
            if (!$garage) {
                return response()->json([
                    "message" => "you are not the owner of the garage or the request garage does not exits"
                ], 404);
            }


            // affiliation expiry
            $data["affiliation_expirings"] = $this->affiliation_expirings($garage);

            //    end affiliation expiry
            //   upcoming_jobs
            $data["upcoming_jobs"] = $this->upcoming_jobs($garage);

            //  end  upcoming_jobs

            // completed bookings
            $data["completed_bookings"] = $this->completed_bookings($garage);
            // end completed bookings

            // winned jobs
            $data["winned_jobs"] = $this->winned_jobs($garage);
            // end winned jobs

            //   jobs
            $data["pre_bookings"] = $this->pre_bookings($garage);
            // end jobs


            // applied jobs
            $data["applied_jobs"] = $this->applied_jobs($garage);
            // end applied jobs


            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }
    public function garages($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $total_data_count_query = new Garage();
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "created_by" => auth()->user()->id
            ]);
        }

        $data["total_data_count"] = $total_data_count_query->count();



        $this_week_data_query = Garage::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);

        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query->select("id", "created_at", "updated_at")->get();




        $previous_week_data_query = Garage::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);

        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }

        $data["previous_week_data"] = $total_data_count_query->select("id", "created_at", "updated_at")->get();




        $this_month_data_query = Garage::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);

        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query->select("id", "created_at", "updated_at")->get();




        $previous_month_data_query = Garage::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);

        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query->select("id", "created_at", "updated_at")->get();



        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }
    public function fuel_stations($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);


        $total_data_count_query = new FuelStation();
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["total_data_count"] = $total_data_count_query->count();


        $this_week_data_query = FuelStation::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);
        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query->select("id", "created_at", "updated_at")
            ->get();


        $previous_week_data_query = FuelStation::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);
        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["previous_week_data"] = $previous_week_data_query->select("id", "created_at", "updated_at")
            ->get();


        $this_month_data_query =  FuelStation::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);
        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query->select("id", "created_at", "updated_at")
            ->get();

        $previous_month_data_query =  FuelStation::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);
        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query->select("id", "created_at", "updated_at")
            ->get();




        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

    public function customers()
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $data["total_data_count"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->count();


        $data["this_week_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_week_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("id", "created_at", "updated_at")
            ->get();



        $data["this_month_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("id", "created_at", "updated_at")
            ->get();
        $data["previous_month_data"] = User::with("roles")->whereHas("roles", function ($q) {
            $q->whereIn("name", ["customer"]);
        })->whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }
    public function overall_customer_jobs()
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $data["total_data_count"] = PreBooking::count();


        $data["this_week_data"] = PreBooking::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_week_data"] = PreBooking::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("id", "created_at", "updated_at")
            ->get();



        $data["this_month_data"] = PreBooking::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_month_data"] = PreBooking::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

    public function overall_bookings($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);


        $total_data_count_query =  Booking::leftJoin('garages', 'garages.id', '=', 'bookings.garage_id');
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["total_data_count"] = $total_data_count_query->count();



        $this_week_data_query =  Booking::leftJoin('garages', 'garages.id', '=', 'bookings.garage_id')
            ->whereBetween('bookings.created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);
        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();




        $previous_week_data_query =  Booking::leftJoin('garages', 'garages.id', '=', 'bookings.garage_id')
            ->whereBetween('bookings.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);
        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_week_data"] = $previous_week_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();






        $this_month_data_query =  Booking::leftJoin('garages', 'garages.id', '=', 'bookings.garage_id')
            ->whereBetween('bookings.created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);
        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();


        $previous_month_data_query =  Booking::leftJoin('garages', 'garages.id', '=', 'bookings.garage_id')
            ->whereBetween('bookings.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);
        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query->select("bookings.id", "bookings.created_at", "bookings.updated_at")
            ->get();


        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }

    public function overall_jobs($created_by_filter = 0)
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);


        $total_data_count_query =  Job::leftJoin('garages', 'garages.id', '=', 'jobs.garage_id');
        if ($created_by_filter) {
            $total_data_count_query =  $total_data_count_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["total_data_count"] = $total_data_count_query->count();





        $this_week_data_query =  Job::leftJoin('garages', 'garages.id', '=', 'jobs.garage_id')
            ->whereBetween('jobs.created_at', [$startDateOfThisWeek, $endDateOfThisWeek]);
        if ($created_by_filter) {
            $this_week_data_query =  $this_week_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["this_week_data"] = $this_week_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();




        $previous_week_data_query =  Job::leftJoin('garages', 'garages.id', '=', 'jobs.garage_id')
            ->whereBetween('jobs.created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek]);
        if ($created_by_filter) {
            $previous_week_data_query =  $previous_week_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_week_data"] = $previous_week_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();





        $this_month_data_query =  Job::leftJoin('garages', 'garages.id', '=', 'jobs.garage_id')
            ->whereBetween('jobs.created_at', [$startDateOfThisMonth, $endDateOfThisMonth]);
        if ($created_by_filter) {
            $this_month_data_query =  $this_month_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["this_month_data"] = $this_month_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();



        $previous_month_data_query =  Job::leftJoin('garages', 'garages.id', '=', 'jobs.garage_id')
            ->whereBetween('jobs.created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth]);
        if ($created_by_filter) {
            $previous_month_data_query =  $previous_month_data_query->where([
                "garages.created_by" => auth()->user()->id
            ]);
        }
        $data["previous_month_data"] = $previous_month_data_query
            ->select("jobs.id", "jobs.created_at", "jobs.updated_at")
            ->get();



        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }



    public function overall_services()
    {
        $startDateOfThisMonth = Carbon::now()->startOfMonth();
        $endDateOfThisMonth = Carbon::now()->endOfMonth();
        $startDateOfPreviousMonth = Carbon::now()->startOfMonth()->subMonth(1);
        $endDateOfPreviousMonth = Carbon::now()->endOfMonth()->subMonth(1);

        $startDateOfThisWeek = Carbon::now()->startOfWeek();
        $endDateOfThisWeek = Carbon::now()->endOfWeek();
        $startDateOfPreviousWeek = Carbon::now()->startOfWeek()->subWeek(1);
        $endDateOfPreviousWeek = Carbon::now()->endOfWeek()->subWeek(1);



        $data["total_data_count"] = Service::count();


        $data["this_week_data"] = Service::whereBetween('created_at', [$startDateOfThisWeek, $endDateOfThisWeek])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["previous_week_data"] = Service::whereBetween('created_at', [$startDateOfPreviousWeek, $endDateOfPreviousWeek])
            ->select("id", "created_at", "updated_at")
            ->get();



        $data["this_month_data"] = Service::whereBetween('created_at', [$startDateOfThisMonth, $endDateOfThisMonth])
            ->select("id", "created_at", "updated_at")
            ->get();
        $data["previous_month_data"] = Service::whereBetween('created_at', [$startDateOfPreviousMonth, $endDateOfPreviousMonth])
            ->select("id", "created_at", "updated_at")
            ->get();

        $data["this_week_data_count"] = $data["this_week_data"]->count();
        $data["previous_week_data_count"] = $data["previous_week_data"]->count();
        $data["this_month_data_count"] = $data["this_month_data"]->count();
        $data["previous_month_data_count"] = $data["previous_month_data"]->count();
        return $data;
    }






    public function bookings($range = 'today')
    {
        return Booking::with([
                "sub_services" => function ($query) {
                    $query->select(
                        "sub_services.id",
                        "sub_services.name"
                    );
                },

                "customer" => function ($query) {
                    $query->select(
                        "id",
                        "first_Name",
                        "last_Name"
                    );
                },
            ])


            ->when($range === 'today', function ($query) {
                $query->whereDate('job_start_date', Carbon::today());
            })
            ->when($range === 'this_week', function ($query) {
                $query->whereBetween('job_start_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when($range === 'this_month', function ($query) {
                $query->whereBetween('job_start_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
            })
            ->when($range === 'next_week', function ($query) {
                $query->whereBetween('job_start_date', [Carbon::now()->addWeek()->startOfWeek(), Carbon::now()->addWeek()->endOfWeek()]);
            })
            ->when($range === 'next_month', function ($query) {
                $query->whereBetween('job_start_date', [Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()]);
            })
            ->when($range === 'previous_week', function ($query) {
                $query->whereBetween('job_start_date', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
            })
            ->when($range === 'previous_month', function ($query) {
                $query->whereBetween('job_start_date', [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()]);
            })
            ->when($range === 'all' && request()->filled("start_date") && request()->filled("end_date"), function ($query) {
                $query->whereBetween('job_start_date', [request()->start_date, request()->end_date]);
            })
        ;
    }

    // Method to get counts for each status
    public function bookingsByStatusCount($range = 'today', $expert_id = NULL)
    {
        $statuses = [
            "all",
            "pending",
            "confirmed",
            "check_in",
            "rejected_by_client",
            "rejected_by_garage_owner",
            "arrived",
            "converted_to_job"
        ];

        $counts = [];

        foreach ($statuses as $status) {
            $counts[$status] = $this->bookings($range)
                ->where("garage_id", auth()->user()->business_id)
                ->when($status != "all", function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->when(!empty($expert_id), function ($query) use ($expert_id) {
                    $query->where('expert_id', $expert_id);
                })

                ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                    $query->where('bookings.expert_id', auth()->user()->id);
                })


                ->count();
        }

        return $counts;
    }

    public function bookingsByStatus($range = 'today', $expert_id = NULL)
    {
        $statuses = [
            "all",
            "pending",
            "confirmed",
            "check_in",
            "rejected_by_client",
            "rejected_by_garage_owner",
            "arrived",
            "converted_to_job"
        ];
        if (request()->filled("status")) {
            $statuses = explode(',', request()->input("status"));
        }


        $data = [];

        foreach ($statuses as $status) {
            $data[$status] = $this->bookings($range)
                ->where("garage_id", auth()->user()->business_id)
                ->when($status != "all", function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->when(!empty($expert_id), function ($query) use ($expert_id) {
                    $query->where('expert_id', $expert_id);
                })

                ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                    $query->where('bookings.expert_id', auth()->user()->id);
                })


                ->get();
        }

        return $data;
    }

    protected function calculateRevenue($garage_id, $range, $expert_id, $is_walk_in_customer)
    {
        return JobPayment::when(request()->filled("payment_type"), function ($query) {
                $payment_typeArray = explode(',', request()->payment_type);
                $query->whereIn("job_payments.payment_type", $payment_typeArray);
            })
            ->whereHas("bookings.customer", function ($query) use ($is_walk_in_customer) {
                $query->where("users.is_walk_in_customer", $is_walk_in_customer);
            })
            ->when(request()->has('is_returning_customers'), function ($q) {
                $q->whereHas("bookings.customer", function ($query) {
                    $query->select('bookings.customer_id', DB::raw('COUNT(id) as bookings_count'))
                        ->groupBy('bookings.customer_id')
                        ->having('bookings_count', (request()->boolean("is_returning_customers") ? '>' : '='), 1);
                });
            })

            ->whereHas('bookings', function ($query) use ($garage_id, $range, $expert_id) {
                $query->selectRaw('COALESCE(SUM(json_length(bookings.booked_slots)), 0) as total_booked_slots')
                    ->where('bookings.garage_id', $garage_id)

                    ->when(!empty($expert_id), function ($query) use ($expert_id) {
                        $query->where('bookings.expert_id', $expert_id);
                    })
                    ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                        $query->where('bookings.expert_id', auth()->user()->id);
                    })
                    ->when(request()->filled("duration_in_minute"), function ($query) {
                        $total_slots = request()->input("duration_in_minute") / 15;
                        $query->having('total_booked_slots', '>', $total_slots);
                    })

                    ->when(request()->filled("slots"), function ($query) {
                        $slotsArray = explode(',', request()->input("slots"));
                        $query->where(function ($subQuery) use ($slotsArray) {
                            foreach ($slotsArray as $slot) {
                                $subQuery->orWhereRaw("JSON_CONTAINS(bookings.busy_slots, '\"$slot\"')");
                            }
                        });
                    })

                    ->when(!empty(request()->sub_service_ids), function ($query) {
                        $sub_service_ids = explode(',', request()->sub_service_ids);

                        return $query->whereHas('sub_services', function ($query) use ($sub_service_ids) {
                            $query->whereIn('sub_services.id', $sub_service_ids)
                                ->when(!empty(request()->service_ids), function ($query) {
                                    $service_ids = explode(',', request()->service_ids);

                                    return $query->whereHas('service', function ($query) use ($service_ids) {
                                        return $query->whereIn('services.id', $service_ids);
                                    });
                                });
                        });
                    })
                    ->when($range === 'today', function ($query) {
                        $query->whereDate('bookings.job_start_date', Carbon::today());
                    })
                    ->when($range === 'this_week', function ($query) {
                        $query->whereBetween('bookings.job_start_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    })
                    ->when($range === 'this_month', function ($query) {
                        $query->whereBetween('bookings.job_start_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    })
                    ->when($range === 'next_week', function ($query) {
                        $query->whereBetween('bookings.job_start_date', [Carbon::now()->addWeek()->startOfWeek(), Carbon::now()->addWeek()->endOfWeek()]);
                    })
                    ->when($range === 'next_month', function ($query) {
                        $query->whereBetween('bookings.job_start_date', [Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    })
                    ->when($range === 'previous_week', function ($query) {
                        $query->whereBetween('bookings.job_start_date', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                    })
                    ->when($range === 'previous_month', function ($query) {
                        $query->whereBetween('bookings.job_start_date', [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    })
                    ->when($range === 'all' && request()->filled("start_date") && request()->filled("end_date"), function ($query) {
                        $query->whereBetween('bookings.job_start_date', [request()->start_date, request()->end_date]);
                    })
                ;
            })->sum('amount');
    }
    public function revenue($range = 'today', $expert_id = NULL)
    {
        $garage_id = auth()->user()->business_id; // Get the garage ID

        // Fetch payments and sum the amount
        return [
            "app_customer_revenue" => $this->calculateRevenue($garage_id, $range, $expert_id, 0),
            "walk_in_customer_revenue" => $this->calculateRevenue($garage_id, $range, $expert_id, 1),
        ];
    }
    public function getCustomersByPeriod($period)
    {
        // Determine date range based on the period
        switch ($period) {
            case 'today':
                $start = Carbon::today();
                $end = Carbon::today();
                break;
            case 'this_week':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                break;
            case 'this_month':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                break;
            case 'next_week':
                $start = Carbon::now()->addWeek()->startOfWeek();
                $end = Carbon::now()->addWeek()->endOfWeek();
                break;
            case 'next_month':
                $start = Carbon::now()->addMonth()->startOfMonth();
                $end = Carbon::now()->addMonth()->endOfMonth();
                break;
            case 'previous_week':
                $start = Carbon::now()->subWeek()->startOfWeek();
                $end = Carbon::now()->subWeek()->endOfWeek();
                break;
            case 'previous_month':
                $start = Carbon::now()->subMonth()->startOfMonth();
                $end = Carbon::now()->subMonth()->endOfMonth();
                break;
            default:
                $start = Carbon::now()->subMonth()->startOfMonth();
                $end = Carbon::now()->subMonth()->endOfMonth();
        }


        $app_customers = User::where("users.is_walk_in_customer", 0)
            ->whereHas('bookings', function ($query) use ($start, $end) {
                $query->whereBetween('bookings.job_start_date', [$start, $end])
                    ->where("garage_id", auth()->user()->business_id)
                    ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                        $query->where('bookings.expert_id', auth()->user()->id);
                    });
            })
            ->distinct()
            ->get();

        $walk_in_customers = User::where("users.is_walk_in_customer", 1) // Assuming is_walk_in_customer should be 1 for walk-in customers
            ->whereHas('bookings', function ($query) use ($start, $end) {
                $query->whereBetween('bookings.job_start_date', [$start, $end])
                    ->where("garage_id", auth()->user()->business_id)
                    ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                        $query->where('bookings.expert_id', auth()->user()->id);
                    });
            })
            ->distinct()
            ->get();

        // Return the results
        return [
            'app_customers' => $app_customers,
            'walk_in_customers' => $walk_in_customers,
        ];
    }

    public function getRepeatedCustomers()
    {
        return User::whereHas('bookings', function ($query) {
                $query->select('customer_id', DB::raw('COUNT(id) as bookings_count'))
                    ->groupBy('customer_id')
                    ->where("garage_id", auth()->user()->business_id)
                    ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                        $query->where('bookings.expert_id', auth()->user()->id);
                    })

                    ->having('bookings_count', '>', 1);
            })->get();
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/business-owner-dashboard",
     *      operationId="getBusinessOwnerDashboardData",
     *      tags={"dashboard_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *      summary="get all dashboard data combined",
     *      description="get all dashboard data combined",
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

    public function getBusinessOwnerDashboardData(Request $request)
    {
        try {
            $this->storeActivity($request, "");

            if (empty(auth()->user()->business_id)) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }

            // Call the method with different time periods
            $data["today_customers"] = $this->getCustomersByPeriod('today');
            $data["this_week_customers"] = $this->getCustomersByPeriod('this_week');
            $data["this_month_customers"] = $this->getCustomersByPeriod('this_month');

            $data["next_week_customers"] = $this->getCustomersByPeriod('next_week');
            $data["next_month_customers"] = $this->getCustomersByPeriod('next_month');

            $data["previous_week_customers"] = $this->getCustomersByPeriod('previous_week');
            $data["previous_month_customers"] = $this->getCustomersByPeriod('previous_month');



            $data["repeated_customers"] = $this->getRepeatedCustomers();

            $data["today_bookings"] = $this->bookingsByStatusCount('today');
            $data["this_week_bookings"] = $this->bookingsByStatusCount('this_week');
            $data["this_month_bookings"] = $this->bookingsByStatusCount('this_month');
            $data["next_week_bookings"] = $this->bookingsByStatusCount('next_week');
            $data["next_month_bookings"] = $this->bookingsByStatusCount('next_month');
            $data["previous_week_bookings"] = $this->bookingsByStatusCount('previous_week');
            $data["previous_month_bookings"] = $this->bookingsByStatusCount('previous_month');



            //  $experts = User::with("translation")
            //  ->where("users.is_active",1)
            //  ->leftJoin('bookings', 'users.id', '=', 'bookings.expert_id')
            //  ->select('users.*', DB::raw('count(bookings.id) as total_bookings'))
            //  ->whereHas('roles', function ($query) {
            //      $query->where('roles.name', 'business_experts');
            //  })
            //  ->where("business_id", auth()->user()->business_id)
            //  ->groupBy('experts.id') // Group by expert ID
            //  ->orderBy('total_bookings', 'desc')
            //  ->get();

            $experts = User::with("translation")
            ->where("users.is_active",1)
                ->leftJoin('bookings', 'users.id', '=', 'bookings.expert_id')
                ->leftJoin('job_payments', 'bookings.id', '=', 'job_payments.booking_id') // Join job_payments on
                ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                    $query->where('users.id', auth()->user()->id);
                })
                ->select(
                    'users.*',
                    DB::raw('SUM(CASE WHEN bookings.job_start_date BETWEEN "' . now()->startOfMonth() . '" AND "' . now()->endOfMonth() . '" THEN job_payments.amount ELSE 0 END) as this_month_revenue'),
                    DB::raw('SUM(CASE WHEN bookings.job_start_date BETWEEN "' . now()->subMonth()->startOfMonth() . '" AND "' . now()->subMonth()->endOfMonth() . '" THEN job_payments.amount ELSE 0 END) as last_month_revenue')
                ) // Sum the amount field for both this month and last month
                ->whereHas('roles', function ($query) {
                    $query->where('roles.name', 'business_experts');
                })
                ->where("business_id", auth()->user()->business_id)
                ->groupBy('users.id') // Group by user ID (expert)
                ->orderBy('this_month_revenue', 'desc') // Order by this month's revenue
                ->get();

            foreach ($experts as $expert) {

                $upcoming_bookings = collect();

                // Get all bookings for the provided date except the rejected ones
                $expert_bookings = Booking::whereDate("bookings.job_start_date", today())
                    ->whereIn("status", ["pending"])
                    ->where([
                        "expert_id" => $expert->id
                    ])
                    ->get();

                foreach ($expert_bookings as $expert_booking) {


                    $booked_slots = $expert_booking->booked_slots;

                    // Convert time strings into Carbon objects
                    $booked_times = array_map(function ($time) {
                        return Carbon::parse($time);
                    }, $booked_slots);

                    // Get the smallest time
                    $smallest_time = min($booked_times);

                    // Get the current time or the input "current_slot"
                    $current_time = request()->input("current_slot")
                        ? Carbon::parse(request()->input("current_slot"))
                        : Carbon::now(); // Use the current time if no input is provided

                    // Compare the smallest booked time with the current time
                    if ($smallest_time->greaterThan($current_time)) {
                        $upcoming_bookings->push($expert_booking);
                    }
                }

                $expert["upcoming_bookings_today"] = $upcoming_bookings->toArray();


                // Get all upcoming bookings for future dates except the rejected ones
                $expert["upcoming_bookings"] = Booking::whereDate("bookings.job_start_date", '>', today())
                    ->whereIn("status", ["pending"])
                    ->where("expert_id", $expert->id)
                    ->get();

                $expert["all_services"] = SubService::whereHas('bookingSubServices.booking', function ($query) use ($expert) {
                    $query->where("bookings.expert_id", $expert->id);
                })
                    ->orderBy('sub_services.name', 'asc') // Sort by this month's sales
                    ->get();


                $expert["average_rating"] = $this->calculateAverageRating($expert->id);


                $expert["today_bookings"] = $this->bookingsByStatusCount('today', $expert->id);
                $expert["this_week_bookings"] = $this->bookingsByStatusCount('this_week', $expert->id);
                $expert["this_month_bookings"] = $this->bookingsByStatusCount('this_month', $expert->id);
                $expert["next_week_bookings"] = $this->bookingsByStatusCount('next_week', $expert->id);
                $expert["next_month_bookings"] = $this->bookingsByStatusCount('next_month', $expert->id);
                $expert["previous_week_bookings"] = $this->bookingsByStatusCount('previous_week', $expert->id);
                $expert["previous_month_bookings"] = $this->bookingsByStatusCount('previous_month', $expert->id);
                $expert["busy_slots"] = $this->blockedSlots(today(), $expert->id);
            }


            $data["top_experts"] = $experts;

            $data["today_revenue"] = $this->revenue('today');
            $data["this_week_revenue"] = $this->revenue('this_week');
            $data["this_month_revenue"] = $this->revenue('this_month');
            $data["next_week_revenue"] = $this->revenue('next_week');
            $data["next_month_revenue"] = $this->revenue('next_month');
            $data["previous_week_revenue"] = $this->revenue('previous_week');
            $data["previous_month_revenue"] = $this->revenue('previous_month');



            $data["top_services"] = SubService::withCount([
                'bookingSubServices as all_sales_count' => function ($query) {
                    $query->whereHas('booking', function ($query) {
                        $query->where('bookings.status', 'converted_to_job') // Filter for converted bookings
                            ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                                $query->where('bookings.expert_id', auth()->user()->id);
                            }); // Sales this month
                    });
                },
                'bookingSubServices as this_month_sales' => function ($query) {
                    $query->whereHas('booking', function ($query) {
                        $query->where('bookings.status', 'converted_to_job') // Filter for converted bookings
                            ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                                $query->where('bookings.expert_id', auth()->user()->id);
                            })
                            ->whereBetween('bookings.job_start_date', [now()->startOfMonth(), now()->endOfMonth()]); // Sales this month
                    });
                },
                'bookingSubServices as last_month_sales' => function ($query) {
                    $query->whereHas('booking', function ($query) {
                        $query->where('bookings.status', 'converted_to_job') // Filter for converted
                            ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                                $query->where('bookings.expert_id', auth()->user()->id);
                            })
                            ->whereBetween('bookings.job_start_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]); // Sales last month
                    });
                }
            ])
                ->orderBy('this_month_sales', 'desc') // Sort by this month's sales
                ->limit(5)
                ->get();



            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }
 /**
     *
     * @OA\Get(
     *      path="/v1.0/expert-report",
     *      operationId="getExpertReport",
     *      tags={"dashboard_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     * @OA\Parameter(
     *     name="start_date",
     *     in="query",
     *     description="Start date for filtering bookings"
     * ),
     * @OA\Parameter(
     *     name="end_date",
     *     in="query",
     *     description="End date for filtering bookings"
     * ),
     * @OA\Parameter(
     *     name="expert_id",
     *     in="query",
     *     description="ID of the expert to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="slots",
     *     in="query",
     *     description="Comma-separated list of slots to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="is_returning_customers",
     *     in="query",
     *     description="Filter for returning customers"
     * ),
     * @OA\Parameter(
     *     name="payment_type",
     *     in="query",
     *     description="Comma-separated list of payment types to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="discount_applied",
     *     in="query",
     *     description="Filter for bookings with or without discounts"
     * ),
     * @OA\Parameter(
     *     name="status",
     *     in="query",
     *     description="Comma-separated list of statuses to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="payment_status",
     *     in="query",
     *     description="Comma-separated list of payment statuses to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="sub_service_ids",
     *     in="query",
     *     description="Comma-separated list of sub-service IDs to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="duration_in_minute",
     *     in="query",
     *     description="Duration in minutes to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="booking_type",
     *     in="query",
     *     description="Comma-separated list of booking types to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="date_filter",
     *     in="query",
     *     description="Filter bookings by date range options"

     * ),
     *      summary="get all dashboard data combined",
     *      description="get all dashboard data combined",
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

     public function getExpertReport(Request $request)
     {
         try {
             $this->storeActivity($request, "");

             if (empty(auth()->user()->business_id)) {
                 return response()->json([
                     "message" => "You are not a business user"
                 ], 401);
             }

             $experts = User::with("translation")
             ->where("users.is_active",1)
                 ->when($request->hasAny([
                     'expert_id',
                     'slots',
                     'is_returning_customers',
                     'payment_type',
                     'discount_applied',
                     'status',
                     'payment_status',
                     'sub_service_ids',
                     'duration_in_minute',
                     'booking_type',
                     'date_filter'
                 ]), function ($query) use ($request) {
                     $query->whereHas("expert_bookings", function ($query) use ($request) {
                         $query->where("bookings.garage_id", auth()->user()->business_id)
                             ->when($request->input("expert_id"), function ($query) {
                                 $query->where("expert_id", request()->input("expert_id"));
                             })
                             ->when($request->filled("slots"), function ($query) {
                                 $slotsArray = explode(',', request()->input("slots"));
                                 $query->where(function ($subQuery) use ($slotsArray) {
                                     foreach ($slotsArray as $slot) {
                                         $subQuery->orWhereRaw("JSON_CONTAINS(bookings.busy_slots, '\"$slot\"')");
                                     }
                                 });
                             })
                             ->when($request->has('is_returning_customers'), function ($q) {
                                 $q->whereHas("customer", function ($query) {
                                     $query->select('bookings.customer_id', DB::raw('COUNT(id) as bookings_count'))
                                         ->groupBy('bookings.customer_id')
                                         ->having('bookings_count', (request()->boolean("is_returning_customers") ? '>' : '='), 1);
                                 });
                             })
                             ->when($request->filled("payment_type"), function ($query) {
                                 $payment_typeArray = explode(',', request()->payment_type);
                                 $query->whereHas("booking_payments", function ($query) use ($payment_typeArray) {
                                     $query->whereIn("job_payments.payment_type", $payment_typeArray);
                                 });
                             })
                             ->when($request->filled("discount_applied"), function ($query) {
                                 if (request()->boolean("discount_applied")) {
                                     $query->where(function ($query) {
                                         $query->where("discount_amount", ">", 0)
                                             ->orWhere("coupon_discount_amount", ">", 0);
                                     });
                                 } else {
                                     $query->where(function ($query) {
                                         $query->where("discount_amount", "<=", 0)
                                             ->orWhere("coupon_discount_amount", "<=", 0);
                                     });
                                 }
                             })
                             ->when((request()->filled("status") && request()->input("status") !== "all"), function ($query) use ($request) {
                                 $statusArray = explode(',', $request->status);
                                 $query->whereIn("status", $statusArray);
                             })
                             ->when(!empty($request->payment_status), function ($query) use ($request) {
                                 $statusArray = explode(',', $request->payment_status);
                                 $query->whereIn("payment_status", $statusArray);
                             })
                             ->when(!empty($request->expert_id), function ($query) use ($request) {
                                 $query->where('bookings.expert_id', request()->input("expert_id"));
                             })
                             ->when(!empty($request->sub_service_ids), function ($query) {
                                 $sub_service_ids = explode(',', request()->sub_service_ids);
                                 $query->whereHas('sub_services', function ($query) use ($sub_service_ids) {
                                     $query->whereIn('sub_services.id', $sub_service_ids)
                                         ->when(!empty(request()->service_ids), function ($query) {
                                             $service_ids = explode(',', request()->service_ids);
                                             $query->whereHas('service', function ($query) use ($service_ids) {
                                                 $query->whereIn('services.id', $service_ids);
                                             });
                                         });
                                 });
                             })
                             ->when($request->filled("duration_in_minute"), function ($query) {
                                 $total_slots = request()->input("duration_in_minute") / 15;
                                 $query->having('total_booked_slots', '>', $total_slots);
                             })
                             ->when(!empty($request->booking_type), function ($query) use ($request) {
                                 $booking_typeArray = explode(',', $request->booking_type);
                                 $query->whereIn("booking_type", $booking_typeArray);
                             })
                             ->when($request->date_filter === 'today', function ($query) {
                                 return $query->whereDate('bookings.job_start_date', Carbon::today());
                             })
                             ->when($request->date_filter === 'this_week', function ($query) {
                                 return $query->whereBetween('bookings.job_start_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                             })
                             ->when($request->date_filter === 'previous_week', function ($query) {
                                 return $query->whereBetween('bookings.job_start_date', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                             })
                             ->when($request->date_filter === 'next_week', function ($query) {
                                 return $query->whereBetween('bookings.job_start_date', [Carbon::now()->addWeek()->startOfWeek(), Carbon::now()->addWeek()->endOfWeek()]);
                             })
                             ->when($request->date_filter === 'this_month', function ($query) {
                                 return $query->whereMonth('bookings.job_start_date', Carbon::now()->month)
                                     ->whereYear('bookings.job_start_date', Carbon::now()->year);
                             })
                             ->when($request->date_filter === 'previous_month', function ($query) {
                                 return $query->whereMonth('bookings.job_start_date', Carbon::now()->subMonth()->month)
                                     ->whereYear('bookings.job_start_date', Carbon::now()->subMonth()->year);
                             })
                             ->when($request->date_filter === 'next_month', function ($query) {
                                 return $query->whereMonth('bookings.job_start_date', Carbon::now()->addMonth()->month)
                                     ->whereYear('bookings.job_start_date', Carbon::now()->addMonth()->year);
                             });
                     });
                 })
                 ->leftJoin('bookings', 'users.id', '=', 'bookings.expert_id')
                 ->leftJoin('job_payments', 'bookings.id', '=', 'job_payments.booking_id')

                 // Join job_payments on
                 ->when(request()->filled("expert_id"), function ($query) {
                     $query->where('users.id', request()->input("expert_id"));
                 })
                 ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                     $query->where('users.id', auth()->user()->id);
                 })
                 ->select(
                     'users.*',
                     DB::raw('SUM(CASE WHEN bookings.job_start_date BETWEEN "' . now()->startOfMonth() . '" AND "' . now()->endOfMonth() . '" THEN job_payments.amount ELSE 0 END) as this_month_revenue'),
                     DB::raw('SUM(CASE WHEN bookings.job_start_date BETWEEN "' . now()->subMonth()->startOfMonth() . '" AND "' . now()->subMonth()->endOfMonth() . '" THEN job_payments.amount ELSE 0 END) as last_month_revenue')
                 ) // Sum the amount field for both this month and last month
                 ->whereHas('roles', function ($query) {
                     $query->where('roles.name', 'business_experts');
                 })
                 ->where("business_id", auth()->user()->business_id)
                 ->groupBy('users.id') // Group by user ID (expert)
                 ->orderBy('this_month_revenue', 'desc') // Order by this month's revenue
                 ->get();
             foreach ($experts as $expert) {
                 // Initialize an array for blocked slots
                 $blockedSlots = []; // Separate variable for blocked slots
                 $appointment_trends = [];

                 if (request()->filled("start_date") && request()->filled("end_date")) {
                     $startDate = Carbon::parse(request()->input("start_date"));
                     $endDate = Carbon::parse(request()->input("end_date"));

                     $date_range = $startDate->isSameDay($endDate) ? [$startDate] : $startDate->daysUntil($endDate->addDay());

                     foreach ($date_range as $date) {
                         $formattedDate = $date->toDateString(); // Format the date to a string for array key
                         // Populate blocked slots for each date
                         $blockedSlots[$formattedDate] = $this->blockedSlots($formattedDate, $expert->id);
                         // Populate appointment trends for each date
                         $appointment_trends[$formattedDate] = $this->get_appointment_trend_data($formattedDate, $expert->id);
                     }

                    }

                 $expert->busy_slots = $blockedSlots;
                 $expert->appointment_trends = $appointment_trends;

                 $expert->feedbacks = ReviewNew::whereHas("booking", function ($query) use ($expert) {
                     $query->where("bookings.expert_id", $expert->id);
                 })
                     ->get();

                 $data["top_services"] = SubService::withCount([
                     'bookingSubServices as all_sales_count' => function ($query) use ($expert) {
                         $query->whereHas('booking', function ($query) use ($expert) {
                             $query->where('bookings.status', 'converted_to_job')
                                 ->when(auth()->user()->hasRole("business_experts"), function ($query) {
                                     $query->where('bookings.expert_id', auth()->user()->id);
                                 })
                                 ->where('bookings.expert_id', $expert->id)
                                 ->when(request()->filled("start_date"), function ($query) {
                                     $query->whereDate("bookings.job_start_date", ">=", request()->input("start_date"));
                                 })
                                 ->when(request()->filled("end_date"), function ($query) {
                                     $query->whereDate("bookings.job_start_date", "<=", request()->input("end_date"));
                                 });
                         });
                     }
                 ])
                     ->orderBy('all_sales_count', 'desc')
                     ->get();




                 // Use object property syntax instead of array-like syntax
                 $expert->today_bookings = $this->bookingsByStatus('today', $expert->id);
                 $expert->this_week_bookings = $this->bookingsByStatus('this_week', $expert->id);
                 $expert->this_month_bookings = $this->bookingsByStatus('this_month', $expert->id);
                 $expert->next_week_bookings = $this->bookingsByStatus('next_week', $expert->id);
                 $expert->next_month_bookings = $this->bookingsByStatus('next_month', $expert->id);
                 $expert->previous_week_bookings = $this->bookingsByStatus('previous_week', $expert->id);
                 $expert->previous_month_bookings = $this->bookingsByStatus('previous_month', $expert->id);



                 $expert->today_revenue = $this->revenue('today', $expert->id);
                 $expert->this_week_revenue = $this->revenue('this_week', $expert->id);
                 $expert->this_month_revenue = $this->revenue('this_month', $expert->id);
                 $expert->next_week_revenue = $this->revenue('next_week', $expert->id);
                 $expert->next_month_revenue = $this->revenue('next_month', $expert->id);
                 $expert->previous_week_revenue = $this->revenue('previous_week', $expert->id);
                 $expert->previous_month_revenue = $this->revenue('previous_month', $expert->id);

                 if (request()->filled("start_date") && request()->filled("end_date")) {
                     $expert->booking_by_date = $this->bookingsByStatus('all', $expert->id);
                     $expert->revenue_by_date = $this->revenue('all', $expert->id);
                 }
             }

             $data["top_experts"] = $experts;





             return response()->json($data, 200);
         } catch (Exception $e) {
             return $this->sendError($e, 500, $request);
         }
     }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/vat-report",
     *      operationId="getVatReport",
     *      tags={"dashboard_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     * @OA\Parameter(
     *     name="start_date",
     *     in="query",
     *     description="Start date for filtering bookings"
     * ),
     * @OA\Parameter(
     *     name="end_date",
     *     in="query",
     *     description="End date for filtering bookings"
     * ),
     * @OA\Parameter(
     *     name="expert_id",
     *     in="query",
     *     description="ID of the expert to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="slots",
     *     in="query",
     *     description="Comma-separated list of slots to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="is_returning_customers",
     *     in="query",
     *     description="Filter for returning customers"
     * ),
     * @OA\Parameter(
     *     name="payment_type",
     *     in="query",
     *     description="Comma-separated list of payment types to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="discount_applied",
     *     in="query",
     *     description="Filter for bookings with or without discounts"
     * ),
     * @OA\Parameter(
     *     name="status",
     *     in="query",
     *     description="Comma-separated list of statuses to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="payment_status",
     *     in="query",
     *     description="Comma-separated list of payment statuses to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="sub_service_ids",
     *     in="query",
     *     description="Comma-separated list of sub-service IDs to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="duration_in_minute",
     *     in="query",
     *     description="Duration in minutes to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="booking_type",
     *     in="query",
     *     description="Comma-separated list of booking types to filter bookings"
     * ),
     * @OA\Parameter(
     *     name="date_filter",
     *     in="query",
     *     description="Filter bookings by date range options"

     * ),
     *      summary="get all dashboard data combined",
     *      description="get all dashboard data combined",
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

    public function getVatReport(Request $request)
    {
        try {
            $this->storeActivity($request, "");

            if (empty(auth()->user()->business_id)) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }

            $vats = Booking::where([
                "garage_id" => auth()->user()->business_id
            ])->when(auth()->user()->hasRole("business_experts"), function ($query) {
                $query->where('bookings.expert_id', auth()->user()->id);
            })
            ->select("bookings.id","bookings.vat_percentage","bookings.vat_amount")
            ->when($request->filled("id"), function ($query) use ($request) {
                return $query
                    ->where("bookings.id", $request->input("id")) // Change to customers.id
                    ->first();
            }, function ($query) {
                return $query->when(!empty(request()->per_page), function ($query) {
                    return $query->paginate(request()->per_page);
                }, function ($query) {
                    return $query->get();
                });
            });

            return response()->json($vats, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }





    /**
     *
     * @OA\Get(
     *      path="/v1.0/superadmin-dashboard",
     *      operationId="getSuperAdminDashboardData",
     *      tags={"dashboard_management.superadmin"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *      summary="get all dashboard data combined",
     *      description="get all dashboard data combined",
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

    public function getSuperAdminDashboardData(Request $request)
    {
        try {
            $this->storeActivity($request, "");
            if (!$request->user()->hasRole('superadmin')) {
                return response()->json([
                    "message" => "You are not a superadmin"
                ], 401);
            }

            $data["garages"] = $this->garages();

            $data["fuel_stations"] = $this->fuel_stations();

            $data["customers"] = $this->customers();

            $data["overall_customer_jobs"] = $this->overall_customer_jobs();

            $data["overall_bookings"] = $this->overall_bookings();

            $data["overall_jobs"] = $this->overall_jobs();



            $data["overall_services"] = $this->overall_services();






            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/data-collector-dashboard",
     *      operationId="getDataCollectorDashboardData",
     *      tags={"dashboard_management.data_collector"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *      summary="get all dashboard data combined",
     *      description="get all dashboard data combined",
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

    public function getDataCollectorDashboardData(Request $request)
    {
        try {
            $this->storeActivity($request, "");
            if (!$request->user()->hasRole('data_collector')) {
                return response()->json([
                    "message" => "You are not a superadmin"
                ], 401);
            }

            $data["garages"] = $this->garages(1);

            $data["fuel_stations"] = $this->fuel_stations(1);

            $data["overall_bookings"] = $this->overall_bookings(1);

            $data["overall_jobs"] = $this->overall_jobs(1);

            //  $data["customers"] = $this->customers();

            //  $data["overall_customer_jobs"] = $this->overall_customer_jobs();



            //  $data["overall_services"] = $this->overall_services();






            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }
}
