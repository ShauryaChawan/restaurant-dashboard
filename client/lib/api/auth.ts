import api from './axios';

export function login(form: any) {
  return api.post('/api/v1/auth/login', form).then((res) => res.data.data);
}

export function register(form: any) {
  return api.post('/api/v1/auth/register', form);
}

export function logout() {
  return api.post('/api/v1/auth/logout');
}
