import { ref } from 'vue'

const storedTheme = localStorage.getItem('tubed-theme')
const preferredDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches
const theme = ref(storedTheme || (preferredDark ? 'dark' : 'light'))

function applyTheme(value) {
  document.documentElement.dataset.theme = value
  document.documentElement.classList.toggle('dark', value === 'dark')
  document.documentElement.style.colorScheme = value
  localStorage.setItem('tubed-theme', value)
}

applyTheme(theme.value)

export function useTheme() {
  function setTheme(value) {
    theme.value = value === 'dark' ? 'dark' : 'light'
    applyTheme(theme.value)
  }

  function toggleTheme() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, setTheme, toggleTheme }
}
