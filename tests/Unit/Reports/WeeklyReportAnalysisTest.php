<?php

namespace Tests\Unit\Reports;

use App\Services\ReportService;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class WeeklyReportAnalysisTest extends TestCase
{
    public function test_weekly_analysis_includes_recurring_and_handles_cross_month_weeks(): void
    {
        $dailyRows = collect([
            (object) ['expense_date' => '2026-10-28', 'regular_expense' => '150.00', 'recurring_expense' => '0.00', 'total_expense' => '150.00', 'transaction_count' => 2],
            // Same Monday-Sunday week as Oct 28, but falls in November (recurring bill).
            (object) ['expense_date' => '2026-11-01', 'regular_expense' => '0.00', 'recurring_expense' => '250.00', 'total_expense' => '250.00', 'transaction_count' => 1],
            (object) ['expense_date' => '2026-11-05', 'regular_expense' => '100.00', 'recurring_expense' => '0.00', 'total_expense' => '100.00', 'transaction_count' => 1],
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

        $result = (new ReportService)->currentMonthWeeklyExpenseAnalysis(1, [
            'from_date' => '2026-10-26',
            'to_date' => '2026-11-08',
        ]);

        $this->assertSame('500.00', $result['summary']['total_expense']);
        $this->assertSame(4, $result['summary']['total_transactions']);
        $this->assertSame(2, $result['summary']['active_weeks']);
        $this->assertSame(2, $result['summary']['weeks_in_month']);

        // Week 1 spans Oct 26 - Nov 01 and MUST include both Oct 28 (150) and Nov 01 (250).
        $this->assertSame('2026-10-26', $result['weeks'][0]['week_start']);
        $this->assertSame('2026-11-01', $result['weeks'][0]['week_end']);
        $this->assertSame('400.00', $result['weeks'][0]['total_expense']);
        $this->assertSame('150.00', $result['weeks'][0]['expense_total']);
        $this->assertSame('250.00', $result['weeks'][0]['recurring_total']);
        $this->assertTrue($result['weeks'][0]['recurring_included']);
        $this->assertSame(3, $result['weeks'][0]['transaction_count']);

        // Week 2 spans Nov 02 - Nov 08.
        $this->assertSame('100.00', $result['weeks'][1]['total_expense']);
        $this->assertSame(1, $result['weeks'][1]['transaction_count']);
    }
}
