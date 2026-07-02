import { BarChart3, RefreshCcw, Tags, Wallet } from 'lucide-react'
import { useMemo } from 'react'
import AdminDataTable, { AdminTableButton } from '../../../components/ui/AdminDataTable'
import { ReportsOverview } from '../component/ReportsOverview.jsx'
import { REPORTS_PAGE_COPY } from '../constants/reports.constants'
import { useCategoryUsageAnalysisReport } from '../hooks/useReports'

const categoryUsageColumns = [
  {
    accessor: 'serial',
    id: 'serial',
    label: 'SL',
    width: '6%',
  },
  {
    id: 'category',
    label: 'Category',
    render: (item) => (
      <div className="space-y-1">
        <p className="font-semibold text-white">{item.categoryName}</p>
        <p className="text-xs text-[#7d8ca5]">Category ID: {item.categoryIdLabel}</p>
      </div>
    ),
    width: '28%',
  },
  {
    align: 'right',
    id: 'usage_count',
    label: 'Usage Count',
    render: (item) => <span className="text-sm text-[#dbe7fb]">{item.usageCountLabel}</span>,
    width: '14%',
  },
  {
    align: 'right',
    id: 'total_amount',
    label: 'Total Amount',
    render: (item) => <span className="text-sm font-semibold text-white">{item.totalAmountLabel}</span>,
    width: '18%',
  },
  {
    align: 'right',
    id: 'share',
    label: 'Share',
    render: (item) => <span className="text-sm text-[#dbe7fb]">{item.shareLabel}</span>,
    width: '12%',
  },
  {
    id: 'summary',
    label: 'Summary',
    render: (item) => (
      <div className="space-y-1">
        <p className="text-sm text-white">{item.rankLabel}</p>
        <p className="text-xs text-[#7d8ca5]">{item.usageHintLabel}</p>
      </div>
    ),
    width: '22%',
  },
]

const buildMetricItems = (report) => [
  { icon: Tags, label: 'Tracked Categories', tone: 'blue', value: report.totalCategoriesLabel },
  { icon: BarChart3, label: 'Usage Count', tone: 'cyan', value: report.totalUsageCountLabel },
  { icon: Wallet, label: 'Total Amount', tone: 'amber', value: report.totalAmountLabel },
  { icon: Tags, label: 'Top Category', tone: 'emerald', value: report.topCategoryLabel },
]

function CategoryUsageChart({ graph, isLoading }) {
  const chartState = useMemo(() => {
    const labels = Array.isArray(graph.labels) ? graph.labels.slice(0, 6) : []
    const datasets = Array.isArray(graph.datasets)
      ? graph.datasets.map((dataset) => ({
          ...dataset,
          data: Array.isArray(dataset.data) ? dataset.data.slice(0, 6) : [],
        }))
      : []
    const maxValue = graph.maxValue > 0 ? graph.maxValue : 1

    return { datasets, labels, maxValue }
  }, [graph])

  if (isLoading) {
    return <div className="h-72 animate-pulse rounded-2xl border border-[#2a2426] bg-[#120f10]" />
  }

  if (!chartState.labels.length || !chartState.datasets.length) {
    return (
      <div className="flex h-72 items-center justify-center rounded-2xl border border-dashed border-[#312a2d] bg-[#120f10] text-sm text-[#7d8ca5]">
        No category usage chart data available.
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-[#2a2426] bg-[#120f10] p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#7ea1ff]">Top Categories</p>
          <p className="mt-2 text-sm text-[#8fa0bd]">The first six categories ranked by usage count.</p>
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

      <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {chartState.labels.map((label, labelIndex) => (
          <article key={label} className="rounded-2xl border border-[#2a2426] bg-[#171314] p-4">
            <p className="text-base font-semibold text-white">{label}</p>
            <div className="mt-4 space-y-4">
              {chartState.datasets.map((dataset) => {
                const rawValue = Number(dataset.data?.[labelIndex] ?? 0)
                const width = Math.max((rawValue / chartState.maxValue) * 100, rawValue > 0 ? 6 : 0)

                return (
                  <div key={`${label}-${dataset.label}`} className="space-y-2">
                    <div className="flex items-center justify-between gap-3 text-sm">
                      <span className="text-[#dbe7fb]">{dataset.label}</span>
                      <span className="font-medium text-white">
                        {dataset.label === 'Total Amount'
                          ? `BDT ${rawValue.toLocaleString('en-US', {
                              minimumFractionDigits: 2,
                              maximumFractionDigits: 2,
                            })}`
                          : rawValue.toLocaleString('en-US')}
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

export default function CategoryUsageAnalysisReportPage() {
  const apiState = useCategoryUsageAnalysisReport()

  const resultLabel = useMemo(() => {
    if (!apiState.pagination.total && !apiState.items.length) {
      return 'No category usage records found.'
    }

    return `Showing ${apiState.pagination.from}-${apiState.pagination.to} of ${apiState.pagination.total} matched categories`
  }, [apiState.items.length, apiState.pagination.from, apiState.pagination.to, apiState.pagination.total])

  return (
    <main className="routes-page">
      <div className="routes-page__inner">
        <header className="routes-page__header">
          <div className="routes-page__title">
            <BarChart3 size={20} color="#4f83ff" />
            <h1>{REPORTS_PAGE_COPY.categoryUsageAnalysis.title}</h1>
          </div>
          <p className="routes-page__subtitle">{REPORTS_PAGE_COPY.categoryUsageAnalysis.subtitle}</p>
        </header>

        <ReportsOverview isLoading={apiState.isLoading} items={buildMetricItems(apiState.report)} />

        <div className="mb-5">
          <CategoryUsageChart graph={apiState.report.graph} isLoading={apiState.isLoading} />
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
          columns={categoryUsageColumns}
          data={apiState.items}
          emptyMessage="No category usage analysis data found."
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
          searchPlaceholder={REPORTS_PAGE_COPY.categoryUsageAnalysis.searchPlaceholder}
        />
      </div>
    </main>
  )
}
