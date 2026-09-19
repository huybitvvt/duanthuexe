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
  

        // The index only needs data rendered by the table. Transaction,
        // activity and add-on histories are loaded by the detail endpoint.
        $query = $this->getModel()->newQuery()->select([
            'orders.id',
            'orders.store_id',
            'orders.customer_id',
            'orders.contract_number',
            'orders.contract_snapshot',
            'orders.created_at',
            'orders.note',
            'orders.order_status',
            'orders.pid',
            'orders.total',
            'orders.out_dated_at',
            'orders.first_deposit_amount',
            'orders.additional_deposit_amount',
            'orders.created_without_collect_deposit',
            'orders.deposit_closed',
        ])
        ->withCount([
            'contractAmendments as vehicle_exchange_count' => function ($q) {
                $q->where('amendment_type', \App\Models\ContractAmendment::TYPE_VEHICLE_EXCHANGE);
            },
        ])->with([
			'vehicles' => function ($q) {
				$q->select(['vehicles.id', 'name', 'license']);
			},
			'store:id,store_name',
			'orderItems:id,order_id,rent_at,return_at',
            'customer:id,name,phone',
            'leads' => function ($q) {
                $q->select(['id', 'order_id', 'user_id'])->with('user:id,name');
            },
        ])
        ;

        $orders=$this->getOrderByParams($query,$params);
    
        $user = auth()->user();
        if ((int) $user->role_id !== 1) {
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
            $sourceIds = $params['source'];
            $query->whereHas('leads', function ($leadQuery) use ($sourceIds) {
                if (count($sourceIds) > 0) {
                    if (in_array('NULL', $sourceIds)) {
                        $leadQuery->where(function ($sourceQuery) use ($sourceIds) {
                            $sourceQuery->whereIn('user_id', array_diff($sourceIds, ['NULL']))
                                ->orWhereNull('user_id');
                        });
                    } else {
                        $leadQuery->whereIn('user_id', $sourceIds);
                    }
                } else {
                    $leadQuery->whereNull('user_id');
                }
            });
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
        if (isset($params['today_filter']) && !empty($params['today_filter'])) {
            $now = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
            $todayStart = $now->copy()->startOfDay();
            $todayEnd = $now->copy()->endOfDay();

            switch ($params['today_filter']) {
                case 'created_today':
                    $query->whereBetween('orders.created_at', [$todayStart, $todayEnd]);
                    break;
                case 'pickup_today':
                    $query->whereHas('orderItems', function ($items) use ($todayStart, $todayEnd) {
                        $items->whereBetween('rent_at', [$todayStart, $todayEnd]);
                    });
                    break;
                case 'return_today':
                    $query->whereHas('orderItems', function ($items) use ($todayStart, $todayEnd) {
                        $items->whereBetween('return_at', [$todayStart, $todayEnd]);
                    });
                    break;
                case 'transaction_today':
                    $query->whereHas('transactions', function ($trans) use ($todayStart, $todayEnd) {
                        $trans->whereBetween('created_at', [$todayStart, $todayEnd]);
                    });
                    break;
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
                        $vehicle->where(function ($match) use ($keyword) {
                            $match->where('license', 'LIKE', '%' . $keyword . '%')
                                ->orWhere('name', 'LIKE', '%' . $keyword . '%');
                        });
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
