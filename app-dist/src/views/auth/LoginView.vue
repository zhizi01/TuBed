<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Image as ImageIcon, Moon, ShieldCheck, Sun, Zap } from 'lucide-vue-next'
import { ElMessage } from 'element-plus'
import { useUserStore } from '../../stores/user'
import { useTheme } from '../../composables/useTheme'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const { theme, toggleTheme } = useTheme()
const formRef = ref()
const loading = ref(false)
const form = reactive({ identifier: '', password: '' })
const rules = {
  identifier: [{ required: true, message: '请输入用户名或邮箱', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

async function submit() {
  await formRef.value.validate()
  loading.value = true
  try {
    await userStore.login(form)
    ElMessage.success('登录成功')
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
      ? route.query.redirect
      : '/dashboard'
    router.replace(redirect)
  } catch (error) {
    ElMessage.error(error.message || '登录失败')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <button class="auth-theme-button icon-button" type="button" @click="toggleTheme">
      <Sun v-if="theme === 'dark'" :size="18" />
      <Moon v-else :size="18" />
    </button>

    <section class="auth-showcase" aria-label="产品介绍">
      <div class="auth-brand">
        <span class="brand-mark large"><ImageIcon :size="23" /></span>
        <span><strong>TuBed</strong><small>Image workspace</small></span>
      </div>
      <div class="auth-message">
        <span class="eyebrow">图片资产工作区</span>
        <h1>让每一张图片<br>都清晰可控。</h1>
        <p>统一管理图片、相册与开放接口，在轻量工作流中掌握存储和访问状态。</p>
      </div>
      <div class="auth-features">
        <article>
          <Zap :size="18" />
          <div><strong>快速上传</strong><span>安全校验与直链生成</span></div>
        </article>
        <article>
          <ShieldCheck :size="18" />
          <div><strong>权限清晰</strong><span>管理员与用户角色分权</span></div>
        </article>
      </div>
    </section>

    <main class="auth-form-side">
      <div class="auth-form-card">
        <div class="auth-form-heading">
          <span class="mobile-auth-logo brand-mark"><ImageIcon :size="19" /></span>
          <h2>欢迎回来</h2>
          <p>登录 TuBed 管理你的图片空间</p>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="submit">
          <el-form-item label="用户名或邮箱" prop="identifier">
            <el-input
              v-model.trim="form.identifier"
              autocomplete="username"
              placeholder="name@example.com"
              size="large"
              @keyup.enter="submit"
            />
          </el-form-item>
          <el-form-item label="密码" prop="password">
            <el-input
              v-model="form.password"
              type="password"
              show-password
              autocomplete="current-password"
              placeholder="输入登录密码"
              size="large"
              @keyup.enter="submit"
            />
          </el-form-item>
          <el-button
            class="auth-submit"
            type="primary"
            size="large"
            :loading="loading"
            @click="submit"
          >
            进入工作台
            <ArrowRight v-if="!loading" :size="17" />
          </el-button>
        </el-form>

        <p class="auth-switch">
          还没有账号？
          <RouterLink to="/register">创建账号</RouterLink>
        </p>
      </div>
    </main>
  </div>
</template>
