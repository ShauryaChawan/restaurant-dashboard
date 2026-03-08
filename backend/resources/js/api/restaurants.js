import axios from './axios';

/**
 * Fetch paginated, filtered restaurant list.
 *
 * @param {Object} params - Query parameters
 * @param {string} [params.search]    - Global search across name, cuisine, location
 * @param {string} [params.cuisine]   - Filter by cuisine type
 * @param {string} [params.location]  - Filter by location
 * @param {number} [params.rating]    - Minimum rating filter
 * @param {string} [params.sort_by]   - Column to sort by
 * @param {string} [params.sort_dir]  - Sort direction: asc|desc
 * @param {number} [params.page]      - Page number
 * @param {number} [params.per_page]  - Items per page (max 50)
 */
export const fetchRestaurants = (params = {}) => {
  return axios.get('/api/v1/restaurants', { params });
};

/**
 * Fetch a single restaurant by ID.
 *
 * @param {number} id - Restaurant ID
 */
export const fetchRestaurant = (id) => {
  return axios.get(`/api/v1/restaurants/${id}`);
};
