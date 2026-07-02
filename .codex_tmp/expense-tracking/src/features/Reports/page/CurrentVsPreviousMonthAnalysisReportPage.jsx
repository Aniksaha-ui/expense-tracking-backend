import { GitCompareArrows, RefreshCcw, TrendingDown, TrendingUp, Wallet } from 'lucide-react'
import { useMemo } from 'react'
import AdminDataTable, { AdminTableButton } from '../../../components/ui/AdminDataTable'
import { ReportsOverview } from '../component/ReportsOverview.jsx'
import { REPORTS_PAGE_COPY } from '../constants/reports.constants'
import { useCurrentVsPreviousMonthAnalysisReport } from '../hooks/useReports'

const comparisonColumns = [
  {
    accessor: 'serial',
    id: 'serial',
    label: 'SL',
    width: '6%',
  },
  {
    id: 'period',
    label: 'Period',
    render: (item) => (
      <div className="space-y-1">
        <p className="font-semibold text-white">{item.periodLabel}</p>
        <p className="text-xs text-[#7d8ca5]">{item.rangeLabel}</p>
      </div>
    ),
    width: '24%',
  },
  {
    align: 'right',
    id: 'income',
    label: 'Income',
    render: (item) => <span className="text-sm text-emerald-200">{item.incomeLabel}</span>,
    width: '14%',
  },
  {
    align: 'right',
    id: 'expense',
    label: 'Expense',
    render: (item) => <span className="text-sm text-rose-200">{item.expenseLabel}</span>,
    width: '14%',
  },
  {
    align: 'right',
    id: 'recurring',
    label: 'Recurring',
    render: (item) => <span className="text-sm text-violet-200">{item.recurringLabel}</span>,
    width: '14%',
  },
  {
    align: 'right',
    id: 'total_outflow',
    label: 'Total Outflow',
    render: (item) => <span className="text-sm text-[#dbe7fb]">{item.totalOutflowLabel}</span>,
    width: '14%',
  },
  {
    align: 'right',
    id: 'net',
    label: 'Net',
    render: (item) => (
      <span className={`text-sm font-semibold ${item.netValue >= 0 ? 'text-emerald-300' : 'text-rose-300'}`}>
        {item.netLabel}
      </span>
    ),
    width: '14%',
  },
]

const buildMetricItems = (report) => [
  { icon: Wallet, label: 'Previous Net', tone: 'amber', value: report.previousNetLabel },
  { icon: TrendingUp, label: 'Current Net', tone: 'emerald', value: report.currentNetLabel },
  { icon: GitCompareArrows, label: 'Income Change', tone: 'blue', value: report.incomeChangeLabel },
  { icon: TrendingDown, label: 'Outflow Change', tone: 'cyan', value: report.totalOutflowChangeLabel },
]

function ComparisonChart({ graph, isLoading }) {
  const chartState = useMemo(() => {
    const labels = Array.isArray(graph.labels) ? graph.labels : []
    const datasets = Array.isArray(graph.datasets) ? graph.datasets : []
    const maxValue = graph.maxValue > 0 ? graph.maxValue : 1

    return { datasets, labels, maxValue }
  }, [graph])

  if (isLoading) {
    return <div className="h-72 animate-pulse rounded-2xl border border-[#2a2426] bg-[#120f10]" />
  }

  if (!chartState.labels.length || !chartState.datasets.length) {
    return (
      <div className="flex h-72 items-center justify-center rounded-2xl border border-dashed border-[#312a2d] bg-[#120f10] text-sm text-[#7d8ca5]">
        No chart data available for the selected comparison.
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-[#2a2426] bg-[#120f10] p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#7ea1ff]">Visual Comparison</p>
          <p className="mt-2 text-sm text-[#8fa0bd]">Income, expense, and recurring totals across both months.</p>
        </div>
        <div className="flex flex-wrap gap-3">
          {chartState.datasets.map((dataset) => (
            <div key={dataset.label} className="inline-flex items-center gap-2 text-xs text-[#9fb0cf]">
              <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: dataset.color }} />
              {dataset.label}
            </div>
          ))}
        </div>
      </div>

      <div className="mt-6 grid gap-4 md:grid-cols-2">
        {chartState.labels.map((label, labelIndex) => (
          <article key={label} className="rounded-2xl border border-[#2a2426] bg-[#171314] p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-lg font-semibold text-white">{label}</p>
                <p className="mt-1 text-xs uppercase tracking-[0.18em] text-[#6f86c8]">Month comparison</p>
              </div>
              <span className="rounded-full border border-[#2f3645] px-3 py-1 text-xs text-[#aebfe1]">
                Max {chartState.maxValue.toLocaleString('en-US')}
              </span>
            </div>

            <div className="mt-5 space-y-4">
              {chartState.datasets.map((dataset) => {
                const rawValue = Number(dataset.data?.[labelIndex] ?? 0)
                const width = Math.max((rawValue / chartState.maxValue) * 100, rawValue > 0 ? 6 : 0)

                return (
                  <div key={`${label}-${dataset.label}`} className="space-y-2">
                    <div className="flex items-center justify-between gap-3 text-sm">
                      <span className="text-[#dbe7fb]">{dataset.label}</span>
                      <span className="font-medium text-white">
                        BDT {rawValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                      </span>
                    </div>
                    <div className="h-3 overflow-hidden rounded-full bg-[#231d1f]">
                      <div
                        className="h-full rounded-full transition-[width] duration-500"
                        style={{ backgroundColor: dataset.color, width: `${width}%` }}
                      />
                    </div>
                  </div>
                )
              })}
            </div>
          </article>
        ))}
      </div>
    </div>
  )
}

export default function CurrentVsPreviousMonthAnalysisReportPage() {
  const apiState = useCurrentVsPreviousMonthAnalysisReport()

  const resultLabel = useMemo(() => {
    if (!apiState.pagination.total && !apiState.items.length) {
      return 'No month comparison rows found.'
    }

    return `Showing ${apiState.pagination.from}-${apiState.pagination.to} of ${apiState.pagination.total} comparison rows`
  }, [apiState.items.length, apiState.pagination.from, apiState.pagination.to, apiState.pagination.total])

  return (
    <main className="routes-page">
      <div className="routes-page__inner">
        <header className="routes-page__header">
          <div className="routes-page__title">
            <GitCompareArrows size={20} color="#4f83ff" />
            <h1>{REPORTS_PAGE_COPY.currentVsPreviousMonthAnalysis.title}</h1>
          </div>
          <p className="routes-page__subtitle">{REPORTS_PAGE_COPY.currentVsPreviousMonthAnalysis.subtitle}</p>
        </header>

        <ReportsOverview isLoading={apiState.isLoading} items={buildMetricItems(apiState.report)} />

        <section className="mb-5 rounded-xl border border-[#332d30] bg-[#171314] p-4">
          <div className="grid gap-4 md:grid-cols-3">
            <article className="flex min-h-[88px] flex-col justify-center rounded-xl border border-[#2a2426] bg-[#120f10] p-4">
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#7ea1ff]">Previous Month</p>
              <p className="mt-2 text-lg font-semibold text-white">{apiState.report.previousMonthLabel}</p>
            </article>
            <article className="flex min-h-[88px] flex-col justify-center rounded-xl border border-[#2a2426] bg-[#120f10] p-4">
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#7ea1ff]">Current Month</p>
              <p className="mt-2 text-lg font-semibold text-white">{apiState.report.currentMonthLabel}</p>
            </article>
            <article className="flex min-h-[88px] flex-col justify-center rounded-xl border border-[#2a2426] bg-[#120f10] p-4">
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#7ea1ff]">Comparison Window</p>
              <p className="mt-2 text-lg font-semibold text-white">{apiState.report.monthRangeLabel}</p>
            </article>
          </div>
        </section>

        <div className="mb-5">
          <ComparisonChart graph={apiState.report.graph} isLoading={apiState.isLoading} />
        </div>

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
          columns={comparisonColumns}
          data={apiState.items}
          emptyMessage="No current vs previous month analysis data found."
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
          searchPlaceholder={REPORTS_PAGE_COPY.currentVsPreviousMonthAnalysis.searchPlaceholder}
        />
      </div>
    </main>
  )
}
