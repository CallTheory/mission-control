<?php

namespace App\Http\Controllers\API\Integrations;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Customer;
use Stripe\InvoiceItem;
use Stripe\Stripe;
use Illuminate\Support\Facades\Log;

class StripeFillOutController extends Controller
{

    private string $redirectOnSuccess;
    private string $redirectOnError;
    function __construct(){
        $this->redirectOnSuccess = 'https://example.com/thanks';
        $this->redirectOnError = 'https://example.com/thanks';
    }


    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));

        $validator = Validator::make($request->all(), [
            'rate_plan' => 'required|string',
            'company_name' => 'required|string',
            'billing_email' => 'required|email',
            'address' => 'required|string',
            'bandwidth_number' => 'required|string|max:10'
        ]);

        if ($validator->fails()) {
            Log::error('FillForm: Failed Validation',  ['request' => $request->all(), 'errors' => $validator->errors()->toArray() ]);
            return redirect()->to( $this->redirectOnError );
        }

        try{
            $customer = Customer::search([
                'query' => "email:'{$request->get('billing_email')}'",
            ]);

            if( count($customer->data) > 0){
                return response()->redirectTo($customer->data[0]->redirectToBillingPortal( $this->redirectOnSuccess ));
            }
            else{
                $customer = Customer::create([
                    'name' => $request->get('company_name'),
                    'email' => $request->get('billing_email'),
                    'description' => substr($request->get('bandwidth_number'), -4 ) . " - " . $request->get('company_name'),
                ]);
            }

        }catch( Exception $e){
            Log::error('FillForm: Failed Stripe Customer Creation',  [$e->getMessage()] );
            return redirect()->to( $this->redirectOnError );
        }

        try{
            InvoiceItem::create([
                'customer' => $customer->id,
                'pricing' => $request->get('rate_plan')

            ]);
        }catch( Exception $e){
            Log::error('FillForm: Failed Invoice Creation',  [$e->getMessage()] );
            return redirect()->to( $this->redirectOnError );
        }

        return response()->redirectTo($customer->data[0]->redirectToBillingPortal( $this->redirectOnSuccess ));
    }
}
