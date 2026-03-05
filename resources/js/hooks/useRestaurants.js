import { useQuery } from '@tanstack/react-query';
import { fetchRestaurants } from '../api/restaurants';

/**
 * TanStack Query hook for fetching paginated restaurant list.
 *
 * Automatically refetches when filters change.
 * Caches results client-side for staleTime duration.
 *
 * @param {Object} params - Filter/pagination parameters
 */
export const useRestaurants = (params = {}) => {
  return useQuery({
    queryKey: ['restaurants', params], // refetches when params change
    queryFn: () =>
      fetchRestaurants(params).then((res) => ({
        data: res.data.data, // array of restaurants
        meta: res.data.meta, // pagination info
      })),
    keepPreviousData: true, // shows old data while new page loads
  });
};
