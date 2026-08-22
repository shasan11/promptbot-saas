<?php

namespace App\Http\Controllers\Tenant\Admin\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Analytics\BotPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bot's own report card, separate from the operational reporting page.
 *
 * Kept apart deliberately: the existing report answers "how is the support
 * team doing", and these numbers answer "is the bot earning its place".
 * Mixing them would bury the one metric a buyer evaluates the product on
 * — deflection — among a dozen ticket counters.
 */
class BotPerformanceController extends Controller
{
    public function index(Request $request, BotPerformanceService $performance): Response
    {
        abort_unless($request->user('tenant')->can('reports.view'), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        return Inertia::render('Tenant/Admin/Reporting/BotPerformance', [
            'metrics' => $performance->summary($from, $to),
        ]);
    }
}
