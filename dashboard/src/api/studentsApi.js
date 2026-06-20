import api from './axios'

export const studentsApi = {
  list: (params = {}) =>
    api.get('/students.php?action=list', { params }),

  detail: (id) =>
    api.get(`/students.php?action=detail&id=${id}`),

  create: (data) =>
    api.post('/students.php?action=create', data),

  update: (id, data) =>
    api.put(`/students.php?action=update&id=${id}`, data),

  delete: (id) =>
    api.delete(`/students.php?action=delete&id=${id}`),

  regenerateQr: (id) =>
    api.post(`/students.php?action=regenerate_qr&id=${id}`),
}
