<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SavingsOffersReportService
{
    public function __construct(private readonly PdfService $pdfService) {}

    public function generate(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $spending = DB::table('transactions')->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', $user->id)->whereBetween('transactions.transaction_date', [$from, $to])
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') category_name, SUM(transactions.amount) total_amount")
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized')")->orderByDesc('total_amount')->get()
            ->map(fn ($row) => ['category' => $row->category_name, 'amount' => number_format((float) $row->total_amount, 2, '.', '')])->all();
        $total = array_sum(array_map(fn ($row) => (float) $row['amount'], $spending));
        $location = $this->location();
        $offers = $this->searchOffers($spending, $location);
        if ($offers === []) {
            $offers = [
            ['provider' => 'Foodpanda', 'focus' => 'Food delivery and grocery', 'source' => 'Official deals and vouchers', 'url' => 'https://www.foodpanda.com.bd/contents/deals'],
            ['provider' => 'Pathao', 'focus' => 'Rides, food, courier and digital-payment offers', 'source' => 'Official promotion page', 'url' => 'https://pathao.com/blog/category/promotions/?lang=en'],
            ['provider' => 'Uber', 'focus' => 'Ride promotions and promo codes', 'source' => 'Uber Bangladesh promotions', 'url' => 'https://www.uber.com/bd/en/promo/'],
            ['provider' => 'Facebook', 'focus' => 'Flash promotions from service pages', 'source' => 'Check official Foodpanda, Pathao, and Uber Bangladesh pages', 'url' => 'https://www.facebook.com/foodpandabd/'],
            ];
        }
        $advice = collect($spending)->take(5)->map(function (array $row): string {
            $name = strtolower($row['category']); $service = str_contains($name, 'food') || str_contains($name, 'restaurant') ? 'Foodpanda' : (str_contains($name, 'transport') || str_contains($name, 'travel') ? 'Pathao or Uber' : 'the listed offer pages');
            return "You spent BDT {$row['amount']} on {$row['category']}. Check {$service} offers before the next purchase; using a valid voucher, free-delivery deal, or ride promo can reduce this category's cost.";
        })->all();
        $viewData = compact('user', 'from', 'to', 'spending', 'total', 'offers', 'advice', 'location');
        return [...$viewData, 'pdf' => $this->pdfService->render('reports.savings-offers-pdf', $viewData), 'filename' => "savings-offers-{$from->toDateString()}-to-{$to->toDateString()}.pdf"];
    }

    private function searchOffers(array $spending, string $location): array
    {
        $key = trim((string) config('services.serper.api_key'));
        if ($key === '') return [];
        $queries = collect($spending)->take(5)->map(fn (array $row) => strtolower($row['category'])." discount offer near {$location}")->unique();
        try {
            return $queries->flatMap(function (string $query) use ($key) {
                $response = Http::withHeaders(['X-API-KEY' => $key])->acceptJson()->timeout(20)->post('https://google.serper.dev/search', ['q' => $query, 'gl' => 'bd', 'num' => 3]);
                return $response->successful() ? collect($response->json('organic', []))->map(fn (array $item) => ['provider' => parse_url($item['link'] ?? '', PHP_URL_HOST) ?: 'Google result', 'focus' => $item['title'] ?? 'Offer', 'source' => $item['snippet'] ?? 'Open this result for offer terms.', 'url' => $item['link'] ?? ''])->filter(fn (array $item) => $item['url'] !== '') : [];
            })->unique('url')->take(12)->values()->all();
        } catch (\Throwable) { return []; }
    }

    private function location(): string
    {
        $lat = trim((string) config('services.offers.latitude')); $lng = trim((string) config('services.offers.longitude'));
        return $lat !== '' && $lng !== '' ? "{$lat}, {$lng}" : (trim((string) config('services.offers.location')) ?: 'Bangladesh');
    }
}
