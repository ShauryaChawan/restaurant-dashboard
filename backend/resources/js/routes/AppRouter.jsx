import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import PrivateRoute from './PrivateRoute';
import Layout from './Layout';
import Login from '../pages/auth/Login';
import Register from '../pages/auth/Register';
import RestaurantList from '../pages/restaurants/RestaurantList';
import Dashboard from '../pages/dashboard/Dashboard';
import RestaurantAnalytics from '../pages/analytics/RestaurantAnalytics';

/**
 * AppRouter
 *
 * Defines all client-side routes.
 * Protected routes are wrapped in PrivateRoute.
 * Phase 5 will add /restaurants/:id/analytics.
 */
export default function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />

        {/* Protected routes with layout */}
        <Route element={<PrivateRoute />}>
          <Route element={<Layout />}>
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/restaurants" element={<RestaurantList />} />
            <Route path="/restaurants/:id/analytics" element={<RestaurantAnalytics />} />
          </Route>
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
