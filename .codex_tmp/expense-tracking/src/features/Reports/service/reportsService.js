import { API_URLS } from '../../../constants/apiUrls'
import { apiRequest } from '../../../services/apiClient'
import { unwrapResponseData } from '../../../services/resourceApi'

const REPORT_PAGE_SIZE = 10

const countFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })
const currencyFormatter = new Intl.NumberFormat('en-US', {
  maximumFractionDigits: 2,
  minimumFractionDigits: 2,
})
const percentFormatter = new Intl.NumberFormat('en-US', {
  maximumFractionDigits: 1,
  minimumFractionDigits: 1,
})
const dateFormatter = new Intl.DateTimeFormat('en-US', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
})

const accountTypeLabels = {
  BANK: 'Bank',
  CARD: 'Card',
  CASH: 'Cash',
  MOBILE_BANKING: 'Mobile Banking',
}

const accountTypeToneClassNames = {
  BANK: 'border-blue-500/20 bg-blue-500/10 text-blue-100',
  CARD: 'border-amber-500/20 bg-amber-500/10 text-amber-100',
  CASH: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-100',
  MOBILE_BANKING: 'border-cyan-500/20 bg-cyan-500/10 text-cyan-100',
}

const categoryTypeToneClassNames = {
  EXPENSE: 'border-rose-500/20 bg-rose-500/10 text-rose-100',
  INCOME: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-100',
}

const toNumber = (value) => Number(value) || 0
const toBoolean = (value) => value === true || value === 1 || value === '1'

const formatCurrencyLabel = (value) => `BDT ${currencyFormatter.format(toNumber(value))}`

const formatDateLabel = (value, fallback = 'Not available') => {
  if (!value) {
    return fallback
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return String(value)
  }

  return dateFormatter.format(date)
}

const buildReportPath = (endpoint, filters = {}) => {
  const params = new URLSearchParams()

  if (filters.fromDate) {
    params.set('from_date', filters.fromDate)
  }

  if (filters.toDate) {
    params.set('to_date', filters.toDate)
  }

  const query = params.toString()

  return query ? `${endpoint}?${query}` : endpoint
}

const paginateRows = (items = [], page = 1) => {
  const total = items.length
  const lastPage = Math.max(Math.ceil(total / REPORT_PAGE_SIZE), 1)
  const currentPage = Math.min(Math.max(page, 1), lastPage)
  const from = total ? (currentPage - 1) * REPORT_PAGE_SIZE + 1 : 0
  const to = total ? Math.min(currentPage * REPORT_PAGE_SIZE, total) : 0
  const startIndex = total ? from - 1 : 0
  const rows = items
    .slice(startIndex, startIndex + REPORT_PAGE_SIZE)
    .map((item, index) => ({
      ...item,
      serial: from + index,
    }))

  return {
    pagination: {
      currentPage,
      from,
      lastPage,
      to,
      total,
    },
    rows,
  }
}

const normalizeSummaryReport = (item = {}) => {
  const totalIncome = toNumber(item.total_income)
  const totalExpense = toNumber(item.total_expense)
  const totalWithdraw = toNumber(item.total_withdraw)
  const totalTransferOut = toNumber(item.total_transfer_out)
  const currentBalance = toNumber(item.current_balance)
  const netMovement = totalIncome - totalExpense - totalWithdraw - totalTransferOut

  return {
    accountCountLabel: countFormatter.format(toNumber(item.account_count)),
    currentBalanceLabel: formatCurrencyLabel(currentBalance),
    fromDateLabel: formatDateLabel(item.from_date, 'Beginning'),
    netMovementLabel: formatCurrencyLabel(netMovement),
    netMovementTone: netMovement >= 0 ? 'text-emerald-300' : 'text-rose-300',
    toDateLabel: formatDateLabel(item.to_date, 'Today'),
    totalExpenseLabel: formatCurrencyLabel(totalExpense),
    totalIncomeLabel: formatCurrencyLabel(totalIncome),
    totalTransferOutLabel: formatCurrencyLabel(totalTransferOut),
    totalWithdrawLabel: formatCurrencyLabel(totalWithdraw),
  }
}

const normalizeAccountBalance = (item = {}) => {
  const normalizedType = String(item.type ?? 'BANK')
    .trim()
    .toUpperCase()
  const institutionName = String(item.institution_name ?? '').trim()
  const currentBalance = toNumber(item.current_balance)
  const isActive = toBoolean(item.is_active)

  return {
    currentBalanceAmount: currentBalance,
    currentBalanceLabel: formatCurrencyLabel(currentBalance),
    id: item.id,
    institutionLabel:
      institutionName || (normalizedType === 'CASH' ? 'Cash wallet' : 'No institution provided'),
    is_active: isActive,
    name: item.name ?? 'Untitled account',
    searchText: [item.id, item.name, institutionName, normalizedType, isActive ? 'active' : 'inactive']
      .filter(Boolean)
      .join(' ')
      .toLowerCase(),
    statusLabel: isActive ? 'Active' : 'Inactive',
    statusToneClassName: isActive
      ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-100'
      : 'border-slate-500/20 bg-slate-500/10 text-slate-200',
    type: normalizedType,
    typeLabel: accountTypeLabels[normalizedType] ?? normalizedType,
    typeToneClassName:
      accountTypeToneClassNames[normalizedType] ?? 'border-blue-500/20 bg-blue-500/10 text-blue-100',
    updatedAtLabel: formatDateLabel(item.updated_at ?? item.created_at),
  }
}

const normalizeCategoryBreakdown = (item = {}, index, totalSpend) => {
  const totalAmount = toNumber(item.total_amount)
  const normalizedType = String(item.category_type ?? 'EXPENSE')
    .trim()
    .toUpperCase()
  const share = totalSpend > 0 ? (totalAmount / totalSpend) * 100 : 0

  return {
    categoryIdLabel: item.category_id ?? 'N/A',
    categoryName: item.category_name ?? 'Unknown category',
    categoryType: normalizedType,
    categoryTypeLabel:
      normalizedType === 'INCOME' ? 'Income' : normalizedType === 'EXPENSE' ? 'Expense' : normalizedType,
    categoryTypeToneClassName:
      categoryTypeToneClassNames[normalizedType] ?? 'border-blue-500/20 bg-blue-500/10 text-blue-100',
    rankLabel: `Rank #${index + 1}`,
    searchText: [item.category_id, item.category_name, normalizedType, item.total_amount]
      .filter(Boolean)
      .join(' ')
      .toLowerCase(),
    shareLabel: `${percentFormatter.format(share)}%`,
    spendHintLabel: share >= 50 ? 'Dominates expense spend' : share >= 20 ? 'Major contributor' : 'Supports the spend mix',
    totalAmountLabel: formatCurrencyLabel(totalAmount),
    totalAmountValue: totalAmount,
  }
}

const normalizeDaywiseExpense = (item = {}, index, totalSpend) => {
  const totalAmount = toNumber(item.total_amount)
  const normalizedType = String(item.category_type ?? 'EXPENSE')
    .trim()
    .toUpperCase()
  const transactionCount = toNumber(item.transaction_count)
  const share = totalSpend > 0 ? (totalAmount / totalSpend) * 100 : 0

  return {
    categoryIdLabel: item.category_id ?? 'N/A',
    categoryName: item.category_name ?? 'Unknown category',
    categoryType: normalizedType,
    categoryTypeLabel:
      normalizedType === 'INCOME' ? 'Income' : normalizedType === 'EXPENSE' ? 'Expense' : normalizedType,
    categoryTypeToneClassName:
      categoryTypeToneClassNames[normalizedType] ?? 'border-blue-500/20 bg-blue-500/10 text-blue-100',
    expenseDate: item.expense_date ?? '',
    expenseDateLabel: formatDateLabel(item.expense_date),
    rankLabel: `Row #${index + 1}`,
    searchText: [
      item.expense_date,
      item.category_id,
      item.category_name,
      normalizedType,
      item.total_amount,
      item.transaction_count,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase(),
    shareLabel: `${percentFormatter.format(share)}%`,
    totalAmountLabel: formatCurrencyLabel(totalAmount),
    totalAmountValue: totalAmount,
    transactionCount,
    transactionCountLabel: countFormatter.format(transactionCount),
  }
}

const sortAccountBalances = (items = []) =>
  [...items].sort((firstItem, secondItem) => {
    if (firstItem.type !== secondItem.type) {
      return firstItem.type.localeCompare(secondItem.type)
    }

    return firstItem.name.localeCompare(secondItem.name)
  })

const sortCategoryBreakdown = (items = []) =>
  [...items].sort((firstItem, secondItem) => secondItem.totalAmountValue - firstItem.totalAmountValue)

const sortDaywiseExpenses = (items = []) =>
  [...items].sort((firstItem, secondItem) => {
    if (firstItem.expenseDate !== secondItem.expenseDate) {
      return secondItem.expenseDate.localeCompare(firstItem.expenseDate)
    }

    if (firstItem.totalAmountValue !== secondItem.totalAmountValue) {
      return secondItem.totalAmountValue - firstItem.totalAmountValue
    }

    return firstItem.categoryName.localeCompare(secondItem.categoryName)
  })

export const emptySummaryReport = normalizeSummaryReport({})

export const emptyAccountBalanceMetrics = {
  activeCountLabel: '0',
  cashCountLabel: '0',
  totalBalanceLabel: 'BDT 0.00',
  totalCountLabel: '0',
}

export const emptyCategoryBreakdownMetrics = {
  topCategoryLabel: 'No data',
  topShareLabel: '0.0%',
  totalCategoriesLabel: '0',
  totalSpendLabel: 'BDT 0.00',
}

export const emptyDaywiseExpenseMetrics = {
  totalCategoriesLabel: '0',
  totalDaysLabel: '0',
  totalSpendLabel: 'BDT 0.00',
  totalTransactionsLabel: '0',
}

export const emptyWeeklyCurrentMonthAnalysisReport = {
  activeWeeksLabel: '0',
  dateRangeLabel: 'No reporting window',
  monthLabel: 'Current Month',
  totalExpenseLabel: 'BDT 0.00',
  totalTransactionsLabel: '0',
  weeks: [],
  weeksInMonthLabel: '0',
}

export const reportEmptyPagination = {
  currentPage: 1,
  from: 0,
  lastPage: 1,
  to: 0,
  total: 0,
}

export const fetchSummaryReport = async (filters = {}) =>
  normalizeSummaryReport(
    unwrapResponseData(
      await apiRequest(buildReportPath(API_URLS.reports.summary, filters)),
      'Unable to load summary report.',
    ),
  )

export const fetchAccountBalancesReport = async () => {
  const data = unwrapResponseData(
    await apiRequest(API_URLS.reports.accountBalances),
    'Unable to load account balances report.',
  )
  const items = Array.isArray(data) ? data : []

  return sortAccountBalances(items.map(normalizeAccountBalance))
}

export const fetchCategoryBreakdownReport = async (filters = {}) => {
  const data = unwrapResponseData(
    await apiRequest(buildReportPath(API_URLS.reports.categoryBreakdown, filters)),
    'Unable to load category breakdown report.',
  )
  const items = Array.isArray(data) ? data : []
  const totalSpend = items.reduce((sum, item) => sum + toNumber(item.total_amount), 0)

  return sortCategoryBreakdown(items.map((item, index) => normalizeCategoryBreakdown(item, index, totalSpend)))
}

export const fetchDaywiseExpensesReport = async (filters = {}) => {
  const data = unwrapResponseData(
    await apiRequest(buildReportPath(API_URLS.reports.daywiseExpenses, filters)),
    'Unable to load daywise expense report.',
  )
  const items = Array.isArray(data) ? data : []
  const totalSpend = items.reduce((sum, item) => sum + toNumber(item.total_amount), 0)

  return sortDaywiseExpenses(items.map((item, index) => normalizeDaywiseExpense(item, index, totalSpend)))
}

export const fetchWeeklyCurrentMonthAnalysisReport = async () => {
  const data = unwrapResponseData(
    await apiRequest(API_URLS.reports.weeklyCurrentMonthAnalysis),
    'Unable to load weekly current month analysis.',
  )

  const weeks = Array.isArray(data?.weeks) ? data.weeks : []
  const summary = data?.summary ?? {}
  const month = data?.month ?? {}
  const totalExpense = toNumber(summary.total_expense)
  const normalizedWeeks = [...weeks]
    .map((item, index) => {
      const totalAmount = toNumber(item.total_expense)
      const transactionCount = toNumber(item.transaction_count)
      const averageExpense = toNumber(item.average_expense)
      const share = totalExpense > 0 ? (totalAmount / totalExpense) * 100 : 0

      return {
        averageExpenseLabel: formatCurrencyLabel(averageExpense),
        averageExpenseValue: averageExpense,
        calendarWeekLabel: item.calendar_week_label ?? `Week ${item.week ?? index + 1}`,
        rangeLabel: item.range_label ?? 'No date range',
        searchText: [
          item.week_label,
          item.calendar_week_label,
          item.range_label,
          item.total_expense,
          item.transaction_count,
        ]
          .filter(Boolean)
          .join(' ')
          .toLowerCase(),
        shareLabel: `${percentFormatter.format(share)}%`,
        totalAmountLabel: formatCurrencyLabel(totalAmount),
        totalAmountValue: totalAmount,
        transactionCount,
        transactionCountLabel: countFormatter.format(transactionCount),
        weekEnd: item.week_end ?? '',
        weekLabel: item.week_label ?? `Week ${index + 1}`,
        weekSequence: toNumber(item.week_sequence) || index + 1,
        weekStart: item.week_start ?? '',
      }
    })
    .sort((firstItem, secondItem) => firstItem.weekSequence - secondItem.weekSequence)

  return {
    activeWeeksLabel: countFormatter.format(toNumber(summary.active_weeks)),
    dateRangeLabel: `${formatDateLabel(month.from_date)} - ${formatDateLabel(month.to_date)}`,
    monthLabel:
      month.month_name && month.year ? `${month.month_name} ${month.year}` : 'Current Month',
    totalExpenseLabel: formatCurrencyLabel(totalExpense),
    totalTransactionsLabel: countFormatter.format(toNumber(summary.total_transactions)),
    weeks: normalizedWeeks,
    weeksInMonthLabel: countFormatter.format(toNumber(summary.weeks_in_month)),
  }
}

export const filterAccountBalanceRows = (items = [], { search = '', typeFilter = 'all' } = {}) => {
  const normalizedSearch = String(search ?? '').trim().toLowerCase()

  return items.filter((item) => {
    const matchesType = typeFilter === 'all' || item.type === typeFilter
    const matchesSearch = !normalizedSearch || item.searchText.includes(normalizedSearch)

    return matchesType && matchesSearch
  })
}

export const filterCategoryBreakdownRows = (items = [], { search = '', typeFilter = 'all' } = {}) => {
  const normalizedSearch = String(search ?? '').trim().toLowerCase()

  return items.filter((item) => {
    const matchesType = typeFilter === 'all' || item.categoryType === typeFilter
    const matchesSearch = !normalizedSearch || item.searchText.includes(normalizedSearch)

    return matchesType && matchesSearch
  })
}

export const filterDaywiseExpenseRows = (items = [], { search = '' } = {}) => {
  const normalizedSearch = String(search ?? '').trim().toLowerCase()

  return items.filter((item) => !normalizedSearch || item.searchText.includes(normalizedSearch))
}

export const filterWeeklyCurrentMonthAnalysisRows = (items = [], { search = '' } = {}) => {
  const normalizedSearch = String(search ?? '').trim().toLowerCase()

  return items.filter((item) => !normalizedSearch || item.searchText.includes(normalizedSearch))
}

export const paginateReportRows = paginateRows

export const buildAccountBalanceMetrics = (items = []) => {
  const activeCount = items.filter((item) => item.is_active).length
  const cashCount = items.filter((item) => item.type === 'CASH').length
  const totalBalance = items.reduce((sum, item) => sum + item.currentBalanceAmount, 0)

  return {
    activeCountLabel: countFormatter.format(activeCount),
    cashCountLabel: countFormatter.format(cashCount),
    totalBalanceLabel: formatCurrencyLabel(totalBalance),
    totalCountLabel: countFormatter.format(items.length),
  }
}

export const buildCategoryBreakdownMetrics = (items = []) => {
  const totalSpend = items.reduce((sum, item) => sum + item.totalAmountValue, 0)
  const topCategory = items[0]

  return {
    topCategoryLabel: topCategory ? topCategory.categoryName : 'No data',
    topShareLabel: topCategory ? topCategory.shareLabel : '0.0%',
    totalCategoriesLabel: countFormatter.format(items.length),
    totalSpendLabel: formatCurrencyLabel(totalSpend),
  }
}

export const buildDaywiseExpenseMetrics = (items = []) => {
  const totalSpend = items.reduce((sum, item) => sum + item.totalAmountValue, 0)
  const totalTransactions = items.reduce((sum, item) => sum + item.transactionCount, 0)
  const uniqueDates = new Set(items.map((item) => item.expenseDate).filter(Boolean))
  const uniqueCategories = new Set(items.map((item) => item.categoryIdLabel).filter(Boolean))

  return {
    totalCategoriesLabel: countFormatter.format(uniqueCategories.size),
    totalDaysLabel: countFormatter.format(uniqueDates.size),
    totalSpendLabel: formatCurrencyLabel(totalSpend),
    totalTransactionsLabel: countFormatter.format(totalTransactions),
  }
}
