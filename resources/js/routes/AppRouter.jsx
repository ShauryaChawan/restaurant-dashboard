import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import PrivateRoute from './PrivateRoute';
import Login from '../pages/auth/Login';
import Register from '../pages/auth/Register';
import RestaurantList from '../pages/restaurants/RestaurantList';

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

        {/* Protected routes */}
        <Route element={<PrivateRoute />}>
          <Route path="/" element={<Navigate to="/restaurants" replace />} />
          <Route path="/restaurants" element={<RestaurantList />} />
          {/* Phase 5: /restaurants/:id/analytics */}
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/restaurants" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
