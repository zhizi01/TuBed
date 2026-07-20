<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Code2, Copy, KeyRound, Plus, RefreshCw, RotateCw, Trash2 } from 'lucide-vue-next'
import { ElMessage, ElMessageBox } from 'element-plus'
import { apiKeyApi } from '../../api'

const loading = ref(true)
const creating = ref(false)
const createVisible = ref(false)
const secretVisible = ref(false)
const secret = ref('')
const list = ref([])
const form = reactive({ name: '', expires_in_days: 365 })
const openEndpoint = computed(() => `${location.origin}/api/open/v1/images`)

async function loadKeys() {
  loading.value = true
  try {
    const response = await apiKeyApi.list()
    list.value = response.data.list
  } catch (error) {
    ElMessage.error(error.message || '密钥加载失败')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  Object.assign(form, { name: '', expires_in_days: 365 })
  createVisible.value = true
}

async function createKey() {
  if (!form.name.trim()) {
    ElMessage.warning('请输入密钥名称')
    return
  }
  creating.value = true
  try {
    const response = await apiKeyApi.create(form)
    secret.value = response.data.secret
    createVisible.value = false
    secretVisible.value = true
    ElMessage.success('API 密钥创建成功')
    loadKeys()
  } catch (error) {
    ElMessage.error(error.message || '创建失败')
  } finally {
    creating.value = false
  }
}

async function toggleStatus(row) {
  const nextStatus = row.status === 1 ? 0 : 1
  try {
    await apiKeyApi.update(row.id, { status: nextStatus })
    row.status = nextStatus
    ElMessage.success(nextStatus ? '密钥已启用' : '密钥已停用')
  } catch (error) {
    ElMessage.error(error.message || '状态更新失败')
  }
}

async function regenerate(row) {
  try {
    await ElMessageBox.confirm(
      '重置后旧密钥立即失效，使用旧密钥的程序将无法访问。',
      '重置 API 密钥',
      { type: 'warning', confirmButtonText: '继续重置', cancelButtonText: '取消' },
    )
    const response = await apiKeyApi.regenerate(row.id)
    secret.value = response.data.secret
    secretVisible.value = true
    loadKeys()
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.message || '重置失败')
  }
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(`确定删除密钥“${row.name}”吗？`, '删除密钥', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    })
    await apiKeyApi.remove(row.id)
    ElMessage.success('密钥已删除')
    loadKeys()
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.message || '删除失败')
  }
}

async function copy(value, message = '已复制') {
  try {
    await navigator.clipboard.writeText(value)
    ElMessage.success(message)
  } catch {
    ElMessage.error('复制失败')
  }
}

function usagePercent(row) {
  if (!row.total_limit) return 0
  return Math.min(100, Math.round(row.used_count * 100 / row.total_limit))
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString('zh-CN') : '永不过期'
}

onMounted(loadKeys)
</script>

<template>
  <div class="manage-page">
    <header class="manage-header">
      <div>
        <h1>开放 API</h1>
        <p>为外部程序创建独立密钥。每个密钥具有访问频率、总调用次数和过期时间限制。</p>
      </div>
      <div class="manage-actions">
        <el-button type="primary" @click="openCreate"><Plus :size="15" />创建密钥</el-button>
      </div>
    </header>

    <section class="endpoint-card panel-card">
      <div class="endpoint-icon"><Code2 :size="18" /></div>
      <div>
        <strong>图片上传端点</strong>
        <code>{{ openEndpoint }}</code>
        <span>请求头使用 <b>X-API-Key</b>，请求体使用 multipart/form-data 的 file 字段。</span>
      </div>
      <el-button @click="copy(openEndpoint, '接口地址已复制')"><Copy :size="14" />复制</el-button>
    </section>

    <section class="manage-toolbar">
      <span class="key-summary">共 {{ list.length }} 个密钥，原始密钥仅在创建或重置时显示。</span>
      <el-button :loading="loading" @click="loadKeys"><RefreshCw :size="14" />刷新</el-button>
    </section>

    <section class="manage-table-card">
      <el-table v-loading="loading" :data="list">
        <el-table-column label="密钥" min-width="220">
          <template #default="{ row }">
            <div class="key-cell">
              <span class="key-icon"><KeyRound :size="16" /></span>
              <div><strong>{{ row.name }}</strong><code>{{ row.key_prefix }}••••••••</code></div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="105">
          <template #default="{ row }">
            <button
              class="status-badge"
              :class="row.status === 1 ? 'success' : 'warning'"
              type="button"
              @click="toggleStatus(row)"
            >
              {{ row.status === 1 ? '已启用' : '已停用' }}
            </button>
          </template>
        </el-table-column>
        <el-table-column label="频率限制" width="135">
          <template #default="{ row }">
            <span class="mono">{{ row.rate_limit }} 次 / {{ row.rate_window }} 秒</span>
          </template>
        </el-table-column>
        <el-table-column label="调用额度" min-width="190">
          <template #default="{ row }">
            <div class="usage-cell">
              <span>{{ row.used_count }} / {{ row.total_limit || '不限' }}</span>
              <el-progress v-if="row.total_limit" :percentage="usagePercent(row)" :show-text="false" :stroke-width="4" />
            </div>
          </template>
        </el-table-column>
        <el-table-column label="过期时间" width="185">
          <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="170" fixed="right" align="right">
          <template #default="{ row }">
            <el-button text @click="regenerate(row)"><RotateCw :size="14" />重置</el-button>
            <el-button text type="danger" @click="remove(row)"><Trash2 :size="14" />删除</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty :image-size="82" description="还没有 API 密钥">
            <el-button type="primary" @click="openCreate"><Plus :size="14" />创建密钥</el-button>
          </el-empty>
        </template>
      </el-table>
    </section>

    <el-dialog v-model="createVisible" title="创建 API 密钥" width="min(480px, 92vw)">
      <el-form label-position="top">
        <el-form-item label="密钥名称" required>
          <el-input v-model.trim="form.name" maxlength="60" placeholder="例如：PicGo 桌面端" />
        </el-form-item>
        <el-form-item label="有效期">
          <el-select v-model="form.expires_in_days" style="width: 100%">
            <el-option label="30 天" :value="30" />
            <el-option label="90 天" :value="90" />
            <el-option label="1 年" :value="365" />
            <el-option label="3 年" :value="1095" />
          </el-select>
        </el-form-item>
        <p class="dialog-tip">访问频率和总额度由管理员的接口策略决定，创建后管理员仍可单独调整。</p>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="createKey">创建密钥</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="secretVisible" title="请保存 API 密钥" width="min(560px, 92vw)" :close-on-click-modal="false">
      <el-alert title="关闭后将无法再次查看完整密钥" type="warning" :closable="false" show-icon />
      <div class="secret-box">
        <code>{{ secret }}</code>
        <el-button type="primary" @click="copy(secret, 'API 密钥已复制')"><Copy :size="14" />复制密钥</el-button>
      </div>
      <template #footer>
        <el-button type="primary" @click="secretVisible = false">我已安全保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.endpoint-card {
  display: flex;
  align-items: center;
  gap: 13px;
}
.endpoint-icon,
.key-icon {
  display: inline-flex;
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  color: var(--accent);
  background: var(--accent-soft);
}
.endpoint-card > div:nth-child(2) {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
}
.endpoint-card strong {
  font-size: 11px;
}
.endpoint-card code {
  overflow: hidden;
  margin-top: 5px;
  color: var(--text-primary);
  font-size: 11px;
  text-overflow: ellipsis;
}
.endpoint-card span,
.key-summary,
.dialog-tip {
  margin-top: 5px;
  color: var(--text-tertiary);
  font-size: 9px;
}
.key-summary { margin: 0; padding-left: 5px; }
.key-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.key-cell > div {
  display: flex;
  min-width: 0;
  flex-direction: column;
}
.key-cell strong {
  color: var(--text-primary);
  font-size: 11px;
}
.key-cell code {
  margin-top: 4px;
  color: var(--text-tertiary);
  font-size: 9px;
}
.status-badge {
  border: 0;
}
.usage-cell {
  display: grid;
  gap: 6px;
  max-width: 150px;
  font-size: 10px;
}
.secret-box {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--panel-muted);
}
.secret-box code {
  overflow-wrap: anywhere;
  flex: 1;
  color: var(--text-primary);
  font-size: 11px;
}
@media (max-width: 620px) {
  .endpoint-card { align-items: flex-start; flex-wrap: wrap; }
  .endpoint-card > .el-button { width: 100%; }
  .secret-box { align-items: stretch; flex-direction: column; }
}
</style>
