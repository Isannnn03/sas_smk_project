import api from './axios'

export const teachersApi = {
  list: (params = {}) =>
    api.get('/teachers.php?action=list', { params }),

  detail: (id) =>
    api.get(`/teachers.php?action=detail&id=${id}`),

  create: (data) =>
    api.post('/teachers.php?action=create', data),

  update: (id, data) =>
    api.put(`/teachers.php?action=update&id=${id}`, data),

  delete: (id) =>
    api.delete(`/teachers.php?action=delete&id=${id}`),
}
