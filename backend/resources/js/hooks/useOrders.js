import { useQuery } from '@tanstack/react-query';
import { fetchOrders } from '../api/analytics';

export function useOrders(filters) {
  return useQuery({
    queryKey: ['orders', filters],
    queryFn: () => fetchOrders(filters),
    placeholderData: (prev) => prev,
    staleTime: 1000 * 60 * 2,
  });
}
