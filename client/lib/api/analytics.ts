import api from './axios';

const basedUrl = 'api';
const version = 'v1';

export function fetchRestaurantAnalytics(restaurantId: string | number, startDate?: string, endDate?: string) {
  return api
    .get(`${basedUrl}/${version}/analytics/restaurant/${restaurantId}`, {
      params: { start_date: startDate, end_date: endDate },
    })
    .then((res) => res.data.data);
}

export function fetchTopRestaurants(startDate?: string, endDate?: string) {
  return api
    .get(`${basedUrl}/${version}/analytics/top-restaurants`, {
      params: { start_date: startDate, end_date: endDate },
    })
    .then((res) => res.data.data);
}

export function fetchOrders(filters: Record<string, any> = {}) {
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== undefined && v !== null && v !== '')
  );
  return api.get(`${basedUrl}/${version}/analytics/orders`, { params }).then((res) => ({
    data: res.data.data,
    meta: res.data.meta,
  }));
}
