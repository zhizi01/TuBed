<script setup>
import { onMounted, reactive, ref } from 'vue'
import { FolderOpen, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-vue-next'
import { ElMessage, ElMessageBox } from 'element-plus'
import { albumApi } from '../../api'

const loading = ref(true)
const saving = ref(false)
const dialogVisible = ref(false)
const list = ref([])
const editingId = ref(null)
const form = reactive({ name: '', description: '' })

async function loadAlbums() {
  loading.value = true
  try {
    const response = await albumApi.list()
    list.value = response.data.list
  } catch (error) {
    ElMessage.error(error.message || '相册加载失败')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', description: '' })
  dialogVisible.value = true
}

function openEdit(album) {
  editingId.value = album.id
  Object.assign(form, { name: album.name, description: album.description || '' })
  dialogVisible.value = true
}

async function save() {
  if (!form.name.trim()) {
    ElMessage.warning('请输入相册名称')
    return
  }
  saving.value = true
  try {
    if (editingId.value) await albumApi.update(editingId.value, form)
    else await albumApi.create(form)
    ElMessage.success(editingId.value ? '相册已更新' : '相册已创建')
    dialogVisible.value = false
    loadAlbums()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function remove(album) {
  try {
    await ElMessageBox.confirm(
      `删除“${album.name}”后，其中图片会移至未分类。`,
      '删除相册',
      { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' },
    )
    await albumApi.remove(album.id)
    ElMessage.success('相册已删除')
    loadAlbums()
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.message || '删除失败')
  }
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString('zh-CN', { dateStyle: 'medium' }) : '-'
}

onMounted(loadAlbums)
</script>

<template>
  <div class="manage-page">
    <header class="manage-header">
      <div>
        <h1>相册管理</h1>
        <p>按照项目或用途归档图片；删除相册不会删除其中图片。</p>
      </div>
      <div class="manage-actions">
        <el-button v-permission="'albums.manage'" type="primary" @click="openCreate"><Plus :size="15" />新建相册</el-button>
      </div>
    </header>

    <section class="manage-toolbar">
      <span class="album-summary">共 {{ list.length }} 个相册</span>
      <el-button :loading="loading" @click="loadAlbums"><RefreshCw :size="14" />刷新</el-button>
    </section>

    <section class="manage-table-card">
      <el-table v-loading="loading" :data="list">
        <el-table-column label="相册" min-width="240">
          <template #default="{ row }">
            <div class="album-cell">
              <span class="album-icon"><FolderOpen :size="17" /></span>
              <div><strong>{{ row.name }}</strong><span>{{ row.description || '暂无说明' }}</span></div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="image_count" label="图片数量" width="120">
          <template #default="{ row }">{{ row.image_count }} 张</template>
        </el-table-column>
        <el-table-column label="更新时间" width="190">
          <template #default="{ row }">{{ formatDate(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" align="right">
          <template #default="{ row }">
            <el-button text @click="openEdit(row)"><Pencil :size="14" />编辑</el-button>
            <el-button text type="danger" @click="remove(row)"><Trash2 :size="14" />删除</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty :image-size="82" description="还没有创建相册">
            <el-button type="primary" @click="openCreate"><Plus :size="14" />新建相册</el-button>
          </el-empty>
        </template>
      </el-table>
    </section>

    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑相册' : '新建相册'" width="min(480px, 92vw)">
      <el-form label-position="top">
        <el-form-item label="相册名称" required>
          <el-input v-model.trim="form.name" maxlength="80" show-word-limit placeholder="例如：产品素材" />
        </el-form-item>
        <el-form-item label="相册说明">
          <el-input v-model.trim="form.description" type="textarea" :rows="4" maxlength="500" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.album-summary {
  padding-left: 5px;
  color: var(--text-secondary);
  font-size: 11px;
}
.album-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.album-icon {
  display: inline-flex;
  width: 32px;
  height: 32px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  color: var(--accent);
  background: var(--accent-soft);
}
.album-cell > div {
  display: flex;
  min-width: 0;
  flex-direction: column;
}
.album-cell strong {
  color: var(--text-primary);
  font-size: 11px;
}
.album-cell span {
  overflow: hidden;
  max-width: 420px;
  margin-top: 3px;
  color: var(--text-tertiary);
  font-size: 9px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
