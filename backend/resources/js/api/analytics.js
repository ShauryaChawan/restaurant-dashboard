import api from './axios';

const basedUrl = 'api';
const version = 'v1';

export function fetchRestaurantAnalytics(restaurantId, startDate, endDate) {
  return api
    .get(`${basedUrl}/${version}/analytics/restaurant/${restaurantId}`, {
      params: { start_date: startDate, end_date: endDate },
    })
    .then((res) => res.data.data);
}

export function fetchTopRestaurants(startDate, endDate) {
  return api
    .get(`${basedUrl}/${version}/analytics/top-restaurants`, {
      params: { start_date: startDate, end_date: endDate },
    })
    .then((res) => res.data.data);
}

export function fetchOrders(filters = {}) {
  // Strip undefined/null/empty string values so they don't appear as blank query params
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== undefined && v !== null && v !== '')
  );
  return api.get(`${basedUrl}/${version}/analytics/orders`, { params }).then((res) => ({
    data: res.data.data,
    meta: res.data.meta,
  }));
}
