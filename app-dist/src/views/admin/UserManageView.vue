<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Pencil, RefreshCw, Search, Users } from 'lucide-vue-next'
import { ElMessage } from 'element-plus'
import { adminApi } from '../../api'

const loading = ref(true)
const saving = ref(false)
const dialogVisible = ref(false)
const list = ref([])
const total = ref(0)
const editing = ref(null)
const filters = reactive({ keyword: '', role: '', status: '', page: 1, page_size: 20 })
const form = reactive({ role: 'user', status: 1, quotaGb: 5 })

function formatBytes(bytes = 0) {
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`
  return `${(bytes / 1024 ** 2).toFixed(1)} MB`
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString('zh-CN') : '-'
}

async function loadUsers() {
  loading.value = true
  try {
    const response = await adminApi.users(filters)
    list.value = response.data.list
    total.value = response.data.total
  } catch (error) {
    ElMessage.error(error.message || '用户列表加载失败')
  } finally {
    loading.value = false
  }
}

function search() {
  filters.page = 1
  loadUsers()
}

function clearFilters() {
  Object.assign(filters, { keyword: '', role: '', status: '', page: 1 })
  loadUsers()
}

function openEdit(user) {
  editing.value = user
  form.role = user.role
  form.status = user.status
  form.quotaGb = Number((user.storage_quota / 1024 ** 3).toFixed(2))
  dialogVisible.value = true
}

async function save() {
  saving.value = true
  try {
    const response = await adminApi.updateUser(editing.value.id, {
      role: form.role,
      status: form.status,
      storage_quota: Math.round(form.quotaGb * 1024 ** 3),
    })
    Object.assign(editing.value, response.data)
    dialogVisible.value = false
    ElMessage.success('用户设置已保存')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(loadUsers)
</script>

<template>
  <div class="manage-page">
    <header class="manage-header">
      <div>
        <h1>用户管理</h1>
        <p>调整账号角色、启用状态和存储配额。角色变更同时影响路由、菜单和后端访问权限。</p>
      </div>
    </header>

    <section class="manage-toolbar">
      <div class="toolbar-group">
        <el-input v-model.trim="filters.keyword" clearable placeholder="搜索用户名或邮箱" style="width: 230px" @keyup.enter="search">
          <template #prefix><Search :size="14" /></template>
        </el-input>
        <el-select v-model="filters.role" clearable placeholder="全部角色" style="width: 130px" @change="search">
          <el-option label="管理员" value="admin" />
          <el-option label="用户" value="user" />
        </el-select>
        <el-select v-model="filters.status" clearable placeholder="全部状态" style="width: 130px" @change="search">
          <el-option label="正常" :value="1" />
          <el-option label="已禁用" :value="0" />
        </el-select>
      </div>
      <div class="toolbar-group">
        <el-button @click="clearFilters">清空</el-button>
        <el-button :loading="loading" @click="loadUsers"><RefreshCw :size="14" />刷新</el-button>
      </div>
    </section>

    <section class="manage-table-card">
      <el-table v-loading="loading" :data="list">
        <el-table-column label="用户" min-width="220">
          <template #default="{ row }">
            <div class="user-cell">
              <span class="avatar small">{{ row.username.slice(0, 2).toUpperCase() }}</span>
              <div><strong>{{ row.username }}</strong><span>{{ row.email || '未填写邮箱' }}</span></div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="角色" width="105">
          <template #default="{ row }"><el-tag size="small" :type="row.role === 'admin' ? 'primary' : 'info'">{{ row.role === 'admin' ? '管理员' : '用户' }}</el-tag></template>
        </el-table-column>
        <el-table-column label="状态" width="105">
          <template #default="{ row }"><span class="status-badge" :class="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '正常' : '已禁用' }}</span></template>
        </el-table-column>
        <el-table-column label="存储用量" min-width="165">
          <template #default="{ row }">
            <div class="storage-cell"><span>{{ formatBytes(row.storage_used) }} / {{ formatBytes(row.storage_quota) }}</span><el-progress :percentage="Math.min(100, row.storage_used * 100 / row.storage_quota)" :show-text="false" :stroke-width="4" /></div>
          </template>
        </el-table-column>
        <el-table-column label="最后登录" width="180">
          <template #default="{ row }">{{ formatDate(row.last_login_at) }}</template>
        </el-table-column>
        <el-table-column label="创建时间" width="180">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="105" fixed="right" align="right">
          <template #default="{ row }"><el-button text @click="openEdit(row)"><Pencil :size="14" />设置</el-button></template>
        </el-table-column>
        <template #empty><el-empty :image-size="80" description="没有匹配用户"><Users :size="20" /></el-empty></template>
      </el-table>
    </section>

    <footer class="manage-pagination">
      <el-pagination v-model:current-page="filters.page" :page-size="filters.page_size" :total="total" layout="prev, pager, next, total" @current-change="loadUsers" />
    </footer>

    <el-dialog v-model="dialogVisible" title="用户设置" width="min(480px, 92vw)">
      <div v-if="editing" class="editing-user">
        <span class="avatar">{{ editing.username.slice(0, 2).toUpperCase() }}</span>
        <div><strong>{{ editing.username }}</strong><span>{{ editing.email || '未填写邮箱' }}</span></div>
      </div>
      <el-form label-position="top">
        <div class="form-grid">
          <el-form-item label="账号角色">
            <el-select v-model="form.role" style="width: 100%">
              <el-option label="普通用户" value="user" />
              <el-option label="管理员" value="admin" />
            </el-select>
          </el-form-item>
          <el-form-item label="账号状态">
            <el-select v-model="form.status" style="width: 100%">
              <el-option label="正常" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
        </div>
        <el-form-item label="存储配额（GB）">
          <el-input-number v-model="form.quotaGb" :min="0.1" :max="10240" :step="1" :precision="2" style="width: 100%" />
        </el-form-item>
        <p class="form-tip">禁用账号会撤销全部登录令牌，并停用其开放 API 密钥。</p>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存设置</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.user-cell, .editing-user { display: flex; align-items: center; gap: 9px; }
.user-cell > div, .editing-user > div { display: flex; min-width: 0; flex-direction: column; }
.user-cell strong, .editing-user strong { color: var(--text-primary); font-size: 10px; }
.user-cell span, .editing-user span { margin-top: 3px; color: var(--text-tertiary); font-size: 9px; }
.storage-cell { display: grid; max-width: 150px; gap: 6px; font-size: 9px; }
.editing-user { margin-bottom: 17px; padding: 11px; border-radius: 9px; background: var(--panel-muted); }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.form-tip { margin: 0; color: var(--text-tertiary); font-size: 9px; line-height: 1.6; }
@media (max-width: 520px) { .form-grid { grid-template-columns: 1fr; gap: 0; } }
</style>
