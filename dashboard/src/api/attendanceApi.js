import api from './axios'

export const attendanceApi = {
  startSession: (data) =>
    api.post('/attendance.php?action=start_session', data),

  scanQr: (data) =>
    api.post('/attendance.php?action=scan_qr', data),

  closeSession: (data) =>
    api.post('/attendance.php?action=close_session', data),

  sessionStatus: (sessionId) =>
    api.get(`/attendance.php?action=session_status&session_id=${sessionId}`),

  sessionReport: (sessionId) =>
    api.get(`/attendance.php?action=session_report&session_id=${sessionId}`),

  updateStatus: (data) =>
    api.put('/attendance.php?action=update_status', data),

  reportByClass: (params) =>
    api.get('/attendance.php?action=report_by_class', { params }),
}
