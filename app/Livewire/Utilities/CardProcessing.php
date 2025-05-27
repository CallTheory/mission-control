<?php

namespace App\Livewire\Utilities;

use App\Models\DataSource;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use NumberFormatter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CardProcessing extends Component
{
    use WithFileUploads;

    public $tbsExportFile;

    public $file_path;

    public $records;

    public $headers;

    public $datasource;

    public $processResults;

    public function mount(): void
    {
        $this->file_path = null;
        $this->records = null;
        $this->processResults = null;

        $existing_import_file = session()->get('utilities.card-processing.import_file');
        $existing_import_records = session()->get('utilities.card-processing.import_records');
        $existing_import_headers = session()->get('utilities.card-processing.import_headers');
        $existing_process_results = session()->get('utilities.card-processing.process_results');

        if ($existing_import_file !== null) {
            $this->file_path = json_decode($existing_import_file, true);
        }

        if ($existing_import_records !== null) {
            $this->records = json_decode($existing_import_records, true);
        }

        if ($existing_import_headers !== null) {
            $this->headers = json_decode($existing_import_headers, true);
        }

        if ($existing_process_results !== null) {
            $this->processResults = json_decode($existing_process_results, true);
        } else {
            $this->processResults['charges'] = [];
            $this->processResults['failures'] = [];
        }

        $this->datasource = DataSource::first();

        if (! $this->datasource) {
            abort(400, 'No data source');
        }
    }

    /**
     * @throws ApiErrorException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function processCardsSmallBatch($production = false)
    {
        //production means CHARGE CLIENTS SO BE CAREFUL
        if ($production === true) {
            if (strlen(decrypt($this->datasource->stripe_prod_secret_key))) {
                Stripe::setApiKey(decrypt($this->datasource->stripe_prod_secret_key));
                $stripe = new StripeClient(decrypt($this->datasource->stripe_prod_secret_key));
            } else {
                try{
                    $validator = Validator::make(['processing' => decrypt($this->datasource->stripe_prod_secret_key)], ['processing' => 'required'], [
                        'processing' => 'Missing production Stripe API key. Please check system data sources.',
                    ]);
                }
                catch(Exception $e){
                    return response()->withErrors(['processing' => 'Missing production Stripe API key. Please check system data sources.'])->withInput();
                }

                if ($validator->fails()) {
                    return response()->withErrors($validator)->withInput();
                }
            }
        } else {
            if (strlen(decrypt($this->datasource->stripe_test_secret_key))) {
                Stripe::setApiKey(decrypt($this->datasource->stripe_test_secret_key));
                $stripe = new StripeClient(decrypt($this->datasource->stripe_test_secret_key));
            } else {
                try{
                    $validator = Validator::make(['processing' => decrypt($this->datasource->stripe_test_secret_key)], ['processing' => 'required'], [
                        'processing' => 'Missing test Stripe API key. Please check system data sources.',
                    ]);
                }
               catch(Exception $e){
                   return response()->withErrors(['processing' => 'Missing test Stripe API key. Please check system data sources.'])->withInput();
               }

                if ($validator->fails()) {
                    return response()->withErrors($validator)->withInput();
                }
            }
        }

        $fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        $curr = 'USD';

        $batchCurrent = 0;
        $batchCutoff = 10;

        foreach ($this->records as $k => $record) {
            if ($record['processed'] === 'no') {
                if (strlen($record[5]) && $fmt->parseCurrency($record[7], $curr) > 0.00) {
                    try {
                        $customer = $stripe->customers->retrieve(
                            $record[5],
                            ['expand' => ['invoice_settings']]
                        );
                    } catch (Exception $e) {
                        //If the customer is null, don't charge
                        //But if we aren't in production, grab the first client to charge against
                        if ($production === true) {
                            $customer = null;
                        } else {
                            $customers = $stripe->customers->all(['limit' => 10]);
                            $customer = $customers->data[5] ?? null;
                        }
                    }

                    if ($customer) {
                        if ($customer->default_source) {
                            try {
                                $payment_method = PaymentMethod::retrieve($customer->default_source);
                                $payment_method->attach(['customer' => $customer->id]);

                                try {
                                    $pi = PaymentIntent::create([
                                        //'setup_future_usage' => 'off_session',
                                        'payment_method' => $customer->default_source,
                                        'amount' => $fmt->parseCurrency($record[7], $curr) * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer->id,
                                        'description' => $record[5],
                                        'confirm' => true,
                                        'off_session' => true,
                                    ]);

                                    $this->processResults['charges'][$record[5]] = $pi->toArray();
                                } catch (Exception $e) {
                                    $this->processResults['failures'][$record[5]] = array_merge($record, ['results' => $e->getMessage()]);
                                }
                            } catch (Exception $e) {
                                $this->processResults['failures'][$record[5]] = array_merge($record, ['results' => $e->getMessage()]);
                            }
                        } else {
                            //check if they have a default payment method set under invoice settings
                            $invoice_settings = $customer->invoice_settings ?? null;

                            if (! isset($invoice_settings->default_payment_method) || $invoice_settings->default_payment_method === null) {
                                $this->processResults['failures'][$record[5]] = array_merge($record, ['results' => 'No default payment method set for Stripe customer']);
                            } else {
                                $payment_method = PaymentMethod::retrieve($invoice_settings->default_payment_method);
                                $payment_method->attach(['customer' => $customer->id]);

                                try {
                                    $pi = PaymentIntent::create([
                                        //'setup_future_usage' => 'off_session',
                                        'payment_method' => $payment_method->id,
                                        'amount' => $fmt->parseCurrency($record[7], $curr) * 100,
                                        'currency' => 'usd',
                                        'customer' => $customer->id,
                                        'description' => $record[5],
                                        'confirm' => true,
                                        'off_session' => true,
                                    ]);

                                    $this->processResults['charges'][$record[5]] = $pi->toArray();
                                } catch (Exception $e) {
                                    $this->processResults['failures'][$record[5]] = array_merge($record, ['results' => $e->getMessage()]);
                                }
                            }
                        }
                    } else {
                        $this->processResults['failures'][$record[5]] = array_merge($record, ['results' => "No matching Stripe customer found ({$record[5]})"]);
                    }
                }

                //get existing data
                $existingEntries = session()->get('utilities.card-processing.process_results');

                if ($existingEntries !== null) {
                    session()->put('utilities.card-processing.process_results', json_encode(array_merge(json_decode($existingEntries, true), $this->processResults)));
                } else {
                    session()->put('utilities.card-processing.process_results', json_encode($this->processResults));
                }

                $this->records[$k]['processed'] = 'yes';
                session()->put('utilities.card-processing.import_records', json_encode($this->records));
                $customer = null;

                if ($batchCurrent >= $batchCutoff) {
                    break;
                }

                $batchCurrent++;
            }
        }

        return true;
    }

    public function downloadExportFile(): BinaryFileResponse
    {
        if (isset($this->processResults['charges']) && count($this->processResults['charges'])) {
            $export_tz = 'America/Chicago';
            $export_file_name = 'LBWEBEXPRESS_'.Carbon::now($export_tz)->format('ymdHis').'.DAT';

            $rows = [];

            foreach ($this->processResults['charges'] as $stripeCustomerID => $charge) {

                if(is_array($charge)) {

                    $arr = collect(array_filter($this->records, function ($k) {
                        if (strlen($k[5])) {
                            return true;
                        }
                        return false;
                    }));

                    $filtered = $arr->where(5, $stripeCustomerID);
                    $record = $filtered->first();

                    //American Express, Diners Club, Discover, JCB, MasterCard, UnionPay, Visa, or Unknown
                    $cardBrands['visa'] = 'VISA';
                    $cardBrands['mastercard'] = 'MC';
                    $cardBrands['amex'] = 'AMEX';
                    $cardBrands['discover'] = 'DCVR';
                    $cardBrands['jcb'] = 'JCB';

                    Log::info('Billing Charge Export', $charge);
                    Log::info('Billing Charges Export', $this->processResults['charges']);

                    try {
                        // $.charges.data.[0].payment_method_details.card.brand
                        if (!isset($cardBrands[$charge['charges']['data'][0]['payment_method_details']['card']['brand']])) {
                            $cardBrand = 'CC';
                        } else {
                            $cardBrand = $cardBrands[$charge['charges']['data'][0]['payment_method_details']['card']['brand']];
                        }
                    } catch (Exception $e) {
                        $cardBrand = 'CC';
                    }
                    /*
                    * |PaymentDate|CustRefCode|CustAccount|Invoice#|PaymentType|PymtID|AuthCode|ApprovedAmount|
                    * Example:
                    * |20210812|9808001| A2001|<Invoice number is optional>|VISA|….2345|B29348G|243.99|
                    */

                    $rows[] = [
                        Carbon::now($export_tz)->format('Ymd'), // PaymentDate
                        $record[2], // CustRefCode
                        $record[3], // CustAccount
                        $charge['id'], ## $charge['charges']['data'][0]['id'], // Invoice#
                        $cardBrand, // PaymentType
                        substr($charge['id'], 3, 4), ## substr($charge['charges']['data'][0]['id'], 3, 4), // PaymtID
                        substr($charge['id'], -8), ## substr($charge['charges']['data'][0]['id'], -8), // AuthCode
                        number_format((float)($charge['amount_received'] / 100), 2, '.', ''), // ApprovedAmount
                    ];
                }
                else{
                    Log::info('Billing Broken Charge', $charge ?? []);
                }
            }

            $dat_output = '';

            foreach ($rows as $row) {
                $dat_output .= '|'.implode('|', $row)."|\r\n";
            }

            session()->put('utilities.card-processing.export_file', $export_file_name);

            File::put(storage_path('app/card-processing/'.$export_file_name), $dat_output);

            return response()->download(storage_path('app/card-processing/'.$export_file_name));
        }

        abort(404);
    }

    public function save(): void
    {
        $results = [];

        $this->validate([
            'tbsExportFile' => 'file|max:2048', // 2MB Max
        ]);

        $this->file_path = $this->tbsExportFile->store('card-processing');

        $csv = Storage::get($this->file_path);

        $lines = explode("\r\n", $csv);

        $headers = array_slice($lines, 0, 1);
        $headers = str_getcsv($headers[0]);

        //Array entries 0-16 are page header/report header details
        //We don't need them.

        for ($i = 0; $i <= 16; $i++) {
            unset($headers[$i]);
        }
        for ($i = 25; $i <= 37; $i++) {
            unset($headers[$i]);
        }

        $this->headers = array_values($headers);

        $csv_by_line = $lines;

        foreach ($csv_by_line as $k => $row) {
            $temp = str_getcsv($row);

            if (count($temp) >= 38) {
                $r = [
                    $temp[25],
                    $temp[26],
                    $temp[27],
                    $temp[28],
                    $temp[29],
                    $temp[30],
                    $temp[31],
                    $temp[32],
                ];

                //re-index our array keys and add a processed tracker
                $results[$k] = array_merge(array_values($r), ['processed' => 'no']);
            }
        }

        $this->records = $results;

        session()->put('utilities.card-processing.import_file', json_encode($this->file_path));
        session()->put('utilities.card-processing.import_records', json_encode($this->records));
        session()->put('utilities.card-processing.import_headers', json_encode($this->headers));

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.utilities.card-processing');
    }

    public function clearSession(): void
    {
        session()->flush();
        $this->mount();
        $this->render();
    }
}
