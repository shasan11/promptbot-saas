<?php
namespace App\Services\Productivity; use Illuminate\Support\Arr;
class TemplateRenderer {public function render(string $template,array $context):string{return preg_replace_callback('/{{\s*([a-zA-Z0-9_.]+)\s*}}/',fn($m)=>(string)Arr::get($context,$m[1],''),$template)??$template;}public function variables(string $template):array{preg_match_all('/{{\s*([a-zA-Z0-9_.]+)\s*}}/',$template,$m);return array_values(array_unique($m[1]??[]));}}
