import api from './axios'

export const authApi = {
  login: (credentials) =>
    api.post('/auth.php?action=login', credentials),

  logout: () =>
    api.post('/auth.php?action=logout'),

  profile: () =>
    api.get('/auth.php?action=profile'),
}
