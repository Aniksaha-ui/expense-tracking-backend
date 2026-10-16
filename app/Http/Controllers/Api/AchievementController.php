<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class AchievementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:workplace,personal,other'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $query = Achievement::query()->where('user_id', auth()->id());
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['year'])) $query->whereYear('achieved_on', $filters['year']);

        return $this->successResponse($query->orderByDesc('achieved_on')->orderByDesc('id')->paginate($filters['per_page'] ?? 10)->withQueryString());
    }

    public function stats(): JsonResponse
    {
        $counts = Achievement::query()
            ->where('user_id', auth()->id())
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $workplace = (int) ($counts['workplace'] ?? 0);
        $personal = (int) ($counts['personal'] ?? 0);
        $other = (int) ($counts['other'] ?? 0);

        return $this->successResponse([
            'workplace' => $workplace,
            'personal' => $personal,
            'other' => $other,
            'total' => $workplace + $personal + $other,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $achievement = Achievement::create([...$this->validated($request), 'user_id' => auth()->id()]);
        return $this->successResponse($achievement, 'Achievement added successfully.', 201);
    }

    public function storeBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'achievements' => ['required', 'array', 'min:1', 'max:100'],
            'achievements.*.type' => ['required', 'in:workplace,personal,other'],
            'achievements.*.title' => ['required', 'string', 'max:160'],
            'achievements.*.description' => ['nullable', 'string', 'max:4000'],
            'achievements.*.achieved_on' => ['required', 'date'],
            'achievements.*.organization' => ['nullable', 'string', 'max:160'],
            'achievements.*.reference_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $achievements = DB::transaction(fn () => collect($data['achievements'])
            ->map(fn (array $achievement) => Achievement::create([...$achievement, 'user_id' => auth()->id()]))
            ->values());

        return $this->successResponse($achievements, $achievements->count().' achievements added successfully.', 201);
    }

    public function export()
    {
        $achievements = Achievement::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('achieved_on')
            ->orderByDesc('id')
            ->cursor();

        return (new FastExcel($achievements))->download(
            'achievements-'.now()->format('Y-m-d').'.csv',
            fn (Achievement $achievement) => [
                'Type' => ucfirst($achievement->type),
                'Title' => $achievement->title,
                'Date achieved' => $achievement->achieved_on?->format('Y-m-d'),
                'Organisation or place' => $achievement->organization,
                'Description' => $achievement->description,
                'Reference' => $achievement->reference_url,
            ]
        );
    }

    public function update(Request $request, int $achievementId): JsonResponse
    {
        $achievement = Achievement::query()->where('user_id', auth()->id())->findOrFail($achievementId);
        $achievement->update($this->validated($request, false));
        return $this->successResponse($achievement, 'Achievement updated successfully.');
    }

    public function destroy(int $achievementId): JsonResponse
    {
        Achievement::query()->where('user_id', auth()->id())->findOrFail($achievementId)->delete();
        return $this->successResponse([], 'Achievement deleted successfully.');
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'type' => [$creating ? 'required' : 'sometimes', 'in:workplace,personal,other'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'achieved_on' => [$creating ? 'required' : 'sometimes', 'date'],
            'organization' => ['nullable', 'string', 'max:160'],
            'reference_url' => ['nullable', 'string', 'max:2048'],
        ]);
    }
}
