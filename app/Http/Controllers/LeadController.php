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
use App\Support\PilotAccess;
use App\Support\OperationalSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    private $operationalSchema;

    public function __construct(OperationalSchema $operationalSchema)
    {
        $this->operationalSchema = $operationalSchema;
    }

   public function index(Request $request){
    
        $params = $request->query();
        $is_all = $request->get('is_all');
       
        $user = auth()->user();
        $role_id = $user->role_id;
        if ((int) $role_id === 1){
            $items = Lead::withTrashed();
            
            if (isset($params['store_id'])){
                $items->where('leads.store_id','=',  $params['store_id']);
            }
        } else if ((int) $role_id === 4){
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
            $keyword = $params['keyword'];
            $items->where(function ($query) use ($keyword) {
                $query->where('leads.customer_name', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('leads.customer_phone', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('leads.vehicle_name', 'LIKE', '%' . $keyword . '%');
            });
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
        $lead = Lead::with('leadLogs.user:name,id')->find($id);
        if ($lead && !$this->canAccessLead(auth()->user(), $lead)) {
            return $this->errorResponse('Bạn không có quyền xem lead của cơ sở khác.', 403);
        }
        if ($lead){
            return $this->successResponse($lead,'Successfully get lead');
        } else {
            return $this->errorResponse('Không tìm thấy lead.', 404);
        }
       
    }
    public function uniqueUsers(){
        $uniqueUsers = Lead::with('user')->distinct('user_id')->whereNotNull('user_id')->whereHas('user', function($query) {
			$query->where('status', 'active');
		})->get(['user_id']);
        return $this->successResponse($uniqueUsers,"Successfully get lead's unique users");
    }
    public function update(Request $request, Lead $lead){
        if (!$this->canAccessLead(auth()->user(), $lead)) {
            return $this->errorResponse('Bạn không có quyền cập nhật lead của cơ sở khác.', 403);
        }
        $data = $this->validateLeadPayload($request->all(), false);
        $user = auth()->user();
        if (!PilotAccess::isAdmin($user) && (int) $user->role_id !== 4) {
            $data['store_id'] = $user->store_id;
        }
        if (!PilotAccess::isAdmin($user)) {
            $data['user_id'] = $user->id;
        }
        try {
            $result = $lead->update($data);
            return $this->successResponse($lead,'Successfully updated lead');
        } catch( \Exception $e){
            Log::error('Unable to update lead.', [
                'lead_id' => $lead->id,
                'exception' => get_class($e),
            ]);
            return $this->errorResponse('Không thể cập nhật lead.', 500);
        } 
        
       
        
    }
    public function destroy(  Lead $lead, Request $request){
        if (!$this->canAccessLead(auth()->user(), $lead)) {
            return $this->errorResponse('Bạn không có quyền xóa lead của cơ sở khác.', 403);
        }
        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);
        $user = User::findOrFail(Auth::id());
        $data = [
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'content' => 'Xóa bởi '
            . $user->name .'. Lý do xóa: '. ($validated['note'] ?? 'Không ghi lý do')
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
        $isBatch = isset($request->all()[0]) && is_array($request->all()[0]);
        $data = $this->validateLeadPayload($request->all(), $isBatch);
        $user = auth()->user();
        if (!PilotAccess::isAdmin($user) && (int) $user->role_id !== 4) {
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data as &$leadData) {
                    $leadData['store_id'] = $user->store_id;
                }
                unset($leadData);
            } else {
                $data['store_id'] = $user->store_id;
            }
        }
        if (!PilotAccess::isAdmin($user)) {
            if ($isBatch) {
                foreach ($data as &$leadData) {
                    $leadData['user_id'] = $user->id;
                }
                unset($leadData);
            } else {
                $data['user_id'] = $user->id;
            }
        }
        try {
            $result =  $this->createFromArray($data);
            return $this->successResponse($result,'Successfully created lead');
        } catch( \Exception $e){
            Log::error('Unable to create lead.', [
                'exception' => get_class($e),
            ]);
            return $this->errorResponse('Không thể tạo lead.', 500);
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
       
      
        return $createdLeads;
        
    }


  
    private function createOne($leadData){
        if (isset($leadData['entry_id'])) {
            $existingLead = Lead::where('entry_id', $leadData['entry_id'])->first();
            if ($existingLead) {
                $existingLead->update($leadData);
                return $existingLead;
            }  
        }      
        if (!isset($leadData['status'])){
            $leadData['status'] = 'pending';
        }
        $createdLead = Lead::create($leadData);
        return $createdLead;
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

    private function canAccessLead($user, Lead $lead): bool
    {
        if (!$user) {
            return false;
        }
        if (PilotAccess::isAdmin($user) || (int) $user->role_id === 4) {
            return true;
        }

        return $user->store_id && (int) $user->store_id === (int) $lead->store_id;
    }

    private function validateLeadPayload(array $payload, bool $isBatch): array
    {
        $prefix = $isBatch ? '*.' : '';
        $validator = Validator::make($payload, [
            $prefix . 'entry_id' => 'nullable|integer',
            $prefix . 'customer_name' => 'nullable|string|max:255',
            $prefix . 'customer_phone' => 'required|string|max:30',
            $prefix . 'vehicle_name' => 'nullable|string|max:255',
            $prefix . 'store_id' => 'nullable|integer|exists:stores,id',
            $prefix . 'pickup_location' => 'nullable|string|max:255',
            $prefix . 'rent_at' => 'nullable|date',
            $prefix . 'return_at' => 'nullable|date|after_or_equal:' . $prefix . 'rent_at',
            $prefix . 'status' => 'nullable|string|max:50',
            $prefix . 'note' => 'nullable|string|max:2000',
            $prefix . 'user_id' => 'nullable|integer|exists:users,id',
            $prefix . 'source_channel' => 'nullable|string|max:100',
            $prefix . 'campaign_name' => 'nullable|string|max:150',
            $prefix . 'utm_source' => 'nullable|string|max:150',
            $prefix . 'utm_campaign' => 'nullable|string|max:150',
            $prefix . 'created_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        if (!$this->operationalSchema->isReady('kpi')) {
            $attributionFields = ['source_channel', 'campaign_name', 'utm_source', 'utm_campaign'];
            if ($isBatch) {
                foreach ($validated as &$leadData) {
                    foreach ($attributionFields as $field) {
                        unset($leadData[$field]);
                    }
                }
                unset($leadData);
            } else {
                foreach ($attributionFields as $field) {
                    unset($validated[$field]);
                }
            }
        }

        return $validated;
    }
}


 



