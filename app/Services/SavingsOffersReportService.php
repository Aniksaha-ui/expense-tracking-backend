<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SavingsOffersReportService
{
    public function __construct(private readonly PdfService $pdfService, private readonly SavingsOffersAnalysisService $analysisService, private readonly ProviderConfigurationService $providerConfigurations) {}

    public function generate(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $spending = DB::table('transactions')->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', $user->id)->whereBetween('transactions.transaction_date', [$from, $to])
            ->whereIn('transactions.type', [TransactionType::EXPENSE->value, TransactionType::RECURRING->value])
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') category_name, SUM(transactions.amount) total_amount")
            ->groupByRaw("COALESCE(categories.name, 'Uncategorized')")->orderByDesc('total_amount')->get()
            ->map(fn ($row) => ['category' => $row->category_name, 'amount' => number_format((float) $row->total_amount, 2, '.', '')])->all();
        $total = array_sum(array_map(fn ($row) => (float) $row['amount'], $spending));
        $location = $this->location($user);
        // Offers are intentionally limited to purchases where a discount is useful.
        // Recurring bills, rent, transport, utilities, and other expense types do
        // not trigger a search-result link in this report.
        $offerSpending = $this->offerEligibleSpending($spending);
        [$offers, $searchStatus] = $this->searchOffers($offerSpending, $location);
        $months = max(1, $from->startOfMonth()->diffInMonths($to->startOfMonth()) + 1);
        $offers = $this->analysisService->generate($offerSpending, $offers, $months);
        $viewData = compact('user', 'from', 'to', 'spending', 'total', 'offers', 'location', 'months', 'searchStatus');
        return [...$viewData, 'pdf' => $this->pdfService->render('reports.savings-offers-pdf', $viewData), 'filename' => "savings-offers-{$from->toDateString()}-to-{$to->toDateString()}.pdf"];
    }

    private function searchOffers(array $spending, string $location): array
    {
        $provider = $this->providerConfigurations->offers();
        if (! $provider['enabled']) {
            return [[], 'Savings Offers searches are disabled by configuration.'];
        }

        $key = trim((string) $provider['serper_api_key']);
        if ($spending === []) {
            return [[], 'Offers are available only for food and shopping purchases in this report.'];
        }

        if ($key === '') return [[], 'Live offer search is unavailable because SERPER_API_KEY is not configured.'];

        // Search only food and product-shopping categories. Results remain
        // source-linked search results and are never presented as verified offers.
        $queries = collect($spending)->take(5)->flatMap(function (array $row) use ($location): array {
            $category = strtolower($row['category']);
            return [
                "{$category} food or shopping discount offers near {$location}",
                "site:facebook.com {$category} offer discount {$location}",
            ];
        })->unique()->values();
        try {
            $offers = $queries->flatMap(function (string $query) use ($key) {
                $response = Http::withHeaders(['X-API-KEY' => $key])->acceptJson()->timeout(20)->post('https://google.serper.dev/search', ['q' => $query, 'gl' => 'bd', 'num' => 3]);
                return $response->successful() ? collect($response->json('organic', []))->map(fn (array $item) => ['provider' => parse_url($item['link'] ?? '', PHP_URL_HOST) ?: 'Google result', 'focus' => $item['title'] ?? 'Offer', 'source' => $item['snippet'] ?? 'Open this result for offer terms.', 'url' => $item['link'] ?? ''])->filter(fn (array $item) => $item['url'] !== '') : [];
            })->unique('url')->take(12)->map(fn (array $offer) => $this->withSourceDetails($offer))->values()->all();
            return [$offers, $offers === [] ? 'No live food or shopping offers were found for this period.' : null];
        } catch (\Throwable $exception) { report($exception); return [[], 'Live offer search is unavailable. Check the internet connection, Serper API key, and quota.']; }
    }

    private function offerEligibleSpending(array $spending): array
    {
        $keywords = [
            'food', 'grocery', 'restaurant', 'dining', 'cafe', 'meal', 'snack', 'delivery',
            'shopping', 'product', 'retail', 'clothing', 'fashion', 'electronics', 'mobile',
            'appliance', 'furniture', 'cosmetic', 'pharmacy', 'medicine', 'book', 'household', 'market',
        ];

        return array_values(array_filter($spending, function (array $row) use ($keywords): bool {
            $category = strtolower((string) ($row['category'] ?? ''));

            return $category !== '' && collect($keywords)->contains(
                fn (string $keyword): bool => str_contains($category, $keyword)
            );
        }));
    }

    private function withSourceDetails(array $offer): array
    {
        $host = strtolower((string) parse_url($offer['url'], PHP_URL_HOST));
        if (! ($host === 'facebook.com' || Str::endsWith($host, '.facebook.com'))) {
            return $offer;
        }

        try {
            $response = Http::withHeaders([
                'Accept-Language' => 'en-US,en;q=0.9',
                'User-Agent' => 'Mozilla/5.0 (compatible; ExpenseTrackerOfferReport/1.0)',
            ])->timeout(15)->get($offer['url']);

            if (! $response->successful()) {
                return $offer;
            }

            $details = $this->pageDescription($response->body());
            if ($details !== null && mb_strlen($details) > mb_strlen((string) $offer['source'])) {
                $offer['source'] = $details;
                $offer['source_detail'] = 'public Facebook post description';
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $offer;
    }

    private function pageDescription(string $html): ?string
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return null;
            }

            foreach ($document->getElementsByTagName('meta') as $meta) {
                $name = strtolower($meta->getAttribute('property') ?: $meta->getAttribute('name'));
                if (in_array($name, ['og:description', 'description'], true)) {
                    $text = preg_replace('/\s+/u', ' ', html_entity_decode($meta->getAttribute('content'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    return $text === null || trim($text) === '' ? null : trim($text);
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return null;
    }

    private function location(User $user): string
    {
        $lat = $user->offers_latitude ?? trim((string) config('services.offers.latitude'));
        $lng = $user->offers_longitude ?? trim((string) config('services.offers.longitude'));
        $radius = max(0.1, (float) config('services.offers.radius_km', 2));

        return $lat !== null && $lat !== '' && $lng !== null && $lng !== ''
            ? "within {$radius} km of {$lat}, {$lng}"
            : (trim((string) config('services.offers.location')) ?: 'Bangladesh');
    }
}
