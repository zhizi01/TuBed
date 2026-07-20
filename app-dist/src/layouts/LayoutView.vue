<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Activity,
  ChevronDown,
  FolderOpen,
  Image as ImageIcon,
  Images,
  KeyRound,
  LayoutDashboard,
  LogOut,
  Menu,
  Moon,
  Settings2,
  ShieldCheck,
  Sun,
  UserRound,
  Users,
} from 'lucide-vue-next'
import { useUserStore } from '../stores/user'
import { useTheme } from '../composables/useTheme'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const { theme, toggleTheme } = useTheme()
const drawerVisible = ref(false)

const navGroups = [
  {
    label: '工作区',
    items: [
      { label: '工作台', path: '/dashboard', icon: LayoutDashboard, permission: 'dashboard.view' },
      { label: '图片库', path: '/images', icon: Images, permission: 'images.manage' },
      { label: '相册管理', path: '/albums', icon: FolderOpen, permission: 'albums.manage' },
      { label: '开放 API', path: '/api-keys', icon: KeyRound, permission: 'api_keys.manage' },
    ],
  },
  {
    label: '系统管理',
    items: [
      { label: '管理概览', path: '/admin', icon: Activity, permission: 'admin.access' },
      { label: '用户管理', path: '/admin/users', icon: Users, permission: 'admin.access' },
      { label: '图片管理', path: '/admin/images', icon: ImageIcon, permission: 'admin.access' },
      { label: '密钥管理', path: '/admin/api-keys', icon: ShieldCheck, permission: 'admin.access' },
      { label: '接口策略', path: '/admin/api-settings', icon: Settings2, permission: 'admin.access' },
    ],
  },
]

const visibleGroups = computed(() => navGroups
  .map((group) => ({
    ...group,
    items: group.items.filter((item) => userStore.hasPermission(item.permission)),
  }))
  .filter((group) => group.items.length))

const initials = computed(() => (userStore.user?.username || 'U').slice(0, 2).toUpperCase())

async function logout() {
  await userStore.logout()
  router.replace('/login')
}

function closeDrawer() {
  drawerVisible.value = false
}
</script>

<template>
  <a class="skip-link" href="#main-content">跳至主内容</a>
  <div class="app-shell">
    <aside class="sidebar desktop-sidebar" aria-label="主导航">
      <RouterLink class="brand" to="/dashboard" aria-label="TuBed 工作台">
        <span class="brand-mark"><ImageIcon :size="18" /></span>
        <span>
          <strong>TuBed</strong>
          <small>Image workspace</small>
        </span>
      </RouterLink>

      <nav class="sidebar-nav">
        <section v-for="group in visibleGroups" :key="group.label" class="nav-group">
          <p class="nav-label">{{ group.label }}</p>
          <RouterLink
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            class="nav-item"
          >
            <component :is="item.icon" :size="17" stroke-width="1.8" />
            <span>{{ item.label }}</span>
          </RouterLink>
        </section>
      </nav>

      <div class="sidebar-footer">
        <RouterLink class="sidebar-user" to="/profile">
          <span class="avatar">{{ initials }}</span>
          <span class="user-copy">
            <strong>{{ userStore.user?.username }}</strong>
            <small>{{ userStore.user?.role_label }}</small>
          </span>
          <ChevronDown :size="15" />
        </RouterLink>
      </div>
    </aside>

    <el-drawer
      v-model="drawerVisible"
      direction="ltr"
      size="286px"
      :with-header="false"
      class="mobile-drawer"
    >
      <div class="drawer-content">
        <RouterLink class="brand" to="/dashboard" @click="closeDrawer">
          <span class="brand-mark"><ImageIcon :size="18" /></span>
          <span><strong>TuBed</strong><small>Image workspace</small></span>
        </RouterLink>
        <nav class="sidebar-nav" aria-label="移动端主导航">
          <section v-for="group in visibleGroups" :key="group.label" class="nav-group">
            <p class="nav-label">{{ group.label }}</p>
            <RouterLink
              v-for="item in group.items"
              :key="item.path"
              :to="item.path"
              class="nav-item"
              @click="closeDrawer"
            >
              <component :is="item.icon" :size="17" />
              <span>{{ item.label }}</span>
            </RouterLink>
          </section>
        </nav>
      </div>
    </el-drawer>

    <div class="main-column">
      <header class="topbar">
        <div class="topbar-title">
          <button
            class="icon-button mobile-menu-button"
            type="button"
            aria-label="打开导航"
            @click="drawerVisible = true"
          >
            <Menu :size="19" />
          </button>
          <div>
            <span class="topbar-eyebrow">{{ userStore.isAdmin ? '管理空间' : '个人空间' }}</span>
            <strong>{{ route.meta.title }}</strong>
          </div>
        </div>

        <div class="topbar-actions">
          <button
            class="icon-button"
            type="button"
            :aria-label="theme === 'dark' ? '切换至亮色主题' : '切换至暗色主题'"
            @click="toggleTheme"
          >
            <Sun v-if="theme === 'dark'" :size="18" />
            <Moon v-else :size="18" />
          </button>

          <el-dropdown trigger="click">
            <button class="topbar-user" type="button">
              <span class="avatar small">{{ initials }}</span>
              <span>{{ userStore.user?.username }}</span>
              <ChevronDown :size="14" />
            </button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item @click="router.push('/profile')">
                  <UserRound :size="15" />个人信息
                </el-dropdown-item>
                <el-dropdown-item divided @click="logout">
                  <LogOut :size="15" />退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>

      <main
        id="main-content"
        class="main-content"
        :class="{ 'is-wide': route.meta.wide }"
        tabindex="-1"
      >
        <RouterView />
      </main>
    </div>
  </div>
</template>
