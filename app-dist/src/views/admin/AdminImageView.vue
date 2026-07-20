<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Copy, RefreshCw, Search, Trash2 } from 'lucide-vue-next'
import { ElMessage, ElMessageBox } from 'element-plus'
import { adminApi } from '../../api'

const loading = ref(true)
const list = ref([])
const total = ref(0)
const filters = reactive({ keyword: '', user_id: '', page: 1, page_size: 20 })

function formatBytes(bytes = 0) {
  if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`
  return `${(bytes / 1024).toFixed(1)} KB`
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString('zh-CN') : '-'
}

async function load() {
  loading.value = true
  try {
    const response = await adminApi.images(filters)
    list.value = response.data.list
    total.value = response.data.total
  } catch (error) {
    ElMessage.error(error.message || '图片列表加载失败')
  } finally {
    loading.value = false
  }
}

function search() {
  filters.page = 1
  load()
}

function clearFilters() {
  Object.assign(filters, { keyword: '', user_id: '', page: 1 })
  load()
}

async function copyUrl(url) {
  try {
    await navigator.clipboard.writeText(url)
    ElMessage.success('图片地址已复制')
  } catch {
    ElMessage.error('复制失败')
  }
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(
      `管理员删除“${row.title || row.original_name}”后无法恢复。`,
      '删除图片',
      { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' },
    )
    await adminApi.removeImage(row.id)
    ElMessage.success('图片已删除')
    load()
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.message || '删除失败')
  }
}

onMounted(load)
</script>

<template>
  <div class="manage-page">
    <header class="manage-header">
      <div>
        <h1>图片管理</h1>
        <p>查看全站图片归属和存储信息，并处理不合规内容。</p>
      </div>
    </header>

    <section class="manage-toolbar">
      <div class="toolbar-group">
        <el-input v-model.trim="filters.keyword" clearable placeholder="搜索标题或文件名" style="width: 230px" @keyup.enter="search">
          <template #prefix><Search :size="14" /></template>
        </el-input>
        <el-input v-model.number="filters.user_id" clearable inputmode="numeric" placeholder="用户 ID" style="width: 130px" @keyup.enter="search" />
      </div>
      <div class="toolbar-group">
        <el-button @click="clearFilters">清空</el-button>
        <el-button :loading="loading" @click="load"><RefreshCw :size="14" />刷新</el-button>
      </div>
    </section>

    <section class="manage-table-card">
      <el-table v-loading="loading" :data="list">
        <el-table-column label="图片" min-width="260">
          <template #default="{ row }">
            <div class="admin-image-cell">
              <img :src="row.url" :alt="row.title || row.original_name" loading="lazy">
              <div><strong>{{ row.title || row.original_name }}</strong><span>{{ row.original_name }}</span></div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="所属用户" width="145">
          <template #default="{ row }"><span>{{ row.username || '未知用户' }}</span><small class="user-id">#{{ row.user_id }}</small></template>
        </el-table-column>
        <el-table-column label="规格" width="145">
          <template #default="{ row }"><span class="mono">{{ row.width }} × {{ row.height }}</span><small class="format">{{ row.extension.toUpperCase() }}</small></template>
        </el-table-column>
        <el-table-column label="大小" width="100">
          <template #default="{ row }">{{ formatBytes(row.file_size) }}</template>
        </el-table-column>
        <el-table-column label="上传时间" width="180">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="165" fixed="right" align="right">
          <template #default="{ row }">
            <el-button text @click="copyUrl(row.url)"><Copy :size="14" />复制</el-button>
            <el-button text type="danger" @click="remove(row)"><Trash2 :size="14" />删除</el-button>
          </template>
        </el-table-column>
        <template #empty><el-empty :image-size="82" description="没有匹配图片" /></template>
      </el-table>
    </section>

    <footer class="manage-pagination">
      <el-pagination v-model:current-page="filters.page" :page-size="filters.page_size" :total="total" layout="prev, pager, next, total" @current-change="load" />
    </footer>
  </div>
</template>

<style scoped>
.admin-image-cell {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 10px;
}
.admin-image-cell img {
  width: 44px;
  height: 38px;
  flex: 0 0 auto;
  border-radius: 7px;
  object-fit: cover;
  background: var(--panel-muted);
}
.admin-image-cell > div { display: flex; min-width: 0; flex-direction: column; }
.admin-image-cell strong, .admin-image-cell span {
  overflow: hidden;
  max-width: 300px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.admin-image-cell strong { color: var(--text-primary); font-size: 10px; }
.admin-image-cell span, .user-id, .format { margin-top: 3px; color: var(--text-tertiary); font-size: 9px; }
.user-id, .format { display: block; }
</style>
