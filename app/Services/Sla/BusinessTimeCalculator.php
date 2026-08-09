<?php
namespace App\Services\Sla; use App\Models\BusinessHourPolicy; use App\Models\Holiday; use Carbon\CarbonImmutable; use Carbon\CarbonInterface;
class BusinessTimeCalculator
{
 public function addMinutes(CarbonInterface $start,int $minutes,?BusinessHourPolicy $policy=null):CarbonImmutable { $cursor=CarbonImmutable::instance($start);if(!$policy)return $cursor->addMinutes($minutes);$policy->loadMissing('intervals');$tz=$policy->timezone;$cursor=$cursor->setTimezone($tz);$remaining=$minutes;$guard=0;while($remaining>0&&$guard++<370){if($this->holiday($cursor)||!($interval=$this->nextInterval($cursor,$policy))){$cursor=$cursor->addDay()->startOfDay();continue;}[$from,$to]=$interval;if($cursor->lt($from))$cursor=$from;if($cursor->gte($to)){$cursor=$cursor->addDay()->startOfDay();continue;}$available=$cursor->diffInMinutes($to);$consume=min($remaining,$available);$cursor=$cursor->addMinutes($consume);$remaining-=$consume;if($remaining>0)$cursor=$cursor->addDay()->startOfDay();}return $cursor->utc();}
 private function nextInterval(CarbonImmutable $cursor,BusinessHourPolicy $policy):?array{$day=$cursor->dayOfWeek;foreach($policy->intervals->where('day_of_week',$day) as $i){$from=$cursor->startOfDay()->setTimeFromTimeString($i->starts_at);$to=$cursor->startOfDay()->setTimeFromTimeString($i->ends_at);if($cursor->lt($to))return[$from,$to];}return null;}
 private function holiday(CarbonImmutable $date):bool{return Holiday::where('is_active',true)->whereDate('date',$date->toDateString())->where('is_full_day',true)->exists();}
}
