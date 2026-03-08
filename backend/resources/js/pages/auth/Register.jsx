import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { register } from '../../api/auth';
import { FiEye, FiEyeOff } from 'react-icons/fi';

export default function Register() {
  const navigate = useNavigate();

  const [form, setForm] = useState({
    username: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  function handleChange(e) {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
    setErrors((prev) => ({ ...prev, [e.target.name]: '' }));
  }

  function validate() {
    const errs = {};
    if (!form.username) errs.username = 'Username is required.';
    if (!form.name) errs.name = 'Name is required.';
    if (!form.email) errs.email = 'Email is required.';
    if (!form.password) errs.password = 'Password is required.';
    else if (form.password.length < 8) errs.password = 'Password must be at least 8 characters.';
    if (form.password !== form.password_confirmation)
      errs.password_confirmation = 'Passwords do not match.';
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
      await register(form);
      navigate('/login?registered=1');
    } catch (err) {
      if (err.response?.status === 422) {
        const laravelErrors = err.response.data?.errors ?? {};
        const mapped = {};
        Object.keys(laravelErrors).forEach((key) => {
          mapped[key] = laravelErrors[key][0];
        });
        setErrors(mapped);
      } else {
        setServerError(err.response?.data?.message ?? 'Registration failed. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  }

  const fields = [
    { name: 'username', label: 'Username', type: 'text', placeholder: 'johndoe' },
    { name: 'name', label: 'Name', type: 'text', placeholder: 'John Doe' },
    { name: 'email', label: 'Email', type: 'email', placeholder: 'you@example.com' },
    { name: 'password', label: 'Password', type: 'password', placeholder: '••••••••' },
    {
      name: 'password_confirmation',
      label: 'Confirm Password',
      type: 'password',
      placeholder: '••••••••',
    },
  ];

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <h1 className="text-2xl font-semibold text-gray-900 tracking-tight">Create an account</h1>
          <p className="text-sm text-gray-500 mt-1">Get started with Restaurant Analytics</p>
        </div>

        <div className="bg-white border border-gray-200 rounded-xl p-8 shadow-sm">
          {serverError && (
            <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {serverError}
            </div>
          )}

          <form onSubmit={handleSubmit} noValidate>
            {fields.map((field) => {
              const isPassword = field.name === 'password';
              // field.name === 'password' || field.name === 'password_confirmation';

              return (
                <div key={field.name} className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    {field.label}
                  </label>

                  <div className="relative">
                    <input
                      type={isPassword ? (showPassword ? 'text' : 'password') : field.type}
                      name={field.name}
                      value={form[field.name] || ''}
                      onChange={handleChange}
                      placeholder={field.placeholder}
                      className={`w-full rounded-lg border px-3 py-2 text-sm outline-none transition
                                                focus:ring-2 focus:ring-gray-900 focus:border-transparent
                                                ${errors[field.name] ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}
                                                ${isPassword ? 'pr-10' : ''}`}
                      style={
                        isPassword
                          ? {
                              WebkitTextSecurity: showPassword ? 'none' : undefined,
                            }
                          : {}
                      }
                    />

                    {isPassword && (
                      <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                      >
                        {showPassword ? <FiEyeOff size={18} /> : <FiEye size={18} />}
                      </button>
                    )}
                  </div>

                  {errors[field.name] && (
                    <p className="mt-1 text-xs text-red-600">{errors[field.name]}</p>
                  )}
                </div>
              );
            })}

            <button
              type="submit"
              disabled={loading}
              className="mt-2 w-full rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white
                hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              {loading ? 'Creating account…' : 'Create account'}
            </button>
          </form>
        </div>

        <p className="mt-4 text-center text-sm text-gray-500">
          Already have an account?{' '}
          <Link to="/login" className="text-gray-900 font-medium hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
