"use client";

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useAuthStore } from '@/store/useAuthStore';
import { logout as logoutApi } from '@/lib/api/auth';

export default function Navbar() {
  const router = useRouter();
  const pathname = usePathname();
  const logout = useAuthStore((state) => state.logout);

  const navItems = [
    { path: '/dashboard', label: 'Dashboard' },
    { path: '/restaurants', label: 'Restaurants' },
  ];

  const isActive = (path: string) => pathname === path;

  async function handleLogout() {
    try {
      await logoutApi();
    } catch (_) {
      /* ignore */
    }
    logout();
    router.push('/login');
  }

  return (
    <nav className="bg-white border-b border-gray-200 px-6 py-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-8">
          <Link
            href="/"
            className="text-lg font-semibold text-gray-900 hover:text-gray-700 transition"
          >
            Restaurant Analytics
          </Link>

          <div className="flex items-center gap-6">
            {navItems.map((item) => (
              <Link
                key={item.path}
                href={item.path}
                className={`text-sm font-medium transition ${
                  isActive(item.path) ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900'
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
