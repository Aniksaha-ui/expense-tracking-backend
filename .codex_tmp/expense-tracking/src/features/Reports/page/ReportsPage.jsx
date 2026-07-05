import { ArrowRight, BarChart3, CalendarDays, Flame, GitCompareArrows, PieChart, Tags, TrendingUp, Wallet } from 'lucide-react'
import { Link } from 'react-router-dom'
import { APP_ROUTES } from '../../../constants/routes'
import { REPORTS_PAGE_COPY } from '../constants/reports.constants'

const reportDirectoryItems = [
  {
    description: 'Get a fast snapshot of balances, inflow, outflow, and overall movement for a selected time range.',
    icon: BarChart3,
    path: APP_ROUTES.reportSummary,
    title: 'Summary Report',
  },
  {
    description: 'Review current balances across cash, bank, card, and mobile banking accounts in a single table.',
    icon: Wallet,
    path: APP_ROUTES.reportAccountBalances,
    title: 'Account Balances',
  },
  {
    description: 'Track each month’s total expense and the average daily burn based on active expense days.',
    icon: Flame,
    path: APP_ROUTES.reportBurnRateAnalysis,
    title: 'Burn Rate Analysis',
  },
  {
    description: 'Compare which categories are driving the most expense volume and how much each one contributes.',
    icon: PieChart,
    path: APP_ROUTES.reportCategoryBreakdown,
    title: 'Category Breakdown',
  },
  {
    description: 'See which expense categories are used most often and how much total amount each one has accumulated.',
    icon: Tags,
    path: APP_ROUTES.reportCategoryUsageAnalysis,
    title: 'Category Usage Analysis',
  },
  {
    description: 'Put the current month beside the previous one so you can quickly read income, outflow, and net change.',
    icon: GitCompareArrows,
    path: APP_ROUTES.reportCurrentVsPreviousMonthAnalysis,
    title: 'Current vs Previous Month Analysis',
  },
  {
    description: 'Review each day alongside the categories that generated expense spend during that reporting window.',
    icon: CalendarDays,
    path: APP_ROUTES.reportDaywiseExpenses,
    title: 'Daywise Expenses',
  },
  {
    description: 'Break down the current month into weekly expense blocks so you can spot busy and quiet spending weeks.',
    icon: TrendingUp,
    path: APP_ROUTES.reportWeeklyCurrentMonthAnalysis,
    title: 'Weekly Current Month Analysis',
  },
]

export default function ReportsPage() {
  return (
    <main className="routes-page">
      <div className="routes-page__inner">
        <header className="routes-page__header">
          <div className="routes-page__title">
            <BarChart3 size={20} color="#4f83ff" />
            <h1>{REPORTS_PAGE_COPY.hub.title}</h1>
          </div>
          <p className="routes-page__subtitle">{REPORTS_PAGE_COPY.hub.subtitle}</p>
        </header>

        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {reportDirectoryItems.map((item) => {
            const Icon = item.icon

            return (
              <Link
                key={item.path}
                to={item.path}
                className="group rounded-2xl border border-[#332d30] bg-[#171314] p-5 transition hover:-translate-y-0.5 hover:border-[#4f83ff] hover:bg-[#1b1618]"
              >
                <div className="flex items-start justify-between gap-3">
                  <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400">
                    <Icon size={22} />
                  </span>
                  <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[#6f86c8]">
                    Open
                  </span>
                </div>
                <h2 className="mt-5 text-lg font-semibold text-white">{item.title}</h2>
                <p className="mt-2 text-sm leading-6 text-[#8fa0bd]">{item.description}</p>
                <div className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[#7ea1ff]">
                  View report
                  <ArrowRight size={15} className="transition group-hover:translate-x-1" />
                </div>
              </Link>
            )
          })}
        </section>
      </div>
    </main>
  )
}
