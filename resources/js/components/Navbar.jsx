import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { logout as logoutApi } from '../api/auth';

export default function Navbar() {
  const navigate = useNavigate();
  const location = useLocation();
  const { logout } = useAuth();

  const navItems = [
    { path: '/dashboard', label: 'Dashboard' },
    { path: '/restaurants', label: 'Restaurants' },
  ];

  const isActive = (path) => location.pathname === path;

  async function handleLogout() {
    try {
      await logoutApi();
    } catch (_) {
      /* ignore */
    }
    logout();
    navigate('/login');
  }

  return (
    <nav className="bg-white border-b border-gray-200 px-6 py-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-8">
          <Link to="/" className="text-lg font-semibold text-gray-900 hover:text-gray-700 transition">
            Restaurant Analytics
          </Link>

          <div className="flex items-center gap-6">
            {navItems.map((item) => (
              <Link
                key={item.path}
                to={item.path}
                className={`text-sm font-medium transition ${
                  isActive(item.path)
                    ? 'text-gray-900'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                {item.label}
              </Link>
            ))}
          </div>
        </div>

        <button
          onClick={handleLogout}
          className="text-sm text-red-500 hover:text-red-700 transition"
        >
          Logout
        </button>
      </div>
    </nav>
  );
}
