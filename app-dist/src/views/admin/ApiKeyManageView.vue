<script setup>
import { onMounted, reactive, ref } from 'vue'
import { KeyRound, Pencil, RefreshCw } from 'lucide-vue-next'
import { ElMessage } from 'element-plus'
import { adminApi } from '../../api'

const loading = ref(true)
const saving = ref(false)
const dialogVisible = ref(false)
const list = ref([])
const total = ref(0)
const editing = ref(null)
const filters = reactive({ user_id: '', status: '', page: 1, page_size: 20 })
const form = reactive({
  status: 1,
  rate_limit: 60,
  rate_window: 60,
  total_limit: 10000,
  expires_at: '',
})

async function load() {
  loading.value = true
  try {
    const response = await adminApi.apiKeys(filters)
    list.value = response.data.list
    total.value = response.data.total
  } catch (error) {
    ElMessage.error(error.message || '密钥列表加载失败')
  } finally {
    loading.value = false
  }
}

function search() {
  filters.page = 1
  load()
}

function clearFilters() {
  Object.assign(filters, { user_id: '', status: '', page: 1 })
  load()
}

function openEdit(row) {
  editing.value = row
  Object.assign(form, {
    status: row.status,
    rate_limit: row.rate_limit,
    rate_window: row.rate_window,
    total_limit: row.total_limit,
    expires_at: row.expires_at ? new Date(row.expires_at).toISOString().slice(0, 19) : '',
  })
  dialogVisible.value = true
}

async function save() {
  saving.value = true
  try {
    const response = await adminApi.updateApiKey(editing.value.id, form)
    Object.assign(editing.value, response.data)
    dialogVisible.value = false
    ElMessage.success('密钥策略已保存')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

function usagePercent(row) {
  return row.total_limit ? Math.min(100, Math.round(row.used_count * 100 / row.total_limit)) : 0
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString('zh-CN') : '永不过期'
}

onMounted(load)
</script>

<template>
  <div class="manage-page">
    <header class="manage-header">
      <div>
        <h1>密钥管理</h1>
        <p>查看所有用户的开放 API 密钥，并单独调整访问频率、总调用次数和有效期。</p>
      </div>
    </header>

    <section class="manage-toolbar">
      <div class="toolbar-group">
        <el-input v-model.number="filters.user_id" clearable inputmode="numeric" placeholder="用户 ID" style="width: 150px" @keyup.enter="search" />
        <el-select v-model="filters.status" clearable placeholder="全部状态" style="width: 130px" @change="search">
          <el-option label="已启用" :value="1" />
          <el-option label="已停用" :value="0" />
        </el-select>
      </div>
      <div class="toolbar-group">
        <el-button @click="clearFilters">清空</el-button>
        <el-button :loading="loading" @click="load"><RefreshCw :size="14" />刷新</el-button>
      </div>
    </section>

    <section class="manage-table-card">
      <el-table v-loading="loading" :data="list">
        <el-table-column label="密钥" min-width="210">
          <template #default="{ row }">
            <div class="key-cell">
              <span class="key-icon"><KeyRound :size="15" /></span>
              <div><strong>{{ row.name }}</strong><code>{{ row.key_prefix }}••••••••</code></div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="所属用户" width="145">
          <template #default="{ row }">{{ row.username || '未知用户' }} <small>#{{ row.user_id }}</small></template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }"><span class="status-badge" :class="row.status === 1 ? 'success' : 'warning'">{{ row.status === 1 ? '已启用' : '已停用' }}</span></template>
        </el-table-column>
        <el-table-column label="频率" width="135">
          <template #default="{ row }"><span class="mono">{{ row.rate_limit }} / {{ row.rate_window }}s</span></template>
        </el-table-column>
        <el-table-column label="调用额度" min-width="170">
          <template #default="{ row }">
            <div class="usage-cell"><span>{{ row.used_count }} / {{ row.total_limit || '不限' }}</span><el-progress v-if="row.total_limit" :percentage="usagePercent(row)" :show-text="false" :stroke-width="4" /></div>
          </template>
        </el-table-column>
        <el-table-column label="过期时间" width="185">
          <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="105" fixed="right" align="right">
          <template #default="{ row }"><el-button text @click="openEdit(row)"><Pencil :size="14" />策略</el-button></template>
        </el-table-column>
        <template #empty><el-empty :image-size="82" description="没有匹配密钥" /></template>
      </el-table>
    </section>

    <footer class="manage-pagination">
      <el-pagination v-model:current-page="filters.page" :page-size="filters.page_size" :total="total" layout="prev, pager, next, total" @current-change="load" />
    </footer>

    <el-dialog v-model="dialogVisible" title="密钥访问策略" width="min(540px, 92vw)">
      <div v-if="editing" class="editing-key">
        <KeyRound :size="16" /><div><strong>{{ editing.name }}</strong><code>{{ editing.key_prefix }}••••••••</code></div>
      </div>
      <el-form label-position="top">
        <div class="form-grid">
          <el-form-item label="状态">
            <el-select v-model="form.status" style="width: 100%"><el-option label="启用" :value="1" /><el-option label="停用" :value="0" /></el-select>
          </el-form-item>
          <el-form-item label="总调用额度（0 为不限）">
            <el-input-number v-model="form.total_limit" :min="0" :max="1000000000" style="width: 100%" />
          </el-form-item>
          <el-form-item label="窗口最大请求数">
            <el-input-number v-model="form.rate_limit" :min="1" :max="10000" style="width: 100%" />
          </el-form-item>
          <el-form-item label="限流窗口（秒）">
            <el-input-number v-model="form.rate_window" :min="1" :max="86400" style="width: 100%" />
          </el-form-item>
        </div>
        <el-form-item label="过期时间（留空为永不过期）">
          <el-date-picker v-model="form.expires_at" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="永不过期" clearable style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存策略</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.key-cell, .editing-key { display: flex; align-items: center; gap: 9px; }
.key-icon { display: inline-flex; width: 31px; height: 31px; align-items: center; justify-content: center; border-radius: 8px; color: var(--accent); background: var(--accent-soft); }
.key-cell > div, .editing-key > div { display: flex; min-width: 0; flex-direction: column; }
.key-cell strong, .editing-key strong { color: var(--text-primary); font-size: 10px; }
.key-cell code, .editing-key code, small { margin-top: 3px; color: var(--text-tertiary); font-size: 9px; }
.usage-cell { display: grid; max-width: 145px; gap: 6px; font-size: 9px; }
.editing-key { margin-bottom: 17px; padding: 11px; border-radius: 9px; background: var(--panel-muted); }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 560px) { .form-grid { grid-template-columns: 1fr; gap: 0; } }
</style>
