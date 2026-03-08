"use client";

import { useState, use } from 'react';
import { useRouter } from 'next/navigation';
import { useRestaurantAnalytics } from '@/hooks/useRestaurantAnalytics';
import DailyOrdersChart from '@/components/charts/DailyOrdersChart';
import DailyRevenueChart from '@/components/charts/DailyRevenueChart';
import AovCard from '@/components/charts/AovCard';
import PeakHourChart from '@/components/charts/PeakHourChart';
import OrdersTable from '@/components/OrdersTable';

function defaultDates() {
  const end = new Date();
  const start = new Date();
  start.setDate(start.getDate() - 6);
  return {
    startDate: start.toISOString().split('T')[0],
    endDate: end.toISOString().split('T')[0],
  };
}

export default function RestaurantAnalytics({ params }: { params: Promise<{ id: string }> }) {
  const router = useRouter();
  const { id } = use(params);

  const [dates, setDates] = useState(defaultDates());
  const [dateInput, setDateInput] = useState(dates);

  const { data, isLoading, isError } = useRestaurantAnalytics(
    Number(id),
    dates.startDate,
    dates.endDate
  );

  if (isError) {
    return (
      <div className="min-h-[calc(100vh-80px)] bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-red-600 font-medium mb-2">Restaurant not found or failed to load.</p>
          <button
            onClick={() => router.push('/restaurants')}
            className="text-sm text-gray-500 underline"
          >
            ← Back to restaurants
          </button>
        </div>
      </div>
    );
  }

  const restaurant = data?.restaurant;

  return (
    <main className="max-w-6xl mx-auto px-6 py-10 border-t border-transparent">
      {/* Breadcrumb nav */}
      <div className="mb-6 flex items-center gap-3">
        <button
          onClick={() => router.push('/restaurants')}
          className="text-sm text-gray-500 hover:text-gray-900 transition"
        >
          ← Restaurants
        </button>
        <span className="text-gray-300">/</span>
        <span className="text-sm font-medium text-gray-900">
          {isLoading ? '…' : restaurant?.name}
        </span>
      </div>

      {/* Header + date filter */}
      <div className="mb-8 flex flex-col sm:flex-row sm:items-end gap-4">
        <div>
          {isLoading ? (
            <div className="h-6 w-48 bg-gray-200 rounded animate-pulse" />
          ) : (
            <>
              <h2 className="text-xl font-semibold text-gray-900">{restaurant?.name}</h2>
              <p className="text-sm text-gray-500 mt-0.5">
                {restaurant?.cuisine} · {restaurant?.location}
                {restaurant?.rating ? ` · ⭐ ${restaurant.rating}` : ''}
              </p>
            </>
          )}
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

      {/* Loading skeleton */}
      {isLoading && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
          {[1, 2, 3, 4].map((i) => (
            <div
              key={i}
              className="bg-white rounded-xl border border-gray-200 p-5 h-56 animate-pulse"
            />
          ))}
        </div>
      )}

      {/* Charts + orders table */}
      {!isLoading && data && (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
            <DailyOrdersChart data={data.daily_orders} />
            <DailyRevenueChart data={data.daily_revenue} />
            <AovCard value={data.avg_order_value} />
            <PeakHourChart data={data.peak_hours} />
          </div>

          <div>
            <h3 className="text-base font-semibold text-gray-900 mb-3">Orders</h3>
            <OrdersTable lockedRestaurantId={Number(id)} />
          </div>
        </>
      )}
    </main>
  );
}
