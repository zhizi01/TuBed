<script setup>
import { computed } from 'vue'
import { HardDrive, Mail, ShieldCheck, UserRound } from 'lucide-vue-next'
import { useUserStore } from '../stores/user'

const userStore = useUserStore()
const user = computed(() => userStore.user || {})

const permissionLabels = {
  '*': '全部权限',
  'dashboard.view': '查看工作台',
  'images.manage': '管理图片',
  'albums.manage': '管理相册',
  'api_keys.manage': '管理 API 密钥',
  'profile.view': '查看个人信息',
}

function formatBytes(bytes = 0) {
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`
  if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`
  return `${(bytes / 1024).toFixed(1)} KB`
}
</script>

<template>
  <div class="manage-page profile-page">
    <header class="profile-banner">
      <span class="profile-avatar">{{ user.username?.slice(0, 2).toUpperCase() }}</span>
      <div>
        <span class="eyebrow">账户资料</span>
        <h1>{{ user.username }}</h1>
        <p>{{ user.role_label }} · 创建于 {{ new Date(user.created_at).toLocaleDateString('zh-CN') }}</p>
      </div>
      <span class="status-badge success">账号正常</span>
    </header>

    <section class="profile-grid">
      <article class="panel-card">
        <div class="profile-card-title"><UserRound :size="17" /><div><h2>基础信息</h2><p>当前登录账号的身份资料</p></div></div>
        <dl class="info-list">
          <div><dt>用户名</dt><dd>{{ user.username }}</dd></div>
          <div><dt>邮箱</dt><dd><Mail :size="14" />{{ user.email || '未绑定' }}</dd></div>
          <div><dt>角色</dt><dd>{{ user.role_label }}</dd></div>
          <div><dt>最后登录</dt><dd>{{ user.last_login_at ? new Date(user.last_login_at).toLocaleString('zh-CN') : '首次登录' }}</dd></div>
        </dl>
      </article>

      <article class="panel-card">
        <div class="profile-card-title"><HardDrive :size="17" /><div><h2>存储空间</h2><p>图片文件占用和账号配额</p></div></div>
        <div class="quota-value"><strong>{{ formatBytes(user.storage_used) }}</strong><span>/ {{ formatBytes(user.storage_quota) }}</span></div>
        <el-progress :percentage="Math.min(100, user.storage_percent || 0)" :stroke-width="7" :show-text="false" />
        <div class="quota-meta"><span>已使用 {{ user.storage_percent || 0 }}%</span><span>剩余 {{ formatBytes(user.storage_remaining) }}</span></div>
      </article>

      <article class="panel-card permission-card">
        <div class="profile-card-title"><ShieldCheck :size="17" /><div><h2>角色权限</h2><p>权限由管理员统一分配并由后端校验</p></div></div>
        <div class="permission-list">
          <span v-for="permission in user.permissions" :key="permission">
            <ShieldCheck :size="13" />{{ permissionLabels[permission] || permission }}
          </span>
        </div>
      </article>
    </section>
  </div>
</template>

<style scoped>
.profile-banner {
  display: flex;
  min-height: 154px;
  align-items: center;
  gap: 18px;
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background:
    linear-gradient(120deg, var(--accent-soft), transparent 52%),
    var(--panel-bg);
}
.profile-avatar {
  display: inline-flex;
  width: 66px;
  height: 66px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 18px;
  color: white;
  background: var(--accent);
  font-size: 20px;
  font-weight: 700;
}
.profile-banner > div {
  flex: 1;
}
.profile-banner h1 {
  margin: 6px 0 3px;
  font-size: 24px;
  letter-spacing: -0.5px;
}
.profile-banner p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 10px;
}
.profile-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.permission-card {
  grid-column: 1 / -1;
}
.profile-card-title {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  color: var(--accent);
}
.profile-card-title div {
  display: flex;
  flex-direction: column;
}
.profile-card-title h2 {
  margin: 0;
  color: var(--text-primary);
  font-size: 12px;
}
.profile-card-title p {
  margin: 3px 0 0;
  color: var(--text-tertiary);
  font-size: 9px;
}
.info-list {
  display: grid;
  gap: 0;
  margin: 17px 0 0;
}
.info-list > div {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}
.info-list > div:last-child { border-bottom: 0; }
.info-list dt { color: var(--text-tertiary); font-size: 10px; }
.info-list dd {
  display: flex;
  align-items: center;
  gap: 5px;
  margin: 0;
  color: var(--text-primary);
  font-size: 10px;
  text-align: right;
}
.quota-value {
  margin: 25px 0 13px;
}
.quota-value strong { font-size: 23px; letter-spacing: -0.5px; }
.quota-value span { margin-left: 5px; color: var(--text-tertiary); font-size: 10px; }
.quota-meta {
  display: flex;
  justify-content: space-between;
  margin-top: 9px;
  color: var(--text-tertiary);
  font-size: 9px;
}
.permission-list {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  margin-top: 18px;
}
.permission-list span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 9px;
  border-radius: 7px;
  color: var(--accent);
  background: var(--accent-soft);
  font-size: 9px;
  font-weight: 600;
}
@media (max-width: 720px) {
  .profile-banner { align-items: flex-start; flex-wrap: wrap; }
  .profile-grid { grid-template-columns: 1fr; }
  .permission-card { grid-column: auto; }
}
</style>
