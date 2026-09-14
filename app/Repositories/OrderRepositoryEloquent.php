<?php


namespace App\Repositories;


use App\Models\Order;
use App\Validators\OrderValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\Log;

class OrderRepositoryEloquent extends BaseRepository implements OrderRepository
{
    /**
     * Specify Model class namel
     *
     * @return string
     */
    public function model()
    {
        return Order::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function index(Request $request)
    {
		$per_page = config('app.paginate', 20);
		if ( $request->get('per_page') && is_numeric( $request->get('per_page') ) && $request->get('per_page') > 0 ) {
			$per_page = $request->get('per_page');
		}
        $params = $request->all();
  

        $query = $this->getModel()->newQuery()->select('orders.*', 'leads.user_id as lead_user_id')->leftJoin('leads', 'leads.order_id', '=', 'orders.id')->with([
			'addOnOrders',
			'transactions'=>function($query){
				$query->with(['bank','user:id,name'])->orderBy('id','desc');
			},
			'activityLogs' => function ($query) {
				$query->with('user:id,name')->orderBy('created_at', 'desc'); 
			},
			'vehicles' => function ($q) {
				$q->select(['vehicles.id as vehicle_id', 'name', 'license']);
			},
			'store:id,store_name', 'orderItems.vehicle', 'customer:id,name,phone,id_card,address','leads.user'])
        ;

        $orders=$this->getOrderByParams($query,$params);
    
        $user = auth()->user();
        if ($user->role_rel->slug !== 'quan-tri-vien') {
            $orders->where('orders.store_id', $user->store_id);
        }

        if ($request->get('is_all')) {
            return $orders->get();
        }
   
        return $orders->orderBy('orders.id', 'desc')->paginate($per_page);
    }

    public function getOrderByParams($query, $params) {
        if ( isset( $params['store_id'])) {
            $query->where('orders.store_id', $params['store_id']);   // orders.id because this will be used in OrderExport
        }
      
        if ( isset($params['start_date'])) {
            // orders.created_at because this will be used in OrderExport
            $query = $query->whereDate('orders.created_at', '>=',  $params['start_date']);
        }
        if (  isset($params['end_date'])  ) {
          
            $query = $query->whereDate('orders.created_at', '<=', $params['end_date']);
        }

        if (   isset($params['order_status']) ) {
            $order_status = $params['order_status'];
            $query->where('order_status', $order_status );
        }

        if (isset($params['source']) && is_array($params['source'])){
            $query->has('leads');
            if (count($params['source']) > 0){
                $sourceIds = $params['source'];
                if (in_array('NULL', $sourceIds)) {
                    $query->where(function($query) use ($sourceIds) {
                        $query->whereIn('leads.user_id', array_diff($sourceIds, ['NULL']))
                              ->orWhereNull('leads.user_id');
                    });
                } else {   
                    $query->whereIn('leads.user_id', $sourceIds);
                }
            } else {
                $query->whereNull('leads.user_id');
            }
        }

        if (  isset($params['is_out_of_date'])  ) {
        
            if ( $params['is_out_of_date'] == 'true' ) {
                $query->where('out_dated_at', '>', 0);
            } else if ( $params['is_out_of_date'] == 'expired-gt-3days' ) {
                $query->where('out_dated_at', '>', 3 * 24 * 60);
            } else if ( $params['is_out_of_date'] == 'expired-gt-10days' ) {
                $query->where('out_dated_at', '>', 10 * 24 * 60);
            }
            
        }
        if (isset($params['keyword'])) {
            $this->applyKeywordFilter($query, (string) $params['keyword']);
        }
     
        return $query;
    }

    public function applyKeywordFilter($query, string $keyword)
    {
        $keyword = trim($keyword);
        $orderId = null;
        if (is_numeric($keyword)) {
            $orderId = intval($keyword);
        } elseif (preg_match('/^#(\d+)$/', $keyword, $matches)) {
            $orderId = intval($matches[1]);
        }

        $query->where(function($query) use ($keyword, $orderId) {
            if ($orderId !== null) {
                $query->where('orders.id', $orderId);
            } else {
                $query->whereRaw('1=0');
            }
            $query->orWhere('orders.contract_number', 'LIKE', '%' . $keyword . '%')
                ->orWhere('orders.contract_snapshot->contract_number', 'LIKE', '%' . $keyword . '%')
                ->orWhereHas('customer', function ($query) use ($keyword) {
                    $query->where('name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('phone', 'LIKE', '%' . $keyword . '%');
                })
                ->orWhereHas('orderItems', function ($items) use ($keyword) {
                    $items->whereHas('vehicle', function ($vehicle) use ($keyword) {
                        $vehicle->where('license', 'LIKE', '%' . $keyword . '%');
                    });
                });
        });

        return $query;
    }

    public function store(array $params)
    {
        return $this->getModel()->newQuery()->create($params);
    }
}
