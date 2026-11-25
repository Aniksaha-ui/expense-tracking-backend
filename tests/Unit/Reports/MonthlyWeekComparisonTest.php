<?php

namespace Tests\Unit\Reports;

use App\Services\FinancialIntelligenceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class MonthlyWeekComparisonTest extends TestCase
{
    public function test_monthly_week_comparison_defaults_to_last_12_months_and_compares_weeks(): void
    {
        $dailyRows = collect([
            (object) [
                'expense_date' => '2026-10-02',
                'regular_expense' => '500.00',
                'recurring_expense' => '0.00',
                'total_expense' => '500.00',
                'transaction_count' => 2,
            ],
            (object) [
                'expense_date' => '2026-10-06',
                'regular_expense' => '1000.00',
                'recurring_expense' => '2000.00',
                'total_expense' => '3000.00',
                'transaction_count' => 3,
            ],
            (object) [
                'expense_date' => '2026-11-04',
                'regular_expense' => '800.00',
                'recurring_expense' => '0.00',
                'total_expense' => '800.00',
                'transaction_count' => 1,
            ],
        ]);

        $query = Mockery::mock();
        DB::shouldReceive('raw')->andReturnUsing(fn (string $sql): Expression => new Expression($sql));
        DB::shouldReceive('table')->once()->with('transactions')->andReturn($query);
        $query->shouldReceive('select')->once()->andReturnSelf();
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturnSelf();
        $query->shouldReceive('whereIn')->once()->with('type', ['EXPENSE', 'RECURRING'])->andReturnSelf();
        $query->shouldReceive('whereDate')->twice()->andReturnSelf();
        $query->shouldReceive('groupBy')->once()->andReturnSelf();
        $query->shouldReceive('orderBy')->once()->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn($dailyRows);

        $result = (new FinancialIntelligenceService)->monthlyWeekComparison(
            1,
            12,
            CarbonImmutable::parse('2026-11-25')
        );

        $this->assertSame(12, $result['period']['months_count']);
        $this->assertSame('2025-12-01', $result['period']['from_date']);
        $this->assertSame('2026-11-30', $result['period']['to_date']);
        $this->assertCount(12, $result['months']);
        $this->assertSame('4300.00', $result['summary']['total_expense']);
        $this->assertSame(2, $result['summary']['active_months']);
        $this->assertSame('Oct 2026', $result['summary']['highest_month_label']);
        $this->assertSame('Week 2', $result['summary']['peak_week_label']);

        $october = collect($result['months'])->firstWhere('month_key', '2026-10');
        $this->assertSame('500.00', $october['weeks'][0]['total_expense']);
        $this->assertSame('3000.00', $october['weeks'][1]['total_expense']);
        $this->assertSame('1000.00', $october['weeks'][1]['expense_total']);
        $this->assertSame('2000.00', $october['weeks'][1]['recurring_total']);
        $this->assertSame(2, $october['peak_week_sequence']);
    }
}
