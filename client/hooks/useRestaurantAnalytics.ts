import { useQuery } from '@tanstack/react-query';
import { fetchRestaurantAnalytics } from '@/lib/api/analytics';

export function useRestaurantAnalytics(restaurantId: string | number, startDate?: string, endDate?: string) {
  return useQuery({
    queryKey: ['analytics', 'restaurant', restaurantId, startDate, endDate],
    queryFn: () => fetchRestaurantAnalytics(restaurantId, startDate, endDate),
    enabled: Boolean(restaurantId && startDate && endDate),
    staleTime: 1000 * 60 * 5,
  });
}
