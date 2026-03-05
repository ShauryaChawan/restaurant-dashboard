import axios from 'axios';

const instance = axios.create({
  baseURL: import.meta.env.VITE_APP_URL || 'http://restaurant-dashboard.test',
  withCredentials: true, // required for Sanctum cookie auth
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Attach Bearer token from localStorage on every request
instance.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Global response error handler
instance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid — clear storage and redirect to login
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default instance;
