"use client";

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useRestaurants } from '@/hooks/useRestaurants';

export default function RestaurantList() {
  const router = useRouter();

  const [search, setSearch] = useState('');
  const [cuisine, setCuisine] = useState('');
  const [location, setLocation] = useState('');
  const [rating, setRating] = useState('');
  const [sortBy, setSortBy] = useState('name');
  const [sortDir, setSortDir] = useState('asc');
  const [page, setPage] = useState(1);

  const params = {
    ...(search && { search }),
    ...(cuisine && { cuisine }),
    ...(location && { location }),
    ...(rating && { rating }),
    sort_by: sortBy,
    sort_dir: sortDir,
    page,
    per_page: 10,
  };

  const { data, isLoading, isError, error } = useRestaurants(params);

  const restaurants = data?.data ?? [];
  const lastPage = data?.meta?.last_page ?? 1;
  const total = data?.meta?.total ?? 0;

  const handleSort = (column: string) => {
    if (sortBy === column) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortBy(column);
      setSortDir('asc');
    }
    setPage(1);
  };

  const handleFilterChange =
    (setter: React.Dispatch<React.SetStateAction<string>>) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
      setter(e.target.value);
      setPage(1);
    };

  const handleRowClick = (restaurantId: string | number) => {
    router.push(`/restaurants/${restaurantId}/analytics`);
  };

  const sortIndicator = (column: string) => {
    if (sortBy !== column) return null;
    return sortDir === 'asc' ? ' ↑' : ' ↓';
  };

  return (
    <main className="px-6 py-10">
      <h1 className="text-2xl font-semibold text-gray-900 mb-6">Restaurants</h1>

      <div className="flex gap-3 mb-6 flex-wrap">
        <input
          type="text"
          placeholder="Search name, cuisine, location..."
          value={search}
          onChange={handleFilterChange(setSearch)}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent flex-1 min-w-64"
        />

        <select
          value={cuisine}
          onChange={handleFilterChange(setCuisine)}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
        >
          <option value="">All Cuisines</option>
          <option value="North Indian">North Indian</option>
          <option value="Japanese">Japanese</option>
          <option value="Italian">Italian</option>
          <option value="American">American</option>
        </select>

        <select
          value={location}
          onChange={handleFilterChange(setLocation)}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
        >
          <option value="">All Locations</option>
          <option value="Bangalore">Bangalore</option>
          <option value="Mumbai">Mumbai</option>
          <option value="Delhi">Delhi</option>
          <option value="Hyderabad">Hyderabad</option>
        </select>

        <select
          value={rating}
          onChange={handleFilterChange(setRating)}
          className="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
        >
          <option value="">All Ratings</option>
          <option value="4">4+ Stars</option>
          <option value="3">3+ Stars</option>
          <option value="2">2+ Stars</option>
        </select>

        <button
          onClick={() => {
            setSearch('');
            setCuisine('');
            setLocation('');
            setRating('');
            setSortBy('name');
            setSortDir('asc');
            setPage(1);
          }}
          className="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 underline"
        >
          Clear Filters
        </button>
      </div>

      {isLoading && <p>Loading restaurants...</p>}

      {isError && (
        <p className="text-red-500">
          Error: {(error as any)?.response?.data?.message ?? 'Failed to load restaurants.'}
        </p>
      )}

      {!isLoading && !isError && (
        <>
          <p className="mb-2 text-gray-500 text-sm">
            {total} restaurant{total !== 1 ? 's' : ''} found
          </p>

          <div className="overflow-x-auto bg-white border border-gray-200 rounded-lg">
            <table className="w-full text-left border-collapse text-sm">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-200">
                  {[
                    { key: 'name', label: 'Name' },
                    { key: 'cuisine', label: 'Cuisine' },
                    { key: 'location', label: 'Location' },
                    { key: 'rating', label: 'Rating' },
                  ].map(({ key, label }) => (
                    <th
                      key={key}
                      onClick={() => handleSort(key)}
                      className="px-4 py-3 cursor-pointer select-none text-gray-500 font-medium"
                    >
                      {label}
                      {sortIndicator(key)}
                    </th>
                  ))}
                </tr>
              </thead>

              <tbody>
                {restaurants.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="p-6 text-center text-gray-400">
                      No restaurants found.
                    </td>
                  </tr>
                ) : (
                  restaurants.map((restaurant: any) => (
                    <tr
                      key={restaurant.id}
                      onClick={() => handleRowClick(restaurant.id)}
                      className="border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition"
                    >
                      <td className="px-4 py-3 font-medium text-gray-900">{restaurant.name}</td>
                      <td className="px-4 py-3 text-gray-600">{restaurant.cuisine}</td>
                      <td className="px-4 py-3 text-gray-600">{restaurant.location}</td>
                      <td className="px-4 py-3 text-gray-600">
                        {restaurant.rating ? `${restaurant.rating} ⭐` : '—'}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          <div className="flex items-center gap-4 mt-6">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              className="px-4 py-2 border border-gray-200 rounded text-sm disabled:opacity-50 hover:bg-gray-50"
            >
              Previous
            </button>

            <span className="text-sm text-gray-600">
              Page {page} of {lastPage}
            </span>

            <button
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              disabled={page === lastPage}
              className="px-4 py-2 border border-gray-200 rounded text-sm disabled:opacity-50 hover:bg-gray-50"
            >
              Next
            </button>
          </div>
        </>
      )}
    </main>
  );
}
