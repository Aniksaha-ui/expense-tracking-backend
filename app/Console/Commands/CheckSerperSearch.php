<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckSerperSearch extends Command
{
    protected $signature = 'offers:search-check {query? : Search query to test}';
    protected $description = 'Safely test live Google-result retrieval for the Savings Offers PDF';

    public function handle(): int
    {
        $key = trim((string) config('services.serper.api_key'));
        if ($key === '') { $this->error('SERPER_API_KEY is missing.'); return self::FAILURE; }
        try {
            $response = Http::withHeaders(['X-API-KEY' => $key])->acceptJson()->timeout(20)->post('https://google.serper.dev/search', ['q' => $this->argument('query') ?: 'Foodpanda Bangladesh offer', 'gl' => 'bd', 'num' => 3]);
        } catch (\Throwable $exception) {
            $this->error('Search connection failed: '.$exception->getMessage());
            return self::FAILURE;
        }
        if (! $response->successful()) { $this->error('Search failed with HTTP '.$response->status().'.'); return self::FAILURE; }
        $this->info('Live search works. Results: '.count($response->json('organic', [])).'.');
        return self::SUCCESS;
    }
}
