<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\LeadLog;

class LeadController extends Controller
{
   public function index(Request $request){
    
        $params = $request->query();
        $is_all = $request->get('is_all');
       
        $user = auth()->user();
        $role_id = $user->role_id;
        if ($role_id === 1){
            $items = Lead::withTrashed();
            
            if (isset($params['store_id'])){
                $items->where('leads.store_id','=',  $params['store_id']);
            }
        } else if ($role_id === 4){
            $items = Lead::whereNull('deleted_at');
            if (isset($params['store_id'])){
                $items->where('leads.store_id','=',  $params['store_id']);
            }
        }else{
            $items = Lead::whereNull('deleted_at');
            $store_id = $user->store_id;
            $items->where('leads.store_id','=',  $store_id);
        }
        $items->with('user');
        $items     
        ->with(['leadLogs' => function ($query) {
            $query->with('user:id,name')
            ->orderBy('id', 'DESC');
        }]);
        
        
        if (isset($params['source'])){
            if (count($params['source']) > 0){
                $sourceIds = $params['source'];
                if (in_array('NULL', $sourceIds)) {
                    $items->where(function($query) use ($sourceIds) {
                        $query->whereIn('leads.user_id', array_diff($sourceIds, ['NULL']))
                              ->orWhereNull('leads.user_id');
                    });
                } else {   
                    $items->whereIn('leads.user_id', $sourceIds);
                }
            } else {
                $items->whereNull('leads.user_id');
            }
        }

        if (isset($params['status'])){

            $items->where('leads.status',    $params['status'] );
           
        }

        
        if (isset($params['keyword'])){

            $items->where('leads.customer_name','LIKE', '%' . $params['keyword'] . '%')
            ->orWhere('leads.customer_phone', 'LIKE', '%' . $params['keyword'] . '%');
        }
          
        if (isset($params['leads_for_order'])){

            $items
            ->where('leads.customer_phone', 'LIKE', '%' . $params['customer_phone'] . '%')
            ->where('leads.status','pending');
            
        }
     
        if (isset($params['start_date'])){
            $items->where('leads.created_at','>=',  $params['start_date']);
        }
        if (isset($params['end_date'])){
            $items->where('leads.created_at','<=',  $params['end_date']);
        }
        $items->orderBy('leads.id', 'DESC');
        if ($is_all){
             
            return $this->successResponse($items->get(),'Successfully get lead');
        } else{
            
            $paginator = $items->paginate(config('app.paginate', 20));
            return $this->successResponse($paginator,'Successfully get lead');
        }
      
    }

    public function show(Request $request, $id){
        $lead = Lead::find($id)->load('leadLogs.user:name,id');
        if ($lead){
            return $this->successResponse($lead,'Successfully get lead');
        } else {
            return $this->errorResponse('No lead');
        }
       
    }
    public function uniqueUsers(){
        $uniqueUsers = Lead::with('user')->distinct('user_id')->whereNotNull('user_id')->whereHas('user', function($query) {
			$query->where('status', 'active');
		})->get(['user_id']);
        return $this->successResponse($uniqueUsers,"Successfully get lead's unique users");
    }
    public function update(Request $request, Lead $lead){
        $data = $request->input();
        try {
            $result = $lead->update($data);
            return $this->successResponse($lead,'Successfully updated lead');
        } catch( \Exception $e){
        
            return $this->errorResponse('Error when updating lead',200,$e->getMessage());
        } 
        
       
        
    }
    public function destroy(  Lead $lead, Request $request){
        $user = User::find(Auth::id());
        $data = [
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'content' => 'Xóa bởi '
            . $user->name .'. Lý do xóa: '. $request->note
            , 
            'metadata' => json_encode([]),
        ];
   
       LeadLog::create($data);
      
        $lead->status = 'deleted';
        $lead->save();
         
        $res = $lead->delete();  
      
     
        

        return $this->successResponse($res,'Successfully deleted lead');
    }
    public function create(Request $request){
        $data = $request->input();
        try {
            $result =  $this->createFromArray($data);
            return $this->successResponse($result,'Successfully created lead');
        } catch( \Exception $e){
        
            return $this->errorResponse('Error when creating lead',200,$e->getMessage());
        } 
       
    }
    private function createFromArray($data){
        $createdLeads = [];
        if ( isset($data[0]) && is_array($data[0])){
            foreach ($data as $lead){
                 
                $createdLeads[] = $this->createOne($lead);
            }
        } else {
          
            $createdLeads[] =   $this->createOne($data);
        }
       
      
        $result = json_encode($createdLeads);
        return $result ;
        
    }


  
    private function createOne($leadData){
        if (isset($leadData['entry_id'])) {
            $existingLead = Lead::where('entry_id', $leadData['entry_id'])->first();
            if ($existingLead) {
                $existingLead->update($leadData);
                $results[] = $existingLead;
                return $results;
            }  
        }      
        if (!isset($leadData['status'])){
            $leadData['status'] = 'pending';
        }
        $createdLead = Lead::create($leadData);
        $results[] = $createdLead;
        return $results;
    }
     
    public function insertIgnoreMany($data){
        $createdLeads = [];
        if ( isset($data[0]) && is_array($data[0])){
            foreach ($data as $lead){
                 
                $createdLeads[] = $this->insertIgnoreOne($lead);
            }
        } else {
          
            $createdLeads[] =   $this->insertIgnoreOne($data);
        }
       
      
        $result = json_encode($createdLeads);
        return $this->successResponse($result,'Successfully created lead');
    }
    public function insertIgnoreOne($leadData){
        if (isset($leadData['entry_id'])) {
            $existingLead = Lead::withTrashed()->where('entry_id', $leadData['entry_id'])->first();     
            if ($existingLead) {
               
                $results[] = $existingLead;
            } else {
                
                $createdLead = Lead::create($leadData);
                $results[] = $createdLead;
            }
        } else {
            
            $createdLead = Lead::create($leadData);
            $results[] = $createdLead;
        }
        return $results;
    }
}


 



