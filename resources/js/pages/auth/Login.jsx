import { useState, useEffect } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { login } from '../../api/auth';
import { toast } from 'react-hot-toast';

export default function Login() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { login: loginCtx } = useAuth();

  const [form, setForm] = useState({ email: '', password: '' });
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [loading, setLoading] = useState(false);

  const justRegistered = searchParams.get('registered') === '1';

  useEffect(() => {
    if (justRegistered) {
      toast.success('Account created — you can now sign in.', { id: 'registered-toast' });
      searchParams.delete('registered');
      navigate({ search: searchParams.toString() }, { replace: true });
    }
  }, [justRegistered, navigate, searchParams]);

  function handleChange(e) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    setErrors((prev) => ({ ...prev, [e.target.name]: '' }));
  }

  function validate() {
    const errs = {};
    if (!form.email) errs.email = 'Email is required.';
    if (!form.password) errs.password = 'Password is required.';
    return errs;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setServerError('');
    const errs = validate();
    if (Object.keys(errs).length) {
      setErrors(errs);
      return;
    }

    setLoading(true);
    try {
      const data = await login(form);
      loginCtx(data.token, data.user);
      navigate('/dashboard');
    } catch (err) {
      setServerError(err.response?.data?.message ?? 'Login failed. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">
            Restaurant Analytics
          </h1>
          <p className="text-sm text-gray-500 mt-1">Sign in to your account</p>
        </div>

        <div className="bg-white border border-gray-200 rounded-xl p-8 shadow-sm">

          {serverError && (
            <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {serverError}
            </div>
          )}

          <form onSubmit={handleSubmit} noValidate>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input
                type="email"
                name="email"
                value={form.email}
                onChange={handleChange}
                placeholder="you@example.com"
                className={`w-full rounded-lg border px-3 py-2 text-sm outline-none transition
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent
                  ${errors.email ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}`}
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
            </div>

            <div className="mb-6">
              <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input
                type="password"
                name="password"
                value={form.password}
                onChange={handleChange}
                placeholder="••••••••"
                className={`w-full rounded-lg border px-3 py-2 text-sm outline-none transition
                  focus:ring-2 focus:ring-gray-900 focus:border-transparent
                  ${errors.password ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}`}
              />
              {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white
                hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              {loading ? 'Signing in…' : 'Sign in'}
            </button>
          </form>
        </div>

        <p className="mt-4 text-center text-sm text-gray-500">
          Don't have an account?{' '}
          <Link to="/register" className="text-gray-900 font-medium hover:underline">
            Register
          </Link>
        </p>
      </div>
    </div>
  );
}
