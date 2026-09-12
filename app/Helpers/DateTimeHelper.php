<?php

namespace App\Helpers;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
 
class DateTimeHelper {
    public static function now_old(): Carbon 
    {
        $currentDateTime = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
        return Carbon::parse($currentDateTime,'UTC');
    }
    public static function parse_old($str): Carbon
    {
        return Carbon::parse($str,'UTC') ;
    }
  
	public static function now(): Carbon 
    {
        return Carbon::now('Asia/Ho_Chi_Minh');
    }

    public static function parse($str): Carbon
    {
        return Carbon::parse($str, 'Asia/Ho_Chi_Minh');
    }
}