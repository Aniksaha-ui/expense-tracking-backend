import { CalendarDays, ReceiptText, RefreshCcw, TrendingUp, Wallet } from 'lucide-react'
import { useMemo } from 'react'
import AdminDataTable, { AdminTableButton } from '../../../components/ui/AdminDataTable'
import { ReportsOverview } from '../component/ReportsOverview.jsx'
import { REPORTS_PAGE_COPY } from '../constants/reports.constants'
import { useWeeklyCurrentMonthAnalysisReport } from '../hooks/useReports'

const weeklyCurrentMonthAnalysisColumns = [
  {
    accessor: 'serial',
    id: 'serial',
    label: 'SL',
    width: '76px',
  },
  {
    id: 'week',
    label: 'Week',
    render: (item) => (
      <div className="space-y-1">
        <p className="font-semibold text-white">{item.weekLabel}</p>
        <p className="text-xs text-[#7d8ca5]">{item.calendarWeekLabel}</p>
      </div>
    ),
    width: '18%',
  },
  {
    id: 'range',
    label: 'Period',
    render: (item) => (
      <div className="space-y-1">
        <p className="font-medium text-[#dbe7fb]">{item.rangeLabel}</p>
        <p className="text-xs text-[#7d8ca5]">
          {item.weekStart} to {item.weekEnd}
        </p>
      </div>
    ),
    width: '24%',
  },
  {
    align: 'right',
    id: 'transaction_count',
    label: 'Transactions',
    render: (item) => <span className="text-sm text-[#dbe7fb]">{item.transactionCountLabel}</span>,
    width: '14%',
  },
  {
    align: 'right',
    id: 'total_amount',
    label: 'Total Amount',
    render: (item) => <span className="text-sm font-semibold text-white">{item.totalAmountLabel}</span>,
    width: '16%',
  },
  {
    align: 'right',
    id: 'average_expense',
    label: 'Average Expense',
    render: (item) => <span className="text-sm text-[#dbe7fb]">{item.averageExpenseLabel}</span>,
    width: '16%',
  },
  {
    align: 'right',
    id: 'share',
    label: 'Share',
    render: (item) => <span className="text-sm text-[#dbe7fb]">{item.shareLabel}</span>,
    width: '12%',
  },
]

const buildMetricItems = (report) => [
  { icon: CalendarDays, label: 'Weeks In Month', tone: 'blue', value: report.weeksInMonthLabel },
  { icon: TrendingUp, label: 'Active Weeks', tone: 'cyan', value: report.activeWeeksLabel },
  { icon: ReceiptText, label: 'Transactions', tone: 'emerald', value: report.totalTransactionsLabel },
  { icon: Wallet, label: 'Total Spend', tone: 'amber', value: report.totalExpenseLabel },
]

export default function WeeklyCurrentMonthAnalysisReportPage() {
  const apiState = useWeeklyCurrentMonthAnalysisReport()

  const resultLabel = useMemo(() => {
    if (!apiState.pagination.total && !apiState.items.length) {
      return `No weekly rows found for ${apiState.report.monthLabel}.`
    }

    return `Showing ${apiState.pagination.from}-${apiState.pagination.to} of ${apiState.pagination.total} weekly rows for ${apiState.report.monthLabel}`
  }, [apiState.items.length, apiState.pagination.from, apiState.pagination.to, apiState.pagination.total, apiState.report.monthLabel])

  return (
    <main className="routes-page">
      <div className="routes-page__inner">
        <header className="routes-page__header">
          <div className="routes-page__title">
            <TrendingUp size={20} color="#4f83ff" />
            <h1>{REPORTS_PAGE_COPY.weeklyCurrentMonthAnalysis.title}</h1>
          </div>
          <p className="routes-page__subtitle">{REPORTS_PAGE_COPY.weeklyCurrentMonthAnalysis.subtitle}</p>
        </header>

        <ReportsOverview isLoading={apiState.isLoading} items={buildMetricItems(apiState.report)} />

        <section className="mb-5 rounded-xl border border-[#332d30] bg-[#171314] p-4">
          <div className="grid gap-4 md:grid-cols-2">
            <article className="rounded-xl border border-[#2a2426] bg-[#120f10] p-4">
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#7ea1ff]">Reporting Month</p>
              <p className="mt-2 text-lg font-semibold text-white">{apiState.report.monthLabel}</p>
            </article>
            <article className="rounded-xl border border-[#2a2426] bg-[#120f10] p-4">
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#7ea1ff]">Covered Range</p>
              <p className="mt-2 text-lg font-semibold text-white">{apiState.report.dateRangeLabel}</p>
            </article>
          </div>
        </section>

        {apiState.error ? <p className="month-balance-alert">{apiState.error}</p> : null}

        <AdminDataTable
          actions={
            <AdminTableButton
              className={apiState.isLoading ? 'opacity-60' : ''}
              disabled={apiState.isLoading}
              onClick={() => void apiState.refresh()}
            >
              <RefreshCcw size={14} />
              Refresh
            </AdminTableButton>
          }
          columns={weeklyCurrentMonthAnalysisColumns}
          data={apiState.items}
          emptyMessage="No weekly current month analysis data found."
          filters={null}
          isLoading={apiState.isLoading}
          onPageChange={apiState.setPage}
          onSearchChange={(value) => {
            apiState.setPage(1)
            apiState.setSearch(value)
          }}
          pagination={apiState.pagination}
          resultLabel={resultLabel}
          search={apiState.search}
          searchPlaceholder={REPORTS_PAGE_COPY.weeklyCurrentMonthAnalysis.searchPlaceholder}
        />
      </div>
    </main>
  )
}
