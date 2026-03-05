import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

/**
 * PrivateRoute
 *
 * Redirects unauthenticated users to /login.
 * Checks for auth_token in localStorage.
 * All protected pages are wrapped with this component in AppRouter.
 */
export default function PrivateRoute() {
  const { isAuthenticated } = useAuth();
  return isAuthenticated ? <Outlet /> : <Navigate to="/login" replace />;
}
