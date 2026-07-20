<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Image as ImageIcon, Moon, Sun, UserPlus } from 'lucide-vue-next'
import { ElMessage } from 'element-plus'
import { useUserStore } from '../../stores/user'
import { useTheme } from '../../composables/useTheme'

const router = useRouter()
const userStore = useUserStore()
const { theme, toggleTheme } = useTheme()
const formRef = ref()
const loading = ref(false)
const form = reactive({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
})

const rules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { pattern: /^[A-Za-z0-9_]{3,32}$/, message: '3-32位字母、数字或下划线', trigger: 'blur' },
  ],
  email: [
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 8, max: 72, message: '密码长度为8-72位', trigger: 'blur' },
  ],
  confirmPassword: [
    {
      validator: (_rule, value, callback) => {
        if (!value) callback(new Error('请再次输入密码'))
        else if (value !== form.password) callback(new Error('两次输入的密码不一致'))
        else callback()
      },
      trigger: 'blur',
    },
  ],
}

async function submit() {
  await formRef.value.validate()
  loading.value = true
  try {
    await userStore.register({
      username: form.username,
      email: form.email,
      password: form.password,
    })
    ElMessage.success('账号创建成功')
    router.replace('/dashboard')
  } catch (error) {
    ElMessage.error(error.message || '注册失败')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page register-page">
    <button class="auth-theme-button icon-button" type="button" @click="toggleTheme">
      <Sun v-if="theme === 'dark'" :size="18" />
      <Moon v-else :size="18" />
    </button>

    <section class="auth-showcase compact" aria-label="注册说明">
      <div class="auth-brand">
        <span class="brand-mark large"><ImageIcon :size="23" /></span>
        <span><strong>TuBed</strong><small>Image workspace</small></span>
      </div>
      <div class="auth-message">
        <span class="eyebrow">开始使用</span>
        <h1>构建你的<br>图片资产空间。</h1>
        <p>注册后即可获得图片库、相册、存储统计和开放 API 密钥管理能力。</p>
      </div>
      <div class="first-admin-note">
        <UserPlus :size="19" />
        <div>
          <strong>首次部署提示</strong>
          <span>系统首个注册账号默认成为管理员。</span>
        </div>
      </div>
    </section>

    <main class="auth-form-side">
      <div class="auth-form-card register-card">
        <div class="auth-form-heading">
          <span class="mobile-auth-logo brand-mark"><ImageIcon :size="19" /></span>
          <h2>创建账号</h2>
          <p>填写基础信息以进入 TuBed</p>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
          <el-form-item label="用户名" prop="username">
            <el-input v-model.trim="form.username" autocomplete="username" size="large" placeholder="tubed_user" />
          </el-form-item>
          <el-form-item label="邮箱（选填）" prop="email">
            <el-input v-model.trim="form.email" autocomplete="email" size="large" placeholder="name@example.com" />
          </el-form-item>
          <div class="auth-form-grid">
            <el-form-item label="密码" prop="password">
              <el-input v-model="form.password" type="password" show-password autocomplete="new-password" size="large" />
            </el-form-item>
            <el-form-item label="确认密码" prop="confirmPassword">
              <el-input v-model="form.confirmPassword" type="password" show-password autocomplete="new-password" size="large" @keyup.enter="submit" />
            </el-form-item>
          </div>
          <el-button class="auth-submit" type="primary" size="large" :loading="loading" @click="submit">
            创建并进入
            <ArrowRight v-if="!loading" :size="17" />
          </el-button>
        </el-form>

        <p class="auth-switch">
          已有账号？
          <RouterLink to="/login">返回登录</RouterLink>
        </p>
      </div>
    </main>
  </div>
</template>
