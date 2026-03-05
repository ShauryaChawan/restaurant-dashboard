import { Navigate, Outlet } from 'react-router-dom';

/**
 * PrivateRoute
 *
 * Redirects unauthenticated users to /login.
 * Checks for auth_token in localStorage.
 * All protected pages are wrapped with this component in AppRouter.
 */
export default function PrivateRoute() {
  const token = localStorage.getItem('auth_token');

  return token ? <Outlet /> : <Navigate to="/login" replace />;
}
