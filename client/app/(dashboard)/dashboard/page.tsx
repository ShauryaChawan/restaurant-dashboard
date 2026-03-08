"use client";

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTopRestaurants } from '@/hooks/useTopRestaurants';

function defaultDates() {
  const end = new Date();
  const start = new Date();
  start.setDate(start.getDate() - 6);
  return {
    startDate: start.toISOString().split('T')[0],
    endDate: end.toISOString().split('T')[0],
  };
}

export default function Dashboard() {
  const router = useRouter();

  const [dates, setDates] = useState(defaultDates());
  const [dateInput, setDateInput] = useState(dates);

  const {
    data: topRestaurants,
    isLoading,
    isError,
  } = useTopRestaurants(dates.startDate, dates.endDate);

  return (
    <main className="max-w-5xl mx-auto px-6 py-10">
      <div className="mb-8 flex flex-col sm:flex-row sm:items-end gap-4">
        <div>
          <h2 className="text-xl font-semibold text-gray-900">Dashboard</h2>
          <p className="text-sm text-gray-500 mt-0.5">Top 3 restaurants by revenue</p>
        </div>
        <div className="sm:ml-auto flex flex-wrap items-end gap-3">
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input
              type="date"
              value={dateInput.startDate}
              onChange={(e) => setDateInput((p) => ({ ...p, startDate: e.target.value }))}
              className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none
                focus:ring-2 focus:ring-gray-900 focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input
              type="date"
              value={dateInput.endDate}
              onChange={(e) => setDateInput((p) => ({ ...p, endDate: e.target.value }))}
              className="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none
                focus:ring-2 focus:ring-gray-900 focus:border-transparent"
            />
          </div>
          <button
            onClick={() => setDates(dateInput)}
            className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white
              hover:bg-gray-700 transition"
          >
            Apply
          </button>
        </div>
      </div>

      {isLoading && (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              className="bg-white rounded-xl border border-gray-200 p-6 h-44 animate-pulse"
            />
          ))}
        </div>
      )}

      {isError && (
        <div className="rounded-xl bg-red-50 border border-red-200 px-6 py-4 text-sm text-red-700">
          Failed to load top restaurants. Please try again.
        </div>
      )}

      {!isLoading && !isError && topRestaurants?.length === 0 && (
        <div className="rounded-xl bg-gray-50 border border-gray-200 px-6 py-10 text-center text-sm text-gray-500">
          No orders found for the selected date range.
        </div>
      )}

      {!isLoading && !isError && topRestaurants?.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
          {topRestaurants.map((r: any, idx: number) => (
            <div
              key={r.id}
              onClick={() => router.push(`/restaurants/${r.id}/analytics`)}
              className="bg-white rounded-xl border border-gray-200 p-6 cursor-pointer
                hover:shadow-md hover:border-gray-300 transition group"
            >
              <div className="flex items-center justify-between mb-1">
                <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                  #{idx + 1}
                </span>
                <span className="text-xs text-gray-400">{r.cuisine}</span>
              </div>
              <h3 className="text-base font-semibold text-gray-900 group-hover:text-gray-700 mt-1 truncate">
                {r.name}
              </h3>
              <p className="text-xs text-gray-500 mb-4">{r.location}</p>
              <div className="border-t border-gray-100 pt-4 space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Revenue</span>
                  <span className="font-semibold text-gray-900">
                    ₹
                    {Number(r.total_revenue).toLocaleString('en-IN', {
                      maximumFractionDigits: 0,
                    })}
                  </span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Orders</span>
                  <span className="font-medium text-gray-700">{r.total_orders}</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="mt-10 text-center">
        <button
          onClick={() => router.push('/restaurants')}
          className="text-sm text-gray-400 hover:text-gray-700 underline transition"
        >
          View all restaurants →
        </button>
      </div>
    </main>
  );
}
