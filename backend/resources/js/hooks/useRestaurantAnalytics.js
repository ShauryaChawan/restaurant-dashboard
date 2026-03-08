import { useQuery } from '@tanstack/react-query';
import { fetchRestaurantAnalytics } from '../api/analytics';

export function useRestaurantAnalytics(restaurantId, startDate, endDate) {
  return useQuery({
    queryKey: ['analytics', 'restaurant', restaurantId, startDate, endDate],
    queryFn: () => fetchRestaurantAnalytics(restaurantId, startDate, endDate),
    enabled: Boolean(restaurantId && startDate && endDate),
    staleTime: 1000 * 60 * 5,
  });
}
