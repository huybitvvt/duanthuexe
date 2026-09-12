<?php

namespace App\Helpers;

use App\Models\OrderVehicleDetail;
use App\Models\PriceVehicle;
use App\Models\Vehicle;
use Carbon\Carbon;
use App\Helpers\DateTimeHelper;
use Illuminate\Support\Facades\Log;

class CarRentalHelper
{   
    
    public static function writeMoneyOutDateAndTotal($order, $request = null){
        // Đơn nợ xấu thì k cần làm gì cả.
		$details = array();
        if ( $order->order_status == 'bad_debt' ){
            return $details;
        }
        $orderItems = $order->orderItems;
        $total = 0;
        
        foreach ($orderItems as $orderItem) {
            // $return_at = DateTimeHelper::parse($orderItem->return_at);
			$detail = array();
			$return_at = Carbon::createFromFormat('Y-m-d H:i:s', $orderItem->return_at);

			$detail['return_at'] = $orderItem->return_at;
			$detail['return_at_formatted'] = $return_at;

			if ( $request && $request->get('completed_at') ) {
				// $completed_at = DateTimeHelper::parse( $request->get('completed_at') );
				$completed_at = Carbon::createFromFormat('d-m-Y H:i:s', $request->get('completed_at'));
				$detail['completed_at_case'] = 1;
				$detail['completed_at'] = $request->get('completed_at');
			} else if ($orderItem->completed_at){
                // $completed_at = DateTimeHelper::parse($orderItem->completed_at);
                $completed_at = Carbon::createFromFormat('Y-m-d H:i:s', $orderItem->completed_at);
				$detail['completed_at_case'] = 2;
				$detail['completed_at'] = $orderItem->completed_at;
            } else {
                // $completed_at = DateTimeHelper::now();
                $completed_at = Carbon::now();
				$detail['completed_at_case'] = 3;
            }
			$detail['completed_at_formatted'] = $completed_at;

            // Nếu quá hạn rồi 
            if ( $return_at->lt($completed_at)  ) {
				$detail['is_outdate'] = true;
                if ($orderItem->vehicle) {
                    $differenceMinute = $completed_at->diffInMinutes($return_at);
					$detail['is_outdate_minutes'] = $differenceMinute;

                    // quá hạn > 30' rồi thì mới ghi số phút quá hạn vào db
                    if ( $differenceMinute > 30){
                        $orderItem->update([
                            'minute_out_date' => $differenceMinute,
                        ]);
                       
                    } else {
                        $orderItem->update([
                            'minute_out_date' => 0,
                        ]);
                    }
                } 
            } else {
                $orderItem->update([
                    'minute_out_date' => 0,
                ]);
            }
			$handle_price = $orderItem->handler_price;
			if ( is_numeric( $handle_price ) && $handle_price > 0 ) { // handler_price là giá tổng mà nhân viên nhập => tức là giá chốt cuối cùng nên không tính phí quá hạn.
				
			} else {
				// Kể cả quá hạn hay chưa vẫn tính money_out_date, vì nếu chưa quá hạn thì minute_out_date bằng 0, có nhân lên với đơn giá cũng vẫn là 0 nên ko sao. Còn nếu quá hạn rồi thì tính bình thường.  Rồi đưa money_out_date vào total của từng order item , dùng để cộng dồn lại vào total của bảng orders
				$order_item_total = CarRentalHelper::calculateOrderItemTotal($orderItem, true);
				$detail['order_item_total'] = $order_item_total;
				$total += $order_item_total;
			}

			if ( ! empty( $detail ) ) {
				$details[$orderItem->id] = $detail;
			}
        }

		$max_minute_outdate = $order->orderItems()->max('minute_out_date');
		$details['max_minute_outdate'] = $max_minute_outdate;
		$details['outdate_or_early_amount'] = $total;
      
        $order->update(['out_dated_at' => $max_minute_outdate]);
        $order->update(['outdate_or_early_amount' => $total]);

		return $details;
    }
    public static function whenOrderReturnEarly($order, $request = null){
        $items = $order->orderItems ;
		$order_total = 0;
		$update_order_total = false;
		$details = array();
        
        foreach ($items as $item){
			$detail = array();

            $rent_at = Carbon::createFromFormat('Y-m-d H:i:s', $item['rent_at']);
            $return_at = Carbon::createFromFormat('Y-m-d H:i:s', $item['return_at']);
			$detail['rent_at'] = $item['rent_at'];
			$detail['rent_at_formatted'] = $rent_at;
			$detail['return_at'] = $item['return_at'];
			$detail['return_at_formatted'] = $return_at;

            // $completed_at = Carbon::createFromFormat('Y-m-d H:i:s', $item['completed_at']);
			if ( $request && $request->get('completed_at') ) {
				// $completed_at = DateTimeHelper::parse( $request->get('completed_at') );
				$completed_at = Carbon::createFromFormat('d-m-Y H:i:s', $request->get('completed_at'));
				$detail['completed_at_case'] = 1;
				$detail['completed_at'] = $request->get('completed_at');
			} else if ($item['completed_at']){
                $completed_at = Carbon::createFromFormat('Y-m-d H:i:s', $item['completed_at']);
				$detail['completed_at_case'] = 2;
				$detail['completed_at'] = $item['completed_at'];
            } else {
                $completed_at = Carbon::now();
				$detail['completed_at_case'] = 3;
            }
			$detail['completed_at_formatted'] = $completed_at;

            if ($return_at->gt($completed_at)) {
				$detail['is_early'] = true;
				$renting_duration = $completed_at->diffInMinutes($rent_at);
				$renting_money = CarRentalHelper::calMoneyOutDate( $item, round($renting_duration / 60) );
				$money_to_return = $item->total_money - $renting_money;
				$differenceMinute = $completed_at->diffInMinutes($return_at);
				// info(' renting_duration ' .($renting_duration/60).' renting_money '. $renting_money . ' money_to_return '.$money_to_return .' differenceMinute ' . $differenceMinute);
				$order_vehicle_detail = OrderVehicleDetail::find($item->id);

				$handle_price = $item->handler_price;
				if ( is_numeric( $handle_price ) && $handle_price > 0 ) { // handler_price là giá tổng mà nhân viên nhập => tức là giá chốt cuối cùng nên không tính tiền hoàn trả sớm.
					$money_to_return = 0;
				}

				$detail['money_to_return'] = $money_to_return;
				$detail['diff_minutes'] = $differenceMinute;

				$updated = $order_vehicle_detail->update([
					'money_out_date' => - $money_to_return,
					'minute_out_date' => - $differenceMinute,
				]);
				$order_total = $order_total - $money_to_return;
				$update_order_total = true;
            }
			$details[$item->id] = $detail;
        }
		if ( $update_order_total ) {
			if ( $order_total <= 0 ) {
				$order_total = 0;
			}
			$order->update(['outdate_or_early_amount' => $order_total]);
		}
		return $details;
    }

    public static function converMinutesInDay($minutes): string
    {
        if ($minutes > 0 && $minutes < 60) {
            return "Đến giờ trả xe";
        }
        $d = floor($minutes / 1440);
        $h = floor(($minutes - $d * 1440) / 60);
        $m = $minutes - ($d * 1440) - ($h * 60);
        $string = '';
        if ($d) {
            $string .= $d . ' Ngày ';
        }
        if ($h) {
            $string .= $h . ' giờ ';
        }
        if ($m) {
            $string .= $m . ' phút';
        }
        return $string;
    }
    public static function getUnitPrice($order_item){
       
        if ($order_item->substitute_unit_price){
            return $order_item->substitute_unit_price;
        }
        
        $vehicle = $order_item->vehicle;
        
        $priceVehicle = PriceVehicle::query()->where('type', $vehicle->type)->where('price_type', $order_item->type)->first();
        return $priceVehicle->price ;
    }
    public static function calMoneyOutDate($order_item, $hours, $price_type = 'day')
    {
        $day = floor($hours / 24);
        $remainHours = $hours % 24;
        $vehicle = $order_item->vehicle ;
        $moneyLateHours = 0;
        if ($remainHours > 0 && $remainHours < 8)
            switch ($vehicle->type) {
                case 'xeso' :
                case 'xega' :
                    $moneyLateHours = $remainHours * 15000;
                    break;
                case 'xecon' :
                    $moneyLateHours = $remainHours * 25000;
                    break;
                case 'sh':
                    $moneyLateHours = $remainHours * 35000;
                    break;
            } else if ($remainHours >= 8) {
            $day += 1;
        }
        $moneyLateDate = CarRentalHelper::getUnitPrice($order_item) * $day;
        return $moneyLateDate + $moneyLateHours;
    }
    public static function calculateOrderItemTotal($orderItem, $return_money_late_only = false){
        if ($orderItem->vehicle) {
            $moneyLate = CarRentalHelper::calMoneyOutDate($orderItem , round($orderItem->minute_out_date / 60));
            $orderItem->update([
                'money_out_date' => $moneyLate,    
            ]);
            if ($orderItem->handler_price) {
                return $orderItem->handler_price;
            } else {
				if ($return_money_late_only) {
					return $moneyLate;
				}
                return ( $orderItem->total_money + $moneyLate );
            }
        }
    }

	public static function calcOrderReturnEarlyAmount($order, $calc_time){
        $items = $order->orderItems;
		$details = array();
		$order_total = $order->total;
		$total_amount_return = 0;
		$total_diff_min = 0;
        foreach ($items as $item){
            $rent_at = Carbon::createFromFormat('Y-m-d H:i:s', $item['rent_at']);
            $return_at = Carbon::createFromFormat('Y-m-d H:i:s', $item['return_at']);
            // $completed_at = Carbon::createFromFormat('d-m-Y H:i:s', $calc_time); // This function convert time to GMT 0
            $completed_at = DateTimeHelper::parse($calc_time); // This function convert time to GMT + 7

			$item_data = [
				'rent_at' => $rent_at,
				'return_at' => $return_at,
				'completed_at' => $completed_at,
			];

            if ($return_at->gt($completed_at)) {
				$renting_duration = $completed_at->diffInMinutes($rent_at);
				$renting_money = CarRentalHelper::calMoneyOutDate( $item, round($renting_duration / 60) );
				$money_to_return = $item->total_money - $renting_money;
				$differenceMinute = $completed_at->diffInMinutes($return_at);
				// info(' renting_duration ' .($renting_duration/60).' renting_money '. $renting_money . ' money_to_return '.$money_to_return .' differenceMinute ' . $differenceMinute);

				$item_data['money_out_date'] = -$money_to_return;
				$item_data['minute_out_date'] = -$differenceMinute;

				$total_amount_return = $total_amount_return - $money_to_return;
				$total_diff_min = $total_diff_min - $differenceMinute;

				$order_total = $order_total - $money_to_return;
				$update_order_total = true;
            }
			$details[$item->id] = $item_data;
        }
		return [
			'details' => $details,
			'total_amount' => $total_amount_return,
			'total_diff_min' => $total_diff_min,
			'order_total' => $order_total,
		];
    }

	public static function calcOrderOutDateAndTotal( $order, $calc_time ){
        if ( $order->order_status == 'bad_debt' ){
            false;
        }
        $orderItems = $order->orderItems;
        $total = 0;
		$max_minute_out_date = 0;
		$completed_at = DateTimeHelper::parse($calc_time);

		$details = array();
        
        foreach ($orderItems as $orderItem) {
            $return_at = DateTimeHelper::parse($orderItem->return_at);
			// $completed_at = Carbon::createFromFormat('d-m-Y H:i:s', $calc_time);
			$item_data = [];
			
			$the_order_item = $orderItem;
            // Nếu quá hạn rồi 
            if ( $return_at->lt($completed_at)  ) {
                if ($orderItem->vehicle) {
                    $differenceMinute = $completed_at->diffInMinutes($return_at);
                    // quá hạn > 30' rồi thì mới ghi số phút quá hạn vào db
                    if ( $differenceMinute > 30){
						$the_order_item->minute_out_date = $differenceMinute;
                    } else {
						$the_order_item->minute_out_date = 0;
                    }   
                } 
            } else {
				$the_order_item->minute_out_date = 0;
            }
			if ($max_minute_out_date < $the_order_item->minute_out_date) {
				$max_minute_out_date = $the_order_item->minute_out_date;
			}
            // Kể cả quá hạn hay chưa vẫn tính money_out_date, vì nếu chưa quá hạn thì minute_out_date bằng 0, có nhân lên với đơn giá cũng vẫn là 0 nên ko sao. Còn nếu quá hạn rồi thì tính bình thường.  Rồi đưa money_out_date vào total của từng order item , dùng để cộng dồn lại vào total của bảng orders
            $order_item_total = CarRentalHelper::calculateOrderItemTotal($the_order_item);

			$item_data['minute_out_date'] = $the_order_item->minute_out_date;
			$item_data['order_item_total'] = $order_item_total;

            $total += $order_item_total;
			$details[$orderItem->id] = $item_data;
        }

		return [
			'details' => $details,
			'max_out_dated_at' => $max_minute_out_date,
			'order_total' => $total,
			'completed_at' => $completed_at,
		];
    }
}
