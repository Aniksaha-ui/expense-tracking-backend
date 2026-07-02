import { useEffect, useMemo, useRef, useState } from 'react'
import {
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  Columns3,
  Search,
} from 'lucide-react'
import useDebouncedValue from '../../hooks/useDebouncedValue'

const getDefaultVisibleColumnIds = (columns) =>
  columns.filter((column) => column.defaultHidden !== true).map((column) => column.id)

const getColumnMobileLabel = (column) => {
  if (typeof column.mobileLabel === 'string' && column.mobileLabel.trim()) {
    return column.mobileLabel
  }

  if (typeof column.label === 'string' || typeof column.label === 'number') {
    return String(column.label)
  }

  if (typeof column.id === 'string' && column.id.trim()) {
    return column.id.replace(/[_-]+/g, ' ')
  }

  return 'Value'
}

const isSerialLikeColumn = (column) => {
  const id = String(column?.id ?? '').trim().toLowerCase()
  const label = String(column?.label ?? '').trim().toLowerCase()

  return id === 'serial' || id === 'sl' || label === 'serial' || label === 'sl' || label === '#'
}

function SortIcon() {
  return <span aria-hidden="true"> ^</span>
}

function ColumnsDropdown({ columns, visibleColumnIds, onToggleColumn, onResetColumns }) {
  return (
    <div className="routes-columns-menu">
      <div className="routes-columns-menu__header">
        <span>Columns</span>
        <button type="button" onClick={onResetColumns}>
          Reset
        </button>
      </div>
      <div className="routes-columns-menu__body">
        {columns.map((column) => (
          <label key={column.id} className="routes-columns-menu__option">
            <input
              type="checkbox"
              checked={visibleColumnIds.includes(column.id)}
              onChange={() => onToggleColumn(column.id)}
            />
            <span>{column.label}</span>
          </label>
        ))}
      </div>
    </div>
  )
}

const getPaginationItems = (currentPage = 1, lastPage = 1) => {
  if (lastPage <= 1) {
    return [1]
  }

  const pages = new Set([1, lastPage, currentPage, currentPage - 1, currentPage + 1])

  if (currentPage <= 3) {
    pages.add(2)
    pages.add(3)
  }

  if (currentPage >= lastPage - 2) {
    pages.add(lastPage - 1)
    pages.add(lastPage - 2)
  }

  const orderedPages = [...pages]
    .filter((page) => page >= 1 && page <= lastPage)
    .sort((firstPage, secondPage) => firstPage - secondPage)

  return orderedPages.reduce((items, page, index) => {
    const previousPage = orderedPages[index - 1]

    if (previousPage && page - previousPage > 1) {
      items.push(`ellipsis-${previousPage}-${page}`)
    }

    items.push(page)
    return items
  }, [])
}

export function AdminTableButton({ children, count, variant = 'default', className = '', ...props }) {
  return (
    <button
      type="button"
      className={`routes-control ${variant === 'blue' ? 'routes-control--blue' : ''} ${className}`}
      {...props}
    >
      {children}
      {count ? <span className="routes-control__count">{count}</span> : null}
    </button>
  )
}

export function AdminTableSelectButton({ children }) {
  return (
    <AdminTableButton>
      {children}
      <ChevronDown size={13} />
    </AdminTableButton>
  )
}

export default function AdminDataTable({
  actions,
  columns,
  data,
  emptyMessage = 'No data found',
  filters,
  getRowKey = (row) => row.id,
  isLoading = false,
  onPageChange,
  onSearchChange,
  pagination,
  renderRowActions,
  resultLabel,
  rowActionsWidth = '88px',
  search,
  searchPlaceholder = 'Search',
}) {
  const columnsMenuRef = useRef(null)
  const defaultVisibleColumnIds = useMemo(() => getDefaultVisibleColumnIds(columns), [columns])
  const [visibleColumnIds, setVisibleColumnIds] = useState(defaultVisibleColumnIds)
  const [columnsOpen, setColumnsOpen] = useState(false)
  const [expandedRowKeys, setExpandedRowKeys] = useState({})
  const [searchInput, setSearchInput] = useState(search ?? '')
  const debouncedSearch = useDebouncedValue(searchInput)
  const visibleColumns = useMemo(
    () => columns.filter((column) => visibleColumnIds.includes(column.id)),
    [columns, visibleColumnIds],
  )
  const mobileSummaryColumn = useMemo(
    () => visibleColumns.find((column) => !isSerialLikeColumn(column)) ?? visibleColumns[0] ?? null,
    [visibleColumns],
  )
  const mobileDetailColumns = useMemo(
    () => visibleColumns.filter((column) => column.id !== mobileSummaryColumn?.id),
    [mobileSummaryColumn, visibleColumns],
  )
  const currentPage = pagination?.currentPage ?? 1
  const lastPage = pagination?.lastPage ?? 1
  const paginationItems = useMemo(() => getPaginationItems(currentPage, lastPage), [currentPage, lastPage])

  useEffect(() => {
    const handlePointerDown = (event) => {
      if (columnsMenuRef.current && !columnsMenuRef.current.contains(event.target)) {
        setColumnsOpen(false)
      }
    }

    document.addEventListener('pointerdown', handlePointerDown)

    return () => document.removeEventListener('pointerdown', handlePointerDown)
  }, [])

  useEffect(() => {
    setSearchInput(search ?? '')
  }, [search])

  useEffect(() => {
    if (debouncedSearch !== (search ?? '')) {
      onSearchChange?.(debouncedSearch)
    }
  }, [debouncedSearch, onSearchChange, search])

  useEffect(() => {
    setExpandedRowKeys({})
  }, [data, currentPage])

  const toggleColumn = (columnId) => {
    setVisibleColumnIds((currentColumnIds) => {
      if (currentColumnIds.includes(columnId)) {
        if (currentColumnIds.length === 1) {
          return currentColumnIds
        }

        return currentColumnIds.filter((currentColumnId) => currentColumnId !== columnId)
      }

      return columns
        .map((column) => column.id)
        .filter((defaultColumnId) => defaultColumnId === columnId || currentColumnIds.includes(defaultColumnId))
    })
  }

  const toggleRowExpansion = (rowKey) => {
    setExpandedRowKeys((currentRows) => ({
      ...currentRows,
      [rowKey]: !currentRows[rowKey],
    }))
  }

  return (
    <section className="routes-table-card">
      <div className="routes-table-toolbar">
        <label className="routes-search">
          <Search size={15} />
          <input
            type="search"
            placeholder={searchPlaceholder}
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
          />
        </label>

        <div className="routes-columns" ref={columnsMenuRef}>
          <button type="button" className="routes-control" onClick={() => setColumnsOpen((open) => !open)}>
            <Columns3 size={14} />
            Columns
            <span className="routes-control__count">{visibleColumnIds.length}</span>
          </button>
          {columnsOpen ? (
            <ColumnsDropdown
              columns={columns}
              visibleColumnIds={visibleColumnIds}
              onToggleColumn={toggleColumn}
              onResetColumns={() => setVisibleColumnIds(defaultVisibleColumnIds)}
            />
          ) : null}
        </div>

        {filters}
        {actions}
      </div>

      <div className="routes-mobile-list md:hidden">
        {isLoading ? (
          <div className="routes-mobile-card routes-mobile-card--empty">Loading records...</div>
        ) : data.length ? (
          data.map((row) => {
            const rowKey = getRowKey(row)
            const isExpanded = expandedRowKeys[rowKey] === true

            return (
              <article key={rowKey} className="routes-mobile-card">
                <button
                  type="button"
                  className="routes-mobile-card__button"
                  onClick={() => toggleRowExpansion(rowKey)}
                  aria-expanded={isExpanded}
                >
                  <div className="routes-mobile-card__summary">
                    {mobileSummaryColumn ? (
                      <div className="routes-mobile-card__summary-main">
                        <span className="routes-mobile-card__label">{getColumnMobileLabel(mobileSummaryColumn)}</span>
                        <div className="routes-mobile-card__value">
                          {mobileSummaryColumn.render
                            ? mobileSummaryColumn.render(row)
                            : row[mobileSummaryColumn.accessor ?? mobileSummaryColumn.id]}
                        </div>
                      </div>
                    ) : null}
                  </div>
                  <span className="routes-mobile-card__toggle">
                    {isExpanded ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                  </span>
                </button>

                {isExpanded ? (
                  <div className="routes-mobile-card__details">
                    {mobileDetailColumns.map((column) => (
                      <div key={column.id} className="routes-mobile-card__detail-row">
                        <span className="routes-mobile-card__label">{getColumnMobileLabel(column)}</span>
                        <div className="routes-mobile-card__detail-value">
                          {column.render ? column.render(row) : row[column.accessor ?? column.id]}
                        </div>
                      </div>
                    ))}
                    {renderRowActions ? (
                      <div className="routes-mobile-card__detail-row">
                        <span className="routes-mobile-card__label">Actions</span>
                        <div className="routes-mobile-card__actions">{renderRowActions(row)}</div>
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </article>
            )
          })
        ) : (
          <div className="routes-mobile-card routes-mobile-card--empty">{emptyMessage}</div>
        )}
      </div>

      <table className="routes-table hidden md:table">
        <thead>
          <tr>
            {visibleColumns.map((column) => (
              <th key={column.id} style={{ width: column.width, textAlign: column.align }}>
                {column.label} {column.sortable === false ? null : <SortIcon />}
              </th>
            ))}
            {renderRowActions ? (
              <th className="routes-table__actions-heading" style={{ width: rowActionsWidth, textAlign: 'right' }}>
                Actions
              </th>
            ) : null}
          </tr>
        </thead>
        <tbody>
          {isLoading ? (
            <tr>
              <td colSpan={visibleColumns.length + (renderRowActions ? 1 : 0)} className="routes-table__empty">
                Loading records...
              </td>
            </tr>
          ) : data.length ? (
            data.map((row) => (
              <tr key={getRowKey(row)}>
                {visibleColumns.map((column) => (
                  <td
                    key={column.id}
                    className={column.className}
                    data-label={getColumnMobileLabel(column)}
                    style={{ textAlign: column.align }}
                  >
                    {column.render ? column.render(row) : row[column.accessor ?? column.id]}
                  </td>
                ))}
                {renderRowActions ? (
                  <td className="routes-table__actions-cell" data-label="Actions">
                    {renderRowActions(row)}
                  </td>
                ) : null}
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={visibleColumns.length + (renderRowActions ? 1 : 0)} className="routes-table__empty">
                {emptyMessage}
              </td>
            </tr>
          )}
        </tbody>
      </table>

      <div className="routes-table-footer">
        <p>{resultLabel ?? `Showing ${data.length} result's`}</p>
        <div className="routes-pagination">
          <button
            type="button"
            disabled={isLoading || !pagination || currentPage <= 1}
            onClick={() => onPageChange?.(currentPage - 1)}
          >
            <ChevronLeft size={14} />
            Previous
          </button>
          <div className="routes-pagination__pages">
            {paginationItems.map((item) =>
              typeof item === 'string' ? (
                <span key={item} className="routes-pagination__ellipsis">
                  ...
                </span>
              ) : (
                <button
                  key={item}
                  type="button"
                  className={item === currentPage ? 'is-active' : ''}
                  disabled={isLoading || item === currentPage}
                  onClick={() => onPageChange?.(item)}
                >
                  {item}
                </button>
              ),
            )}
          </div>
          <button
            type="button"
            disabled={isLoading || !pagination || currentPage >= lastPage}
            onClick={() => onPageChange?.(currentPage + 1)}
          >
            Next
            <ChevronRight size={14} />
          </button>
        </div>
      </div>
    </section>
  )
}
