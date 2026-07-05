import { lazy, Suspense } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import './App.css'
import FullPageLoader from './components/common/FullPageLoader'
import { ToastProvider } from './components/common/Toaster'
import { APP_ROUTES } from './constants/routes'
import { AuthProvider, useAuthContext } from './contexts/AuthContext'
import { AppLayout } from './layout/AppLayout'

const Dashboard = lazy(() => import('./features/Dashboard/page/DashboardPage'))
const AccountsPage = lazy(() => import('./features/Accounts/page/AccountsPage'))
const AccountBalancesReportPage = lazy(() => import('./features/Reports/page/AccountBalancesReportPage'))
const BurnRateAnalysisReportPage = lazy(() => import('./features/Reports/page/BurnRateAnalysisReportPage'))
const CategoriesPage = lazy(() => import('./features/Categories/page/CategoriesPage'))
const CategoryBreakdownReportPage = lazy(() => import('./features/Reports/page/CategoryBreakdownReportPage'))
const CategoryUsageAnalysisReportPage = lazy(
  () => import('./features/Reports/page/CategoryUsageAnalysisReportPage'),
)
const CurrentVsPreviousMonthAnalysisReportPage = lazy(
  () => import('./features/Reports/page/CurrentVsPreviousMonthAnalysisReportPage'),
)
const DaywiseExpenseReportPage = lazy(() => import('./features/Reports/page/DaywiseExpenseReportPage'))
const WeeklyCurrentMonthAnalysisReportPage = lazy(
  () => import('./features/Reports/page/WeeklyCurrentMonthAnalysisReportPage'),
)
const RecurringExpensesPage = lazy(() => import('./features/RecurringExpenses/page/RecurringExpensesPage'))
const ReportsPage = lazy(() => import('./features/Reports/page/ReportsPage'))
const SummaryReportPage = lazy(() => import('./features/Reports/page/SummaryReportPage'))
const TransfersPage = lazy(() => import('./features/Transfers/page/TransfersPage'))
const TransactionsPage = lazy(() => import('./features/Transactions/page/TransactionsPage'))
const MenuItemFormPage = lazy(() => import('./features/MenuItems/page/MenuItemFormPage'))
const MenuItemsPage = lazy(() => import('./features/MenuItems/page/MenuItemsPage'))
const LoginPage = lazy(() => import('./features/auth/page/LoginPage'))

function ProtectedRoute({ children }) {
  const {
    auth: { isAuthenticated },
  } = useAuthContext()

  if (!isAuthenticated) {
    return <Navigate to={APP_ROUTES.login} replace />
  }

  return children
}

function GuestRoute({ children }) {
  const {
    auth: { isAuthenticated },
  } = useAuthContext()

  if (isAuthenticated) {
    return <Navigate to={APP_ROUTES.dashboard} replace />
  }

  return children
}

function AppRoutes() {
  const {
    auth: { isAuthenticated },
  } = useAuthContext()

  return (
    <Routes>
      <Route
        path={APP_ROUTES.login}
        element={
          <GuestRoute>
            <Suspense fallback={<FullPageLoader message="Loading login..." />}>
              <LoginPage />
            </Suspense>
          </GuestRoute>
        }
      />
      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/" element={<Navigate to={APP_ROUTES.dashboard} replace />} />

        <Route
          path={APP_ROUTES.accounts}
          element={
            <Suspense fallback={<FullPageLoader message="Loading accounts..." />}>
              <AccountsPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.categories}
          element={
            <Suspense fallback={<FullPageLoader message="Loading categories..." />}>
              <CategoriesPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.transactions}
          element={
            <Suspense fallback={<FullPageLoader message="Loading transactions..." />}>
              <TransactionsPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.transfers}
          element={
            <Suspense fallback={<FullPageLoader message="Loading transfers..." />}>
              <TransfersPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.recurringExpenses}
          element={
            <Suspense fallback={<FullPageLoader message="Loading recurring expenses..." />}>
              <RecurringExpensesPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reports}
          element={
            <Suspense fallback={<FullPageLoader message="Loading reports..." />}>
              <ReportsPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportSummary}
          element={
            <Suspense fallback={<FullPageLoader message="Loading summary report..." />}>
              <SummaryReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportAccountBalances}
          element={
            <Suspense fallback={<FullPageLoader message="Loading account balances report..." />}>
              <AccountBalancesReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportBurnRateAnalysis}
          element={
            <Suspense fallback={<FullPageLoader message="Loading burn rate analysis..." />}>
              <BurnRateAnalysisReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportCategoryBreakdown}
          element={
            <Suspense fallback={<FullPageLoader message="Loading category breakdown report..." />}>
              <CategoryBreakdownReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportCategoryUsageAnalysis}
          element={
            <Suspense fallback={<FullPageLoader message="Loading category usage analysis..." />}>
              <CategoryUsageAnalysisReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportCurrentVsPreviousMonthAnalysis}
          element={
            <Suspense fallback={<FullPageLoader message="Loading current vs previous month analysis..." />}>
              <CurrentVsPreviousMonthAnalysisReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportDaywiseExpenses}
          element={
            <Suspense fallback={<FullPageLoader message="Loading daywise expense report..." />}>
              <DaywiseExpenseReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.reportWeeklyCurrentMonthAnalysis}
          element={
            <Suspense fallback={<FullPageLoader message="Loading weekly current month analysis..." />}>
              <WeeklyCurrentMonthAnalysisReportPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.menuItems}
          element={
            <Suspense fallback={<FullPageLoader message="Loading menu items..." />}>
              <MenuItemsPage />
            </Suspense>
          }
        />
        <Route
          path={APP_ROUTES.dashboard}
          element={
            <Suspense fallback={<FullPageLoader message="Loading dashboard..." />}>
              <Dashboard />
            </Suspense>
          }
        />
      </Route>
      <Route
        path="*"
        element={<Navigate to={isAuthenticated ? APP_ROUTES.dashboard : APP_ROUTES.login} replace />}
      />
    </Routes>
  )
}

function App() {
  return (
    <BrowserRouter>
      <ToastProvider>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </ToastProvider>
    </BrowserRouter>
  )
}

export default App
