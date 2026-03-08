import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { fetchOrders } from '@/lib/api/analytics';

export function useOrders(filters: Record<string, any>) {
  return useQuery({
    queryKey: ['orders', filters],
    queryFn: () => fetchOrders(filters),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60 * 2,
  });
}
