import { create } from 'zustand';

interface User {
  id: number;
  name: string;
  email: string;
  // add other fields as necessary
}

interface AuthState {
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;
  login: (token: string, user?: User | null) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>((set) => {
  // We need to verify window exists because this might run on the server in Next.js
  const getInitialToken = () => {
    if (typeof window !== 'undefined') {
      return localStorage.getItem('auth_token');
    }
    return null;
  };

  const initialToken = getInitialToken();

  return {
    token: initialToken,
    user: null,
    isAuthenticated: Boolean(initialToken),

    login: (token, user = null) => {
      if (typeof window !== 'undefined') {
        localStorage.setItem('auth_token', token);
      }
      set({ token, user, isAuthenticated: true });
    },

    logout: () => {
      if (typeof window !== 'undefined') {
        localStorage.removeItem('auth_token');
      }
      set({ token: null, user: null, isAuthenticated: false });
    },
  };
});
