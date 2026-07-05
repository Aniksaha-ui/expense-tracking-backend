import { useEffect, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { useAuthContext } from '../contexts/AuthContext'
import { getSupportedRoute, hasChildren } from '../features/menu/utils/menuHelpers'
import { Footer } from './Footer'
import { Navbar } from './Navbar'
import { Sidebar } from './Sidebar'

const SIDEBAR_COLLAPSE_STORAGE_KEY = 'travel-agency-admin-sidebar-collapsed'

const pageTitles = {
  '/admin/account/balance': 'Account Balance Report',
  '/admin/account/history': 'Account History Report',
  '/admin/accounts': 'Account Management',
  '/accounts': 'Account Management',
  '/admin/categories': 'Category Management',
  '/categories': 'Category Management',
  '/admin/account/daily-balance': 'Monthly Daily Balance',
  '/admin/customerValueReport': 'Customer Value Report',
  '/admin/financialReport': 'Financial Report',
  '/admin/account/overall-sales': 'Overall Sales Report',
  '/admin/account/route-wise-sales': 'Route Wise Sales Report',
  '/admin/account/ticket-status-report': 'Ticket Status Analysis',
  '/admin/high-cancellation-packages': 'High Cancellation Packages',
  '/admin/hotel': 'Hotel Information',
  '/admin/low-occupancy-report': 'Low Occupancy Report',
  '/admin/menu-items': 'Menu Management',
  '/admin/monthRunningBalance': 'Monthly Running Balance',
  '/admin/online-payment-configure': 'Online Payment Configure',
  '/admin/packages': 'Package Management',
  '/admin/recurring-expenses': 'Recurring Expense Management',
  '/recurring-expenses': 'Recurring Expense Management',
  '/reports': 'Reports Center',
  '/reports/account-balances': 'Account Balances Report',
  '/reports/burn-rate-analysis': 'Burn Rate Analysis',
  '/reports/category-breakdown': 'Category Breakdown Report',
  '/reports/category-usage-analysis': 'Category Usage Analysis',
  '/reports/current-vs-previous-month-analysis': 'Current vs Previous Month Analysis',
  '/reports/daywise-expenses': 'Daywise Expense Report',
  '/reports/weekly-current-month-analysis': 'Weekly Current Month Analysis',
  '/reports/summary': 'Summary Report',
  '/admin/refunds': 'Refund Management',
  '/admin/transfers': 'Transfer Management',
  '/transfers': 'Transfer Management',
  '/admin/transactions': 'Transactions',
  '/transactions': 'Transactions',
  '/admin/tickets': 'Ticket Management',
  '/admin/tripPerformance': 'Trip Performance',
  '/admin/users': 'User Management',
  '/admin/visa/applications': 'Visa Applications',
  '/admin/visa/countries': 'Visa Country Information',
  '/admin/visa/types': 'Visa Types Information',
  '/admin/vehicletrackingreport': 'Vehicle Tracking Report',
  '/admin/vehiclewiseseatreport': 'Vehicle Wise Seat Report',
  '/dashboard': 'Dashboard',
  '/routes': 'Routes',
  '/seats': 'Seat Management',
  '/trips': 'Trip Management',
  '/vehicles': 'Vehicles',
}

export function AppLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [sidebarCollapsed, setSidebarCollapsed] = useState(getPersistedSidebarCollapsed)
  const location = useLocation()
  const { menu } = useAuthContext()
  const supportedPath = getSupportedRoute(location.pathname) ?? location.pathname
  const title =
    findMenuTitle([...menu.mainMenuItems, ...menu.bottomMenuItems], supportedPath) ??
    pageTitles[location.pathname] ??
    pageTitles[supportedPath] ??
    'Dashboard'

  useEffect(() => {
    window.localStorage.setItem(SIDEBAR_COLLAPSE_STORAGE_KEY, JSON.stringify(sidebarCollapsed))
  }, [sidebarCollapsed])

  useEffect(() => {
    setSidebarOpen(false)
  }, [location.pathname])

  const handleSidebarToggle = () => {
    if (window.matchMedia('(max-width: 767px)').matches) {
      setSidebarOpen((currentValue) => !currentValue)
      return
    }

    setSidebarCollapsed((currentValue) => !currentValue)
  }

  return (
    <div className="flex min-h-dvh bg-[#100d0e] text-slate-200">
      <Sidebar
        isCollapsed={sidebarCollapsed}
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        onExpand={() => setSidebarCollapsed(false)}
      />
      <main className="flex min-w-0 flex-1 flex-col">
        <Navbar
          isSidebarCollapsed={sidebarCollapsed}
          title={title}
          onMenuClick={handleSidebarToggle}
        />
        <div className="flex-1 overflow-y-auto bg-[#100d0e]">
          <Outlet />
        </div>
        <Footer />
      </main>
    </div>
  )
}

function findMenuTitle(items, pathname) {
  for (const item of items) {
    if (getSupportedRoute(item.path) === pathname) {
      return item.title
    }

    if (hasChildren(item)) {
      const childTitle = findMenuTitle(item.children, pathname)

      if (childTitle) {
        return childTitle
      }
    }
  }

  return null
}

function getPersistedSidebarCollapsed() {
  const rawValue = window.localStorage.getItem(SIDEBAR_COLLAPSE_STORAGE_KEY)

  if (!rawValue) {
    return false
  }

  try {
    return JSON.parse(rawValue) === true
  } catch {
    return false
  }
}
