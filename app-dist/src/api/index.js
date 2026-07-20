import http from './http'

export const authApi = {
  login: (data) => http.post('/auth/login', data),
  register: (data) => http.post('/auth/register', data),
  me: () => http.get('/auth/me'),
  logout: () => http.post('/auth/logout'),
}

export const statsApi = {
  overview: () => http.get('/stats/overview'),
}

export const albumApi = {
  list: () => http.get('/albums'),
  create: (data) => http.post('/albums', data),
  update: (id, data) => http.patch(`/albums/${id}`, data),
  remove: (id) => http.delete(`/albums/${id}`),
}

export const imageApi = {
  list: (params) => http.get('/images', { params }),
  upload: (formData, onProgress) => http.post('/images', formData, {
    onUploadProgress: onProgress,
  }),
  detail: (id) => http.get(`/images/${id}`),
  update: (id, data) => http.patch(`/images/${id}`, data),
  remove: (id) => http.delete(`/images/${id}`),
}

export const apiKeyApi = {
  list: () => http.get('/api-keys'),
  create: (data) => http.post('/api-keys', data),
  update: (id, data) => http.patch(`/api-keys/${id}`, data),
  regenerate: (id) => http.post(`/api-keys/${id}/regenerate`),
  remove: (id) => http.delete(`/api-keys/${id}`),
}

export const adminApi = {
  overview: () => http.get('/admin/overview'),
  users: (params) => http.get('/admin/users', { params }),
  updateUser: (id, data) => http.patch(`/admin/users/${id}`, data),
  images: (params) => http.get('/admin/images', { params }),
  removeImage: (id) => http.delete(`/admin/images/${id}`),
  apiSettings: () => http.get('/admin/api-settings'),
  updateApiSettings: (data) => http.put('/admin/api-settings', data),
  apiKeys: (params) => http.get('/admin/api-keys', { params }),
  updateApiKey: (id, data) => http.patch(`/admin/api-keys/${id}`, data),
}
