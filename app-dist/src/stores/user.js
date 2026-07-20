import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '../api'

function readStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('tubed-user') || 'null')
  } catch {
    localStorage.removeItem('tubed-user')
    return null
  }
}

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('tubed-token') || '')
  const user = ref(readStoredUser())
  const initialized = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))
  const permissions = computed(() => user.value?.permissions || [])
  const isAdmin = computed(() => hasPermission('admin.access'))

  function hasPermission(permission) {
    if (!permission) return true
    return permissions.value.includes('*') || permissions.value.includes(permission)
  }

  function setSession(data) {
    token.value = data.access_token
    user.value = data.user
    initialized.value = true
    localStorage.setItem('tubed-token', data.access_token)
    localStorage.setItem('tubed-user', JSON.stringify(data.user))
  }

  function updateUser(data) {
    user.value = data
    initialized.value = true
    localStorage.setItem('tubed-user', JSON.stringify(data))
  }

  function clearSession() {
    token.value = ''
    user.value = null
    initialized.value = true
    localStorage.removeItem('tubed-token')
    localStorage.removeItem('tubed-user')
  }

  async function login(payload) {
    const response = await authApi.login(payload)
    setSession(response.data)
    return response
  }

  async function register(payload) {
    const response = await authApi.register(payload)
    setSession(response.data)
    return response
  }

  async function fetchMe() {
    if (!token.value) {
      initialized.value = true
      return null
    }

    const response = await authApi.me()
    updateUser(response.data)
    return response.data
  }

  async function logout() {
    try {
      if (token.value) await authApi.logout()
    } finally {
      clearSession()
    }
  }

  return {
    token,
    user,
    initialized,
    isAuthenticated,
    permissions,
    isAdmin,
    hasPermission,
    login,
    register,
    fetchMe,
    logout,
    clearSession,
    updateUser,
  }
})
