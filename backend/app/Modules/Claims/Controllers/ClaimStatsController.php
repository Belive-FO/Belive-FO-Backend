<?php

namespace App\Modules\Claims\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClaimCategoryResource;
use App\Models\Claim;
use App\Models\ClaimCategory;
use App\Modules\Claims\Services\DistanceService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimStatsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DistanceService $distanceService
    ) {}

    private function monthExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'pgsql'
            ? "to_char(claim_date, 'YYYY-MM')"
            : "DATE_FORMAT(claim_date, '%Y-%m')";
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Claim::where('user_id', $user->id);
        $totalAmount = (float) (clone $query)->sum('amount');
        $pendingCount = (clone $query)->where('status', Claim::STATUS_PENDING)->count();
        $approvedCount = (clone $query)->where('status', Claim::STATUS_APPROVED)->count();
        $totalClaims = (clone $query)->count();

        $start = Carbon::now()->startOfMonth()->subMonths(11);
        $end = Carbon::now()->endOfMonth();
        $monthAlias = $this->monthExpression().' as month';
        $monthlySpend = Claim::where('user_id', $user->id)
            ->whereBetween('claim_date', [$start, $end])
            ->whereIn('status', [Claim::STATUS_APPROVED, Claim::STATUS_PAID])
            ->select(DB::raw($monthAlias), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($v) => (float) $v)
            ->all();

        return $this->success([
            'totalAmount' => round($totalAmount, 2),
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'totalClaims' => $totalClaims,
            'monthlySpend' => $monthlySpend,
        ]);
    }

    public function monthlySpend(Request $request): JsonResponse
    {
        $months = min(max((int) $request->query('months', 6), 1), 24);
        $user = $request->user();
        $end = Carbon::now()->endOfMonth();
        $start = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $monthAlias = $this->monthExpression().' as month';
        $raw = Claim::where('user_id', $user->id)
            ->whereBetween('claim_date', [$start, $end])
            ->whereIn('status', [Claim::STATUS_APPROVED, Claim::STATUS_PAID])
            ->select(DB::raw($monthAlias), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($v) => (float) $v);

        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $dt = Carbon::now()->subMonths($i)->startOfMonth();
            $key = $dt->format('Y-m');
            $result[] = [
                'month' => $dt->format('M'),
                'amount' => $raw->get($key, 0.0),
            ];
        }

        return $this->success($result);
    }

    public function categories(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $spentByCategory = Claim::where('user_id', $userId)
            ->whereIn('status', [Claim::STATUS_APPROVED, Claim::STATUS_PAID])
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'category_id')
            ->map(fn ($v) => (float) $v);

        $categories = ClaimCategory::orderBy('name')->get()
            ->each(fn ($cat) => $cat->setAttribute('spent', $spentByCategory->get($cat->id, 0.0)));

        return $this->success(ClaimCategoryResource::collection($categories));
    }

    public function mileageRate(Request $request): JsonResponse
    {
        return $this->success([
            'rate' => config('claims.mileage_rate'),
        ]);
    }

    /**
     * Calculate driving distance between two addresses (Google Distance Matrix API).
     * POST body: { "from": "...", "to": "..." }
     * Returns { "distance_km": 12.4 } or 422 with { "message": "...", "distance_km": null }.
     */
    public function calculateDistance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'max:500'],
            'to' => ['required', 'string', 'max:500'],
        ]);

        $distanceKm = $this->distanceService->calculateDrivingDistanceKm(
            $validated['from'],
            $validated['to']
        );

        if ($distanceKm === null) {
            return response()->json([
                'message' => 'Unable to calculate distance',
                'distance_km' => null,
            ], 422);
        }

        return response()->json([
            'distance_km' => $distanceKm,
        ]);
    }
}
