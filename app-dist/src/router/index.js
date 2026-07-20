import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '../stores/user'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/auth/LoginView.vue'),
    meta: { title: '登录', guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('../views/auth/RegisterView.vue'),
    meta: { title: '注册', guestOnly: true },
  },
  {
    path: '/forbidden',
    name: 'forbidden',
    component: () => import('../views/ForbiddenView.vue'),
    meta: { title: '无权访问' },
  },
  {
    path: '/',
    component: () => import('../layouts/LayoutView.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { title: '工作台', permission: 'dashboard.view' },
      },
      {
        path: 'images',
        name: 'images',
        component: () => import('../views/images/ImageLibraryView.vue'),
        meta: { title: '图片库', permission: 'images.manage', wide: true },
      },
      {
        path: 'albums',
        name: 'albums',
        component: () => import('../views/albums/AlbumView.vue'),
        meta: { title: '相册管理', permission: 'albums.manage' },
      },
      {
        path: 'api-keys',
        name: 'api-keys',
        component: () => import('../views/api-keys/ApiKeyView.vue'),
        meta: { title: '开放 API', permission: 'api_keys.manage', wide: true },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('../views/ProfileView.vue'),
        meta: { title: '个人信息', permission: 'profile.view' },
      },
      {
        path: 'admin',
        name: 'admin-overview',
        component: () => import('../views/admin/AdminOverviewView.vue'),
        meta: { title: '管理概览', permission: 'admin.access' },
      },
      {
        path: 'admin/users',
        name: 'admin-users',
        component: () => import('../views/admin/UserManageView.vue'),
        meta: { title: '用户管理', permission: 'admin.access', wide: true },
      },
      {
        path: 'admin/images',
        name: 'admin-images',
        component: () => import('../views/admin/AdminImageView.vue'),
        meta: { title: '图片管理', permission: 'admin.access', wide: true },
      },
      {
        path: 'admin/api-keys',
        name: 'admin-api-keys',
        component: () => import('../views/admin/ApiKeyManageView.vue'),
        meta: { title: '密钥管理', permission: 'admin.access', wide: true },
      },
      {
        path: 'admin/api-settings',
        name: 'admin-api-settings',
        component: () => import('../views/admin/OpenApiSettingView.vue'),
        meta: { title: '接口策略', permission: 'admin.access' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('../views/NotFoundView.vue'),
    meta: { title: '页面不存在' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const userStore = useUserStore()
  document.title = `${to.meta.title || '后台'} · TuBed`

  if (to.meta.guestOnly && userStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.matched.some((record) => record.meta.requiresAuth)) {
    if (!userStore.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (!userStore.initialized) {
      try {
        await userStore.fetchMe()
      } catch {
        userStore.clearSession()
        return { name: 'login', query: { redirect: to.fullPath } }
      }
    }

    if (to.meta.permission && !userStore.hasPermission(to.meta.permission)) {
      return { name: 'forbidden' }
    }
  }

  return true
})

export default router
