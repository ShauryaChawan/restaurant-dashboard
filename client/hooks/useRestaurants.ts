import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { fetchRestaurants } from '@/lib/api/restaurants';

export const useRestaurants = (params: Record<string, any> = {}) => {
  return useQuery({
    queryKey: ['restaurants', params],
    queryFn: () =>
      fetchRestaurants(params).then((res) => ({
        data: res.data.data,
        meta: res.data.meta,
      })),
    placeholderData: keepPreviousData,
  });
}
