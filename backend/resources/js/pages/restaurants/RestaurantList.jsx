import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useRestaurants } from '../../hooks/useRestaurants';

/**
 * RestaurantList Page
 *
 * Displays a paginated, searchable, filterable, sortable table of restaurants.
 * Uses TanStack Query via useRestaurants hook for all data fetching.
 * Navigates to analytics page on row click.
 */
export default function RestaurantList() {
  const navigate = useNavigate();

  // ── Filter State ───────────────────────────────────────────────────
  const [search, setSearch] = useState('');
  const [cuisine, setCuisine] = useState('');
  const [location, setLocation] = useState('');
  const [rating, setRating] = useState('');
  const [sortBy, setSortBy] = useState('name');
  const [sortDir, setSortDir] = useState('asc');
  const [page, setPage] = useState(1);

  // ── Query Params ───────────────────────────────────────────────────
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

  // ── Data Fetching ──────────────────────────────────────────────────
  const { data, isLoading, isError, error } = useRestaurants(params);

  const restaurants = data?.data ?? [];
  const lastPage = data?.meta?.last_page ?? 1;
  const total = data?.meta?.total ?? 0;

  // ── Handlers ───────────────────────────────────────────────────────
  const handleSort = (column) => {
    if (sortBy === column) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortBy(column);
      setSortDir('asc');
    }
    setPage(1);
  };

  const handleFilterChange = (setter) => (e) => {
    setter(e.target.value);
    setPage(1); // reset to page 1 on any filter change
  };

  const handleRowClick = (restaurantId) => {
    navigate(`/restaurants/${restaurantId}/analytics`);
  };

  // ── Sort Indicator ─────────────────────────────────────────────────
  const sortIndicator = (column) => {
    if (sortBy !== column) return null;
    return sortDir === 'asc' ? ' ↑' : ' ↓';
  };

  // ── Render ─────────────────────────────────────────────────────────
  return (
    <main className="px-6 py-10">
      <h1 className="text-2xl font-semibold text-gray-900 mb-6">Restaurants</h1>

      {/* ── Search & Filters ── */}
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

        <select value={rating} onChange={handleFilterChange(setRating)} style={{ padding: '8px' }}>
          <option value="">All Ratings</option>
          <option value="4">4+ Stars</option>
          <option value="3">3+ Stars</option>
          <option value="2">2+ Stars</option>
        </select>

        {/* Clear all filters */}
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
          style={{ padding: '8px 16px' }}
        >
          Clear Filters
        </button>
      </div>

      {/* ── States ── */}
      {isLoading && <p>Loading restaurants...</p>}

      {isError && (
        <p style={{ color: 'red' }}>
          Error: {error?.response?.data?.message ?? 'Failed to load restaurants.'}
        </p>
      )}

      {/* ── Table ── */}
      {!isLoading && !isError && (
        <>
          <p style={{ marginBottom: '8px', color: '#666' }}>
            {total} restaurant{total !== 1 ? 's' : ''} found
          </p>

          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ background: '#f5f5f5', textAlign: 'left' }}>
                {[
                  { key: 'name', label: 'Name' },
                  { key: 'cuisine', label: 'Cuisine' },
                  { key: 'location', label: 'Location' },
                  { key: 'rating', label: 'Rating' },
                ].map(({ key, label }) => (
                  <th
                    key={key}
                    onClick={() => handleSort(key)}
                    style={{ padding: '12px', cursor: 'pointer', userSelect: 'none' }}
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
                  <td colSpan={4} style={{ padding: '24px', textAlign: 'center', color: '#999' }}>
                    No restaurants found.
                  </td>
                </tr>
              ) : (
                restaurants.map((restaurant) => (
                  <tr
                    key={restaurant.id}
                    onClick={() => handleRowClick(restaurant.id)}
                    style={{ borderBottom: '1px solid #eee', cursor: 'pointer' }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = '#f9f9f9')}
                    onMouseLeave={(e) => (e.currentTarget.style.background = 'white')}
                  >
                    <td style={{ padding: '12px' }}>{restaurant.name}</td>
                    <td style={{ padding: '12px' }}>{restaurant.cuisine}</td>
                    <td style={{ padding: '12px' }}>{restaurant.location}</td>
                    <td style={{ padding: '12px' }}>
                      {restaurant.rating ? `${restaurant.rating} ⭐` : '—'}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>

          {/* ── Pagination ── */}
          <div style={{ display: 'flex', gap: '8px', marginTop: '16px', alignItems: 'center' }}>
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              style={{ padding: '8px 16px' }}
            >
              Previous
            </button>

            <span>
              Page {page} of {lastPage}
            </span>

            <button
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              disabled={page === lastPage}
              style={{ padding: '8px 16px' }}
            >
              Next
            </button>
          </div>
        </>
      )}
    </main>
  );
}
