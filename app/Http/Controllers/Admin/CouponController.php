<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Plan;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Coupons/Index', ['coupons' => Coupon::with('plans:id,name')->latest()->paginate(30), 'plans' => Plan::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $coupon = Coupon::create($this->attributes($data));
        $coupon->plans()->sync($data['plan_ids'] ?? []);
        $audit->record('coupon.created', $coupon, ['new_values' => collect($data)->except('plan_ids')->all()]);
        return back()->with('status', 'Coupon created.');
    }

    public function update(Request $request, Coupon $coupon, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        $coupon->update($this->attributes($data));
        $coupon->plans()->sync($data['plan_ids'] ?? []);
        $audit->record('coupon.updated', $coupon, ['new_values' => collect($data)->except('plan_ids')->all()]);
        return back()->with('status', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon, AuditLogService $audit): RedirectResponse
    {
        $coupon->update(['is_active' => false]);
        $audit->record('coupon.archived', $coupon);
        return back()->with('status', 'Coupon archived.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'code' => ['required', 'alpha_dash:ascii', 'max:50', Rule::unique('coupons')->ignore($coupon?->id)],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0', $request->input('type') === 'percent' ? 'max:100' : 'max:999999'],
            'currency' => ['required_if:type,fixed', 'nullable', 'string', 'size:3'],
            'duration' => ['required', 'in:once,forever,repeating'], 'duration_months' => ['nullable', 'integer', 'min:1', 'max:120', 'required_if:duration,repeating'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'], 'starts_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'per_account_limit' => ['nullable', 'integer', 'min:1'], 'billing_interval' => ['nullable', 'in:monthly,yearly'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'], 'plan_ids' => ['array'], 'plan_ids.*' => ['integer', 'exists:plans,id'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [...collect($data)->except(['plan_ids', 'currency', 'duration', 'duration_months'])->all(),
            'code' => strtoupper($data['code']),
            'metadata' => ['currency' => strtoupper($data['currency'] ?? config('platform.default_currency', 'USD')), 'duration' => $data['duration'], 'duration_months' => $data['duration_months'] ?? null],
        ];
    }
}
