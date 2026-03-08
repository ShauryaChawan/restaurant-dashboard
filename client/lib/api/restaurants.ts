import axios from './axios';

export const fetchRestaurants = (params = {}) => {
  return axios.get('/api/v1/restaurants', { params });
};

export const fetchRestaurant = (id: string | number) => {
  return axios.get(`/api/v1/restaurants/${id}`);
};
