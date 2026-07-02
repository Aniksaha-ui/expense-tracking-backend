import { useCallback, useEffect, useMemo, useState } from 'react'
import { useToast } from '../../../components/common/Toaster'
import {
  buildAccountBalanceMetrics,
  buildCategoryBreakdownMetrics,
  buildDaywiseExpenseMetrics,
  emptyCategoryUsageAnalysisReport,
  emptyCurrentVsPreviousMonthAnalysisReport,
  emptyAccountBalanceMetrics,
  emptyCategoryBreakdownMetrics,
  emptyDaywiseExpenseMetrics,
  emptySummaryReport,
  emptyWeeklyCurrentMonthAnalysisReport,
  fetchAccountBalancesReport,
  fetchCategoryBreakdownReport,
  fetchCategoryUsageAnalysisReport,
  fetchCurrentVsPreviousMonthAnalysisReport,
  fetchDaywiseExpensesReport,
  fetchSummaryReport,
  fetchWeeklyCurrentMonthAnalysisReport,
  filterAccountBalanceRows,
  filterCategoryBreakdownRows,
  filterCategoryUsageAnalysisRows,
  filterCurrentVsPreviousMonthAnalysisRows,
  filterDaywiseExpenseRows,
  filterWeeklyCurrentMonthAnalysisRows,
  paginateReportRows,
  reportEmptyPagination,
} from '../service/reportsService'

const toDateInputValue = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const createDefaultDateRange = () => {
  const today = new Date()
  const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)

  return {
    fromDate: toDateInputValue(firstDayOfMonth),
    toDate: toDateInputValue(today),
  }
}

const loadWithDelay = (callback) => {
  const timeoutId = window.setTimeout(() => {
    void callback()
  }, 0)

  return () => window.clearTimeout(timeoutId)
}

export function useSummaryReport() {
  const defaultDateRange = useMemo(() => createDefaultDateRange(), [])
  const toast = useToast()
  const [fromDate, setFromDate] = useState(defaultDateRange.fromDate)
  const [toDate, setToDate] = useState(defaultDateRange.toDate)
  const [report, setReport] = useState(emptySummaryReport)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setError('')

    try {
      setReport(await fetchSummaryReport({ fromDate, toDate }))
    } catch (loadError) {
      const message = loadError.message || 'Unable to load summary report.'
      setReport(emptySummaryReport)
      setError(message)
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }, [fromDate, toDate, toast])

  useEffect(() => loadWithDelay(loadReport), [loadReport])

  return {
    defaultDateRange,
    error,
    fromDate,
    isLoading,
    refresh: loadReport,
    report,
    setFromDate,
    setToDate,
    toDate,
  }
}

export function useAccountBalancesReport() {
  const toast = useToast()
  const [allItems, setAllItems] = useState([])
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [typeFilter, setTypeFilter] = useState('all')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setError('')

    try {
      setAllItems(await fetchAccountBalancesReport())
    } catch (loadError) {
      const message = loadError.message || 'Unable to load account balances report.'
      setAllItems([])
      setError(message)
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }, [toast])

  useEffect(() => loadWithDelay(loadReport), [loadReport])

  const filteredItems = useMemo(
    () => filterAccountBalanceRows(allItems, { search, typeFilter }),
    [allItems, search, typeFilter],
  )
  const paginatedState = useMemo(() => paginateReportRows(filteredItems, page), [filteredItems, page])
  const metrics = useMemo(
    () => (allItems.length ? buildAccountBalanceMetrics(allItems) : emptyAccountBalanceMetrics),
    [allItems],
  )

  useEffect(() => {
    if (page > paginatedState.pagination.lastPage) {
      setPage(paginatedState.pagination.lastPage)
    }
  }, [page, paginatedState.pagination.lastPage])

  return {
    error,
    isLoading,
    items: paginatedState.rows,
    metrics,
    pagination: allItems.length ? paginatedState.pagination : reportEmptyPagination,
    refresh: loadReport,
    search,
    setPage,
    setSearch,
    setTypeFilter,
    typeFilter,
  }
}

export function useCategoryBreakdownReport() {
  const defaultDateRange = useMemo(() => createDefaultDateRange(), [])
  const toast = useToast()
  const [allItems, setAllItems] = useState([])
  const [fromDate, setFromDate] = useState(defaultDateRange.fromDate)
  const [toDate, setToDate] = useState(defaultDateRange.toDate)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [typeFilter, setTypeFilter] = useState('all')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setError('')

    try {
      setAllItems(await fetchCategoryBreakdownReport({ fromDate, toDate }))
    } catch (loadError) {
      const message = loadError.message || 'Unable to load category breakdown report.'
      setAllItems([])
      setError(message)
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }, [fromDate, toDate, toast])

  useEffect(() => loadWithDelay(loadReport), [loadReport])

  const filteredItems = useMemo(
    () => filterCategoryBreakdownRows(allItems, { search, typeFilter }),
    [allItems, search, typeFilter],
  )
  const paginatedState = useMemo(() => paginateReportRows(filteredItems, page), [filteredItems, page])
  const metrics = useMemo(
    () => (allItems.length ? buildCategoryBreakdownMetrics(allItems) : emptyCategoryBreakdownMetrics),
    [allItems],
  )

  useEffect(() => {
    if (page > paginatedState.pagination.lastPage) {
      setPage(paginatedState.pagination.lastPage)
    }
  }, [page, paginatedState.pagination.lastPage])

  return {
    defaultDateRange,
    error,
    fromDate,
    isLoading,
    items: paginatedState.rows,
    metrics,
    pagination: allItems.length ? paginatedState.pagination : reportEmptyPagination,
    refresh: loadReport,
    search,
    setFromDate,
    setPage,
    setSearch,
    setToDate,
    setTypeFilter,
    toDate,
    typeFilter,
  }
}

export function useDaywiseExpenseReport() {
  const defaultDateRange = useMemo(() => createDefaultDateRange(), [])
  const toast = useToast()
  const [allItems, setAllItems] = useState([])
  const [fromDate, setFromDate] = useState(defaultDateRange.fromDate)
  const [toDate, setToDate] = useState(defaultDateRange.toDate)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setError('')

    try {
      setAllItems(await fetchDaywiseExpensesReport({ fromDate, toDate }))
    } catch (loadError) {
      const message = loadError.message || 'Unable to load daywise expense report.'
      setAllItems([])
      setError(message)
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }, [fromDate, toDate, toast])

  useEffect(() => loadWithDelay(loadReport), [loadReport])

  const filteredItems = useMemo(
    () => filterDaywiseExpenseRows(allItems, { search }),
    [allItems, search],
  )
  const paginatedState = useMemo(() => paginateReportRows(filteredItems, page), [filteredItems, page])
  const metrics = useMemo(
    () => (allItems.length ? buildDaywiseExpenseMetrics(allItems) : emptyDaywiseExpenseMetrics),
    [allItems],
  )

  useEffect(() => {
    if (page > paginatedState.pagination.lastPage) {
      setPage(paginatedState.pagination.lastPage)
    }
  }, [page, paginatedState.pagination.lastPage])

  return {
    defaultDateRange,
    error,
    fromDate,
    isLoading,
    items: paginatedState.rows,
    metrics,
    pagination: allItems.length ? paginatedState.pagination : reportEmptyPagination,
    refresh: loadReport,
    search,
    setFromDate,
    setPage,
    setSearch,
    setToDate,
    toDate,
  }
}

export function useWeeklyCurrentMonthAnalysisReport() {
  const toast = useToast()
  const [report, setReport] = useState(emptyWeeklyCurrentMonthAnalysisReport)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setError('')

    try {
      setReport(await fetchWeeklyCurrentMonthAnalysisReport())
    } catch (loadError) {
      const message = loadError.message || 'Unable to load weekly current month analysis.'
      setReport(emptyWeeklyCurrentMonthAnalysisReport)
      setError(message)
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }, [toast])

  useEffect(() => loadWithDelay(loadReport), [loadReport])

  const filteredItems = useMemo(
    () => filterWeeklyCurrentMonthAnalysisRows(report.weeks, { search }),
    [report.weeks, search],
  )
  const paginatedState = useMemo(() => paginateReportRows(filteredItems, page), [filteredItems, page])

  useEffect(() => {
    if (page > paginatedState.pagination.lastPage) {
      setPage(paginatedState.pagination.lastPage)
    }
  }, [page, paginatedState.pagination.lastPage])

  return {
    error,
    isLoading,
    items: paginatedState.rows,
    pagination: report.weeks.length ? paginatedState.pagination : reportEmptyPagination,
    refresh: loadReport,
    report,
    search,
    setPage,
    setSearch,
  }
}

export function useCurrentVsPreviousMonthAnalysisReport() {
  const toast = useToast()
  const [report, setReport] = useState(emptyCurrentVsPreviousMonthAnalysisReport)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setError('')

    try {
      setReport(await fetchCurrentVsPreviousMonthAnalysisReport())
    } catch (loadError) {
      const message = loadError.message || 'Unable to load current vs previous month analysis.'
      setReport(emptyCurrentVsPreviousMonthAnalysisReport)
      setError(message)
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }, [toast])

  useEffect(() => loadWithDelay(loadReport), [loadReport])

  const filteredItems = useMemo(
    () => filterCurrentVsPreviousMonthAnalysisRows(report.rows, { search }),
    [report.rows, search],
  )
  const paginatedState = useMemo(() => paginateReportRows(filteredItems, page), [filteredItems, page])

  useEffect(() => {
    if (page > paginatedState.pagination.lastPage) {
      setPage(paginatedState.pagination.lastPage)
    }
  }, [page, paginatedState.pagination.lastPage])

  return {
    error,
    isLoading,
    items: paginatedState.rows,
    pagination: report.rows.length ? paginatedState.pagination : reportEmptyPagination,
    refresh: loadReport,
    report,
    search,
    setPage,
    setSearch,
  }
}

export function useCategoryUsageAnalysisReport() {
  const toast = useToast()
  const [report, setReport] = useState(emptyCategoryUsageAnalysisReport)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setError('')

    try {
      setReport(await fetchCategoryUsageAnalysisReport())
    } catch (loadError) {
      const message = loadError.message || 'Unable to load category usage analysis.'
      setReport(emptyCategoryUsageAnalysisReport)
      setError(message)
      toast.error(message)
    } finally {
      setIsLoading(false)
    }
  }, [toast])

  useEffect(() => loadWithDelay(loadReport), [loadReport])

  const filteredItems = useMemo(
    () => filterCategoryUsageAnalysisRows(report.rows, { search }),
    [report.rows, search],
  )
  const paginatedState = useMemo(() => paginateReportRows(filteredItems, page), [filteredItems, page])

  useEffect(() => {
    if (page > paginatedState.pagination.lastPage) {
      setPage(paginatedState.pagination.lastPage)
    }
  }, [page, paginatedState.pagination.lastPage])

  return {
    error,
    isLoading,
    items: paginatedState.rows,
    pagination: report.rows.length ? paginatedState.pagination : reportEmptyPagination,
    refresh: loadReport,
    report,
    search,
    setPage,
    setSearch,
  }
}
