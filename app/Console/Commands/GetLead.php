<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use App\Models\MaintenanceVehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Http\Controllers\LeadController;
use App\Models\Store;

class GetLead extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feature:get-lead';
    protected $leadController;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan và lấy lead từ Wordpress site';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(LeadController $leadController)
    {
        parent::__construct();
        $this->leadController = $leadController;    
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        info('Scan và lấy lead từ Wordpress site');
        try {
            $client = new Client();
			$url = config('services.wordpress_leads.url');
			$token = config('services.wordpress_leads.token');

			if (!$url || !$token) {
				throw new \RuntimeException('Thiếu cấu hình WORDPRESS_LEADS_URL hoặc WORDPRESS_LEADS_TOKEN.');
			}

            $response = $client->request('GET', $url, [
                'headers' => [
					'Authorization' => 'Bearer ' . $token,
                ],
            ]);
    
            $body = $response->getBody()->getContents();
            
            $responseData = json_decode($body, true);
           
            $this->saveLeads($responseData);
    
            // \Log::info('Response from remote server: ' . $body );
        } catch (\Exception $e) {
            \Log::error('Error ' . $e->getMessage());
        }
    
        dump('Done: Scan và lấy lead từ Wordpress site');
    }
    public function saveLeads($data){
         \Log::info(count($data));
         usort($data, function($a, $b) {
            return intval($a['entry_id']) <=> intval($b['entry_id']);
        });
        $leads = $this->convertData($data);
        try {
            foreach ($leads as $leadData) {
                $res = $this->leadController->insertIgnoreMany($leadData); 
            }
            \Log::info('Leads saved successfully.');
        } catch (\Exception $e) {
            \Log::error('Error saving leads: ' . $e->getMessage());
        }
    }
    public function convertData($data){
      
        $leads = array_map(function ($item){
             
            $item['data']['entry_id'] = $item['entry_id'];
            $item['data']['date_created'] = $item['date_created'];
             
            return $this->convertLead($item['data']);
        },$data);
        return $leads;
    }
   public function convertLead($data){
    $lead = [];
    
    $fields = [17 => 'customer_name', 3 => 'customer_phone', 16 => 'vehicle_name', 5 => 'store_id', 12 => 'pickup_location', 11 => 'rent_at', 14 => 'return_at'];

    foreach ($fields as $fieldId => $fieldName) {
        $value = isset($data[$fieldId]['value']) ? $data[$fieldId]['value'] : null;
        $lead[$fieldName] = ($value !== '') ? $value : null;
    }

    $lead['status'] = 'pending';
    $lead['created_at'] = $data['date_created'];
    $lead['entry_id'] = $data['entry_id'];
    $lead['store_id'] = $this->matchStoreId( $lead['store_id']);
    $lead['user_id'] = NULL;
    return $lead;
   }
   public function matchStoreId($address){
    $storeName = explode(',', $address)[0];
    $store = Store::where('store_name', 'like', trim($storeName))->first();
   
    return $store ? $store->id : null;
       
    
   }
}
