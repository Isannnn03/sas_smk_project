import api from './axios'

export const classesApi = {
  listClasses: (params = {}) =>
    api.get('/classes.php?action=list', { params }),

  createClass: (data) =>
    api.post('/classes.php?action=create', data),

  updateClass: (id, data) =>
    api.put(`/classes.php?action=update&id=${id}`, data),

  deleteClass: (id) =>
    api.delete(`/classes.php?action=delete&id=${id}`),

  listSubjects: (params = {}) =>
    api.get('/classes.php?action=list_subjects', { params }),

  createSubject: (data) =>
    api.post('/classes.php?action=create_subject', data),

  updateSubject: (id, data) =>
    api.put(`/classes.php?action=update_subject&id=${id}`, data),

  deleteSubject: (id) =>
    api.delete(`/classes.php?action=delete_subject&id=${id}`),
}
