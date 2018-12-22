<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Braintree;
use Auth;
use App\Siteinfo;


class BraintreeController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->admin = Auth::user()->authorizeRoles(['admin']);
            return $next($request);
        });
    }



    /**
     * Get all plans
     *
     * @return void
     */
    public function getAllPlans()
    {
        try {
            $getPlanFromApi = \Braintree\Plan::all();
            $getPlanFromDB = Braintree::select('plan_id')->get();
            $arrayPlan = [];
            $fPDB = [];

            foreach ($getPlanFromDB as $k => $v) {
                array_push($fPDB, $v->plan_id);
            }

            if(count($getPlanFromApi) > 0) {

                foreach ($getPlanFromApi as $apiKey => $value) {
                    if (in_array($value->id, $fPDB)) {
                        $arrayPlan[$apiKey] = ['active' => true, 'plan' => $value];
                    } else {
                        $arrayPlan[$apiKey] = ['active' => false, 'plan' => $value];
                    }

                }

            }else {
                $arrayPlan = null;
            }


            return response()->json([
                'status' => 'success',
                'data' =>  $arrayPlan,
                'payment_gateway_status' => Siteinfo::find(1)->payment_status
            ], 200);

        }
        catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' =>  "Braintree is not connected, Please check your Braintree account info is correct in .env file",
            ], 200);
        }


    }

  /**
     * Create plan
     *
     * @return void
     */
    public function createPlan(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('app.stripe_private'));
     
       $create =  \Stripe\Plan::create(array(
            "amount" => $request->amount,
            "interval" => $request->interval,
            "product" => array(
              "name" => $request->product_name,
            ),
            "currency" => $request->currency,
            "id" => $request->id,
            "trial_period_days" => $request->trial_period_days 
          ));
     

        return response()->json(['status' => 'success', 'message' => 'Successful create plan', 'data' => $create], 200);   
    }

    /**
     * Retrieve plan
     *
     * @return void
     */
    public function retrievePlan($id)
    {
        \Stripe\Stripe::setApiKey(config('app.stripe_private'));
     
        $getPlan =  \Stripe\Plan::retrieve($id);
        $getProduct =  \Stripe\Product::retrieve($getPlan->product);

        return response()->json(['status' => 'success',  'data' => $getPlan, 'product' =>  $getProduct], 200);   
    }

    /**
     * Update plan
     *
     * @return void
     */
    public function updatePlan(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('app.stripe_private'));
     
        $getPlan =  \Stripe\Plan::retrieve($request->id);
        $getPlan->trial_period_days = $request->trial_period_days;
        $getPlan->save();

        $product = \Stripe\Product::retrieve($getPlan->product);
        $product->name = $request->product_name;
        $product->save();

        return response()->json(['status' => 'success', 'message' => 'Successful update plan', 'type' => 'update'], 200);
    }


    /**
     * Active plan
     *
     * @return void
     */
    public function activePlan(Request $request)
    {

        $check = Braintree::where('plan_id', $request->plan_id)->first();
        if( is_null($check) ) {

            $create = new Braintree();
            $create->plan_id       = $request->plan_id;
            $create->plan_name     = $request->plan_name;
            $create->plan_amount   = $request->plan_amount;
            $create->plan_currency = $request->plan_currency;
            $create->plan_trial    = $request->plan_trial;
            $create->save();

            return response()->json(['status' => 'success', 'message' => 'Successful add plan', 'type' => 'add'], 200);
        }else{

            $check->delete();

            return response()->json(['status' => 'success', 'message' => 'Successful delete plan', 'type' => 'delete'], 200);   
        }
    }



    /**
     * Get all coupon
     *
     * @return void
     */
    public function getCoupons()
    {
        \Stripe\Stripe::setApiKey(config('app.stripe_private'));
        $getAllCoupons = \Stripe\Coupon::all();

        return response()->json(['status' => 'success', 'data' => $getAllCoupons], 200);   
        
    }


    /**
     * Delete coupon
     *
     * @return void
     */
    public function deleteCoupon($id)
    {
        \Stripe\Stripe::setApiKey(config('app.stripe_private'));
        $getAllCoupons = \Stripe\Coupon::all();
        $cpn = \Stripe\Coupon::retrieve($id);
        $cpn->delete();

        if($cpn->deleted) {
        return response()->json(['status' => 'success', 'message' => 'Successful delete coupon', 'type' => $cpn], 200);   
        }else {
            return response()->json(['status' => 'error', 'message' => 'Error in delete'], 442);   

        }
    }


    /**
     * Create coupon
     *
     * @return void
     */
    public function createCoupon(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('app.stripe_private'));
     
        if(!empty($request->redeem_by) ) {
       $create =  \Stripe\Coupon::create(array(
            "id" =>  $request->id,
            "amount_off" => $request->amount_off,
            "currency" => $request->currency,
            "duration" => $request->duration,
            "duration_in_months" => $request->duration_in_months,
            "max_redemptions" => $request->max_redemptions,
            "percent_off" => $request->percent_off,
            "redeem_by" => ( !empty($request->redeem_by) ? strtotime($request->redeem_by) : "" )
            ));
        }else {
            $create =  \Stripe\Coupon::create(array(
                "id" =>  $request->id,
                "amount_off" => $request->amount_off,
                "currency" => $request->currency,
                "duration" => $request->duration,
                "duration_in_months" => $request->duration_in_months,
                "max_redemptions" => $request->max_redemptions,
                "percent_off" => $request->percent_off
                ));
        }

        return response()->json(['status' => 'success', 'message' => 'Successful create coupon', 'type' => $create], 200);   
    }


    public function DisabledPayment() {
        $getInfo = Siteinfo::find(1);

        if($getInfo !== null) {
            if ($getInfo->payment_status) {
                $getInfo->payment_status = 0;
                $getInfo->save();
                return response()->json([
                    'status' => 'success',
                    'type' => 'inactive',
                    'message' => 'Successful inactive all payment gateway',
                ], 200);
            } else {
                $getInfo->payment_status = 1;
                $getInfo->save();
                return response()->json([
                    'status' => 'success',
                    'type' => 'active',
                    'message' => 'Successful active all payment gateway',
                ], 200);
            }
        }
    }
}
