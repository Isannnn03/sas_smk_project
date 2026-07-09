import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost/sas%20smk%20project/sas_smk_project/backend/api',
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000
})

// ── Request Interceptor: tambahkan token ke setiap request ──
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('sas_token')
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// ── Response Interceptor: handle 401 global ──
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('sas_token')
      localStorage.removeItem('sas_user')
      // Redirect ke login jika belum di sana
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
