import api from './axios'

export const schedulesApi = {
  list: (params = {}) =>
    api.get('/schedules.php?action=list', { params }),

  detail: (id) =>
    api.get(`/schedules.php?action=detail&id=${id}`),

  create: (data) =>
    api.post('/schedules.php?action=create', data),

  update: (id, data) =>
    api.put(`/schedules.php?action=update&id=${id}`, data),

  delete: (id) =>
    api.delete(`/schedules.php?action=delete&id=${id}`),

  byClass: (classId) =>
    api.get(`/schedules.php?action=by_class&class_id=${classId}`),

  byTeacher: (teacherId) =>
    api.get(`/schedules.php?action=by_teacher&teacher_id=${teacherId}`),
}
