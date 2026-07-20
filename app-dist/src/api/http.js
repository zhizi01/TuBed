import axios from 'axios'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 20000,
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem('tubed-token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => response.data,
  (error) => {
    const status = error.response?.status
    if (status === 401 && !location.pathname.startsWith('/login')) {
      localStorage.removeItem('tubed-token')
      localStorage.removeItem('tubed-user')
      const redirect = encodeURIComponent(location.pathname + location.search)
      location.assign(`/login?redirect=${redirect}`)
    }

    return Promise.reject(error.response?.data || {
      code: 500,
      message: '网络连接失败，请稍后重试',
      data: null,
    })
  },
)

export default http
