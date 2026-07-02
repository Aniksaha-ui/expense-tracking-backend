export const REPORT_ACCOUNT_TYPE_OPTIONS = [
  { label: 'All Accounts', value: 'all' },
  { label: 'Cash', value: 'CASH' },
  { label: 'Bank', value: 'BANK' },
  { label: 'Card', value: 'CARD' },
  { label: 'Mobile Banking', value: 'MOBILE_BANKING' },
]

export const REPORT_CATEGORY_TYPE_OPTIONS = [
  { label: 'All Categories', value: 'all' },
  { label: 'Expense', value: 'EXPENSE' },
  { label: 'Income', value: 'INCOME' },
]

export const REPORTS_PAGE_COPY = {
  accountBalances: {
    searchPlaceholder: 'Search by account name, institution, type, status, or account ID',
    subtitle: 'Review current balances across cash, bank, card, and mobile banking accounts in one place.',
    title: 'Account Balances Report',
  },
  categoryBreakdown: {
    searchPlaceholder: 'Search by category name, type, or amount',
    subtitle: 'See which categories drive the most expense volume across the selected date range.',
    title: 'Category Breakdown Report',
  },
  categoryUsageAnalysis: {
    searchPlaceholder: 'Search by category, usage count, amount, or share',
    subtitle: 'Measure how often each expense category is used and how much money each one has consumed.',
    title: 'Category Usage Analysis',
  },
  currentVsPreviousMonthAnalysis: {
    searchPlaceholder: 'Search by month label, range, income, expense, recurring, or net',
    subtitle: 'Compare the current month against the previous month across income, expense, recurring bills, and net movement.',
    title: 'Current vs Previous Month Analysis',
  },
  daywiseExpenses: {
    searchPlaceholder: 'Search by day, category, type, amount, or transaction count',
    subtitle: 'Track expense totals day by day and see which categories contributed to each reporting date.',
    title: 'Daywise Expense Report',
  },
  weeklyCurrentMonthAnalysis: {
    searchPlaceholder: 'Search by week label, range, amount, or transaction count',
    subtitle: 'Analyze how expense spending is distributed week by week across the current calendar month.',
    title: 'Weekly Current Month Analysis',
  },
  hub: {
    subtitle: 'Choose a report to inspect balances, movement, and category performance from one place.',
    title: 'Reports',
  },
  summary: {
    subtitle: 'Get a quick snapshot of balances and transaction movement for the selected reporting range.',
    title: 'Summary Report',
  },
}
