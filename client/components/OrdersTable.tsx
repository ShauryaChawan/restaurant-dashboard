"use client";

import { useState } from 'react';
import { useOrders } from '@/hooks/useOrders';

const STATUS_LABELS: Record<number, string> = {
  0: 'Failed',
  1: 'Completed',
  2: 'Pending',
  3: 'In Progress',
};

const STATUS_STYLES: Record<number, string> = {
  0: 'bg-red-50 text-red-700 border-red-200',
  1: 'bg-green-50 text-green-700 border-green-200',
  2: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  3: 'bg-blue-50 text-blue-700 border-blue-200',
};

interface OrdersTableProps {
  lockedRestaurantId?: string | number | null;
}

export default function OrdersTable({ lockedRestaurantId }: OrdersTableProps) {
  const [filters, setFilters] = useState<Record<string, any>>({
    restaurant_id: lockedRestaurantId ?? '',
    start_date: '',
    end_date: '',
    min_amount: '',
    max_amount: '',
    hour_from: '',
    hour_to: '',
    page: 1,
    per_page: 15,
  });

  const { data, isLoading, isError } = useOrders(filters);

  const orders = data?.data ?? [];
  const meta = data?.meta ?? {};
  const total = meta.total ?? 0;
  const lastPage = meta.last_page ?? 1;

  function setFilter(key: string, value: string | number) {
    setFilters((prev) => ({ ...prev, [key]: value, page: 1 }));
  }

  function setPage(page: number) {
    setFilters((prev) => ({ ...prev, page }));
  }

  function resetFilters() {
    setFilters({
      restaurant_id: lockedRestaurantId ?? '',
      start_date: '',
      end_date: '',
      min_amount: '',
      max_amount: '',
      hour_from: '',
      hour_to: '',
      page: 1,
      per_page: 15,
    });
  }

  return (
    <div className="bg-white rounded-xl border border-gray-200">
      {/* Filter bar */}
      <div className="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Date From</label>
          <input
            type="date"
            value={filters.start_date}
            onChange={(e) => setFilter('start_date', e.target.value)}
            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Date To</label>
          <input
            type="date"
            value={filters.end_date}
            onChange={(e) => setFilter('end_date', e.target.value)}
            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Min Amount</label>
          <input
            type="number"
            min={0}
            value={filters.min_amount}
            placeholder="0"
            onChange={(e) => setFilter('min_amount', e.target.value)}
            className="w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Max Amount</label>
          <input
            type="number"
            min={0}
            value={filters.max_amount}
            placeholder="∞"
            onChange={(e) => setFilter('max_amount', e.target.value)}
            className="w-24 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Hour From</label>
          <input
            type="number"
            min={0}
            max={23}
            value={filters.hour_from}
            placeholder="0"
            onChange={(e) => setFilter('hour_from', e.target.value)}
            className="w-20 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Hour To</label>
          <input
            type="number"
            min={0}
            max={23}
            value={filters.hour_to}
            placeholder="23"
            onChange={(e) => setFilter('hour_to', e.target.value)}
            className="w-20 rounded-lg border border-gray-300 px-3 py-1.5 text-sm outline-none
              focus:ring-2 focus:ring-gray-900 focus:border-transparent"
          />
        </div>

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
              {['#', 'Restaurant', 'Amount', 'Status', 'Date', 'Hour'].map((h) => (
                <th
                  key={h}
                  className="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"
                >
                  {h}
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
            {orders.map((order: any) => {
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
              onClick={() => setPage(filters.page - 1)}
              disabled={filters.page <= 1}
              className="rounded px-3 py-1 text-xs border border-gray-200 text-gray-600
                hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
            >
              ← Prev
            </button>
            <span className="text-xs text-gray-500">
              Page {filters.page} of {lastPage}
            </span>
            <button
              onClick={() => setPage(filters.page + 1)}
              disabled={filters.page >= lastPage}
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
