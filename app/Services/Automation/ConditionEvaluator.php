<?php
namespace App\Services\Automation; use Illuminate\Support\Arr;
class ConditionEvaluator
{
 public function matches(array $conditions,array $context):bool {if($conditions===[])return true;$operator=strtolower($conditions['operator']??'and');$rules=$conditions['rules']??$conditions;if(!is_array($rules))return false;$results=collect($rules)->map(fn($rule)=>isset($rule['rules'])?$this->matches($rule,$context):$this->rule($rule,$context));return $operator==='or'?$results->contains(true):!$results->contains(false);}
 private function rule(array $rule,array $context):bool{$actual=Arr::get($context,$rule['field']??'');$expected=$rule['value']??null;return match($rule['operator']??'equals'){'equals'=>$actual==$expected,'not_equals'=>$actual!=$expected,'in'=>in_array($actual,(array)$expected,true),'not_in'=>!in_array($actual,(array)$expected,true),'contains'=>is_array($actual)?in_array($expected,$actual,true):str_contains(strtolower((string)$actual),strtolower((string)$expected)),'greater_than'=>is_numeric($actual)&&$actual>$expected,'less_than'=>is_numeric($actual)&&$actual<$expected,'is_null'=>$actual===null,'is_not_null'=>$actual!==null,default=>false};}
}
