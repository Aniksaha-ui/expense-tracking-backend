<?php

namespace Tests\Feature;

use App\Mail\CategoryExpenseReportMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class EmailCategoryExpenseReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_emails_category_wise_expense_pdf_for_requested_period(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'current_balance' => '350.00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Food',
            'slug' => 'food',
            'type' => 'EXPENSE',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transactions')->insert([
            [
                'user_id' => $user->id,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'related_account_id' => null,
                'type' => 'EXPENSE',
                'amount' => '100.00',
                'balance_before' => '500.00',
                'balance_after' => '400.00',
                'note' => 'Breakfast',
                'reference_type' => null,
                'reference_id' => null,
                'transaction_date' => '2026-06-19 09:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'related_account_id' => null,
                'type' => 'EXPENSE',
                'amount' => '50.00',
                'balance_before' => '400.00',
                'balance_after' => '350.00',
                'note' => 'Lunch',
                'reference_type' => null,
                'reference_id' => null,
                'transaction_date' => '2026-06-19 13:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('expense-reports:email', [
            '--from' => '2026-06-19',
            '--to' => '2026-06-19',
        ])->assertSuccessful();

        Mail::assertSent(CategoryExpenseReportMail::class, function (CategoryExpenseReportMail $mail): bool {
            return $mail->hasTo('sahaanik1045@gmail.com')
                && $mail->rows->count() === 1
                && $mail->rows->first()['category_name'] === 'Food'
                && $mail->rows->first()['transaction_count'] === 2
                && $mail->total === '150.00'
                && count($mail->attachments()) === 1;
        });
    }

    public function test_command_skips_users_without_expenses_by_default(): void
    {
        Mail::fake();
        User::factory()->create();

        $this->artisan('expense-reports:email', [
            '--from' => CarbonImmutable::parse('2026-06-19')->toDateString(),
            '--to' => CarbonImmutable::parse('2026-06-19')->toDateString(),
        ])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_command_uses_current_month_through_today_by_default(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow('2026-06-20 10:00:00');

        try {
            $user = User::factory()->create();
            $accountId = DB::table('accounts')->insertGetId([
                'user_id' => $user->id,
                'name' => 'Cash',
                'type' => 'CASH',
                'current_balance' => '400.00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $categoryId = DB::table('categories')->insertGetId([
                'user_id' => $user->id,
                'name' => 'Food',
                'slug' => 'food',
                'type' => 'EXPENSE',
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('transactions')->insert([
                [
                    'user_id' => $user->id,
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'related_account_id' => null,
                    'type' => 'EXPENSE',
                    'amount' => '100.00',
                    'balance_before' => '500.00',
                    'balance_after' => '400.00',
                    'note' => 'Current month',
                    'reference_type' => null,
                    'reference_id' => null,
                    'transaction_date' => '2026-06-05 09:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => $user->id,
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'related_account_id' => null,
                    'type' => 'EXPENSE',
                    'amount' => '50.00',
                    'balance_before' => '550.00',
                    'balance_after' => '500.00',
                    'note' => 'Previous month',
                    'reference_type' => null,
                    'reference_id' => null,
                    'transaction_date' => '2026-05-31 09:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $this->artisan('expense-reports:email')->assertSuccessful();

            Mail::assertSent(CategoryExpenseReportMail::class, function (CategoryExpenseReportMail $mail): bool {
                return $mail->fromDate->toDateString() === '2026-06-01'
                    && $mail->toDate->toDateString() === '2026-06-20'
                    && $mail->total === '100.00';
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_report_rounds_repeating_category_percentages(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'current_balance' => '97.00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ['name' => 'Food', 'slug' => 'food', 'amount' => '1.00'],
            ['name' => 'Travel', 'slug' => 'travel', 'amount' => '2.00'],
        ] as $category) {
            $categoryId = DB::table('categories')->insertGetId([
                'user_id' => $user->id,
                'name' => $category['name'],
                'slug' => $category['slug'],
                'type' => 'EXPENSE',
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('transactions')->insert([
                'user_id' => $user->id,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'related_account_id' => null,
                'type' => 'EXPENSE',
                'amount' => $category['amount'],
                'balance_before' => '100.00',
                'balance_after' => '97.00',
                'note' => $category['name'],
                'reference_type' => null,
                'reference_id' => null,
                'transaction_date' => '2026-06-19 09:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('expense-reports:email', [
            '--from' => '2026-06-19',
            '--to' => '2026-06-19',
        ])->assertSuccessful();

        Mail::assertSent(CategoryExpenseReportMail::class, function (CategoryExpenseReportMail $mail): bool {
            return $mail->rows->pluck('percentage')->sort()->values()->all() === ['33.3', '66.7'];
        });
    }
}
