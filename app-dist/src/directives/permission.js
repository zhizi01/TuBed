import { useUserStore } from '../stores/user'

function applyPermission(el, binding) {
  const userStore = useUserStore()
  const required = Array.isArray(binding.value) ? binding.value : [binding.value]
  const allowed = required.filter(Boolean).some((item) => userStore.hasPermission(item))

  if (el.__permissionDisplay === undefined) {
    el.__permissionDisplay = el.style.display
  }
  el.style.display = allowed ? el.__permissionDisplay : 'none'
  el.setAttribute('aria-hidden', allowed ? 'false' : 'true')
}

export const permissionDirective = {
  mounted: applyPermission,
  updated: applyPermission,
}
