import { useQuery } from '@tanstack/react-query';
import { fetchTopRestaurants } from '@/lib/api/analytics';

export function useTopRestaurants(startDate?: string, endDate?: string) {
  return useQuery({
    queryKey: ['analytics', 'top-restaurants', startDate, endDate],
    queryFn: () => fetchTopRestaurants(startDate, endDate),
    enabled: Boolean(startDate && endDate),
    staleTime: 1000 * 60 * 5,
  });
}
