<?php

namespace App\Http\Controllers\Tenant\Widget;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WidgetCorsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return response('', 204)->withHeaders([
            'Access-Control-Allow-Origin' => $request->header('Origin', '*'),
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Vary' => 'Origin',
        ]);
    }
}
