import { useState, useEffect } from 'react';
import { useOrders } from '../hooks/useOrders';
import useDebounce from '../hooks/useDebounce';

const STATUS_LABELS = {
  0: 'Failed',
  1: 'Completed',
  2: 'Pending',
  3: 'In Progress',
};

const STATUS_STYLES = {
  0: 'bg-red-50 text-red-700 border-red-200',
  1: 'bg-green-50 text-green-700 border-green-200',
  2: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  3: 'bg-blue-50 text-blue-700 border-blue-200',
};

const FILTER_BAR = [
  { label: 'Date From', type: 'date', key: 'start_date' },
  { label: 'Date To', type: 'date', key: 'end_date' },
  { label: 'Min Amount', type: 'number', key: 'min_amount', placeholder: '0', min: 0, className: 'w-24' },
  { label: 'Max Amount', type: 'number', key: 'max_amount', placeholder: '∞', min: 0, className: 'w-24' },
  { label: 'Hour From', type: 'number', key: 'hour_from', placeholder: '0', min: 0, max: 23, className: 'w-20' },
  { label: 'Hour To', type: 'number', key: 'hour_to', placeholder: '23', min: 0, max: 23, className: 'w-20' },
  {
    label: 'Status',
    type: 'select',
    key: 'status',
    options: [
      { value: '', label: 'All Statuses' },
      ...Object.entries(STATUS_LABELS).map(([val, name]) => ({ value: val, label: name })),
    ],
    className: 'w-32',
  },
];

const COLUMNS = [
  { label: '#', sortKey: 'id' },
  { label: 'Restaurant', sortKey: 'restaurant' },
  { label: 'Amount', sortKey: 'amount' },
  { label: 'Status', sortKey: 'status' },
  { label: 'Date', sortKey: 'date' },
  { label: 'Hour', sortKey: 'hour' },
];

export default function OrdersTable({ lockedRestaurantId }) {
  const [filters, setFilters] = useState({
    restaurant_id: lockedRestaurantId ?? '',
    status: '',
    start_date: '',
    end_date: '',
    min_amount: '',
    max_amount: '',
    hour_from: '',
    hour_to: '',
    sort_by: 'date',
    sort_dir: 'desc',
  });

  const [page, setPage] = useState(1);
  const per_page = 15;

  const debouncedFilters = useDebounce(filters, 500);

  useEffect(() => {
    setPage(1);
  }, [debouncedFilters]);

  const { data, isLoading, isError } = useOrders({
    ...debouncedFilters,
    page,
    per_page,
  });

  const orders = data?.data ?? [];
  const meta = data?.meta ?? {};
  const total = meta.total ?? 0;
  const lastPage = meta.last_page ?? 1;

  function setFilter(key, value) {
    setFilters((prev) => ({ ...prev, [key]: value }));
  }

  function resetFilters() {
    setFilters({
      restaurant_id: lockedRestaurantId ?? '',
      status: '',
      start_date: '',
      end_date: '',
      min_amount: '',
      max_amount: '',
      hour_from: '',
      hour_to: '',
      sort_by: 'date',
      sort_dir: 'desc',
    });
    setPage(1);
  }

  function handleSort(key) {
    setFilters((prev) => {
      const isSameKey = prev.sort_by === key;
      const newDir = isSameKey && prev.sort_dir === 'desc' ? 'asc' : 'desc';
      return { ...prev, sort_by: key, sort_dir: newDir };
    });
  }

  return (
    <div className="bg-white rounded-xl border border-gray-200">
      {/* Filter bar */}
      <div className="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-end">
        {FILTER_BAR.map(({ label, type, key, placeholder, min, max, options, className = '' }) => (
          <div key={key}>
            <label className="block text-xs font-medium text-gray-600 mb-1">{label}</label>
            {type === 'select' ? (
              <select
                value={filters[key]}
                onChange={(e) => setFilter(key, e.target.value)}
                className={`${className} rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white`}
              >
                {options.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            ) : (
              <input
                type={type}
                min={min}
                max={max}
                value={filters[key]}
                placeholder={placeholder}
                onChange={(e) => setFilter(key, e.target.value)}
                className={`${className} rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent`}
              />
            )}
          </div>
        ))}

        <button
          onClick={resetFilters}
          className="text-xs text-gray-400 hover:text-gray-700 underline transition self-end pb-1.5"
        >
          Reset
        </button>
      </div>

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 bg-gray-50 text-left">
              {COLUMNS.map(({ label, sortKey }) => (
                <th
                  key={label}
                  onClick={() => handleSort(sortKey)}
                  className="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide cursor-pointer hover:bg-gray-100 transition whitespace-nowrap"
                >
                  <div className="flex items-center gap-1">
                    {label}
                    {filters.sort_by === sortKey && (
                      <span className="text-gray-900">
                        {filters.sort_dir === 'asc' ? '↑' : '↓'}
                      </span>
                    )}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50">
            {isLoading && (
              <tr>
                <td colSpan={6} className="text-center py-12 text-sm text-gray-400">
                  Loading…
                </td>
              </tr>
            )}
            {isError && (
              <tr>
                <td colSpan={6} className="text-center py-12 text-sm text-red-500">
                  Failed to load orders.
                </td>
              </tr>
            )}
            {!isLoading && !isError && orders.length === 0 && (
              <tr>
                <td colSpan={6} className="text-center py-12 text-sm text-gray-400">
                  No orders match your filters.
                </td>
              </tr>
            )}
            {orders.map((order) => {
              const dt = order.ordered_at ? new Date(order.ordered_at) : null;
              const hourDisplay = dt ? `${dt.getUTCHours()}:00` : '—';
              const dateDisplay = dt ? dt.toLocaleDateString('en-IN', { timeZone: 'UTC' }) : '—';

              return (
                <tr key={order.id} className="hover:bg-gray-50 transition">
                  <td className="px-4 py-3 text-gray-500 font-mono text-xs">{order.id}</td>
                  <td className="px-4 py-3 text-gray-800">{order.restaurant?.name ?? '—'}</td>
                  <td className="px-4 py-3 font-medium text-gray-900">
                    ₹
                    {Number(order.order_amount).toLocaleString('en-IN', {
                      maximumFractionDigits: 2,
                    })}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium
                      ${STATUS_STYLES[order.status] ?? 'bg-gray-50 text-gray-600 border-gray-200'}`}
                    >
                      {STATUS_LABELS[order.status] ?? `Status ${order.status}`}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-600 text-xs">{dateDisplay}</td>
                  <td className="px-4 py-3 text-gray-600">{hourDisplay}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {!isLoading && total > 0 && (
        <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
          <span className="text-xs text-gray-500">
            {meta.from}–{meta.to} of {total} order{total !== 1 ? 's' : ''}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="rounded px-3 py-1 text-xs border border-gray-200 text-gray-600
                hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
            >
              ← Prev
            </button>
            <span className="text-xs text-gray-500">
              Page {page} of {lastPage}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              disabled={page >= lastPage}
              className="rounded px-3 py-1 text-xs border border-gray-200 text-gray-600
                hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
            >
              Next →
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
