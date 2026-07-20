<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Copy,
  Image as ImageIcon,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  UploadCloud,
  X,
} from 'lucide-vue-next'
import { ElMessage, ElMessageBox } from 'element-plus'
import { albumApi, imageApi } from '../../api'
import { useUserStore } from '../../stores/user'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const loading = ref(true)
const list = ref([])
const total = ref(0)
const albums = ref([])
const uploadVisible = ref(false)
const editVisible = ref(false)
const uploadLoading = ref(false)
const uploadProgress = ref(0)
const fileInput = ref()
const selectedFile = ref(null)
const previewUrl = ref('')
const editing = ref(null)
const filters = reactive({
  keyword: '',
  album_id: '',
  mime_type: '',
  page: 1,
  page_size: 24,
})
const uploadForm = reactive({ title: '', album_id: '' })
const editForm = reactive({ title: '', album_id: '' })

function formatBytes(bytes = 0) {
  if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`
  return `${Math.max(0.1, bytes / 1024).toFixed(1)} KB`
}

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString('zh-CN') : '-'
}

async function loadImages() {
  loading.value = true
  try {
    const response = await imageApi.list(filters)
    list.value = response.data.list
    total.value = response.data.total
  } catch (error) {
    ElMessage.error(error.message || '图片列表加载失败')
  } finally {
    loading.value = false
  }
}

async function loadAlbums() {
  try {
    const response = await albumApi.list()
    albums.value = response.data.list
  } catch {
    albums.value = []
  }
}

function search() {
  filters.page = 1
  loadImages()
}

function clearFilters() {
  Object.assign(filters, { keyword: '', album_id: '', mime_type: '', page: 1 })
  loadImages()
}

function openUpload() {
  uploadVisible.value = true
}

function chooseFile() {
  fileInput.value?.click()
}

function setFile(file) {
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'].includes(file.type)) {
    ElMessage.warning('仅支持 JPG、PNG、GIF、WebP 和 AVIF')
    return
  }
  if (file.size > 20 * 1024 * 1024) {
    ElMessage.warning('单张图片不能超过 20 MB')
    return
  }

  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  selectedFile.value = file
  previewUrl.value = URL.createObjectURL(file)
  if (!uploadForm.title) uploadForm.title = file.name.replace(/\.[^.]+$/, '')
}

function onFileChange(event) {
  setFile(event.target.files?.[0])
  event.target.value = ''
}

function onDrop(event) {
  setFile(event.dataTransfer.files?.[0])
}

function clearFile() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  selectedFile.value = null
  previewUrl.value = ''
}

async function submitUpload() {
  if (!selectedFile.value) {
    ElMessage.warning('请先选择图片')
    return
  }
  uploadLoading.value = true
  uploadProgress.value = 0
  try {
    const data = new FormData()
    data.append('file', selectedFile.value)
    if (uploadForm.title) data.append('title', uploadForm.title)
    if (uploadForm.album_id) data.append('album_id', uploadForm.album_id)
    await imageApi.upload(data, (event) => {
      if (event.total) uploadProgress.value = Math.round(event.loaded * 100 / event.total)
    })
    ElMessage.success('图片上传成功')
    uploadVisible.value = false
    clearFile()
    Object.assign(uploadForm, { title: '', album_id: '' })
    await Promise.all([loadImages(), userStore.fetchMe()])
  } catch (error) {
    ElMessage.error(error.message || '上传失败')
  } finally {
    uploadLoading.value = false
  }
}

function openEdit(image) {
  editing.value = image
  editForm.title = image.title || ''
  editForm.album_id = image.album_id || ''
  editVisible.value = true
}

async function saveEdit() {
  try {
    const response = await imageApi.update(editing.value.id, {
      title: editForm.title,
      album_id: editForm.album_id || null,
    })
    Object.assign(editing.value, response.data)
    editVisible.value = false
    ElMessage.success('图片信息已更新')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  }
}

async function removeImage(image) {
  try {
    await ElMessageBox.confirm(
      `删除“${image.title || image.original_name}”后无法恢复，是否继续？`,
      '删除图片',
      { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' },
    )
    await imageApi.remove(image.id)
    ElMessage.success('图片已删除')
    await Promise.all([loadImages(), userStore.fetchMe()])
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.message || '删除失败')
  }
}

async function copyUrl(url) {
  try {
    await navigator.clipboard.writeText(url)
    ElMessage.success('图片地址已复制')
  } catch {
    ElMessage.error('复制失败，请手动复制')
  }
}

onMounted(async () => {
  await Promise.all([loadImages(), loadAlbums()])
  if (route.query.upload === '1') {
    openUpload()
    router.replace({ query: {} })
  }
})

onBeforeUnmount(clearFile)
</script>

<template>
  <div class="manage-page image-page">
    <header class="manage-header">
      <div>
        <h1>图片库</h1>
        <p>上传、筛选和整理图片，复制可直接访问的公开地址。</p>
      </div>
      <div class="manage-actions">
        <el-button v-permission="'images.manage'" type="primary" @click="openUpload"><Plus :size="15" />上传图片</el-button>
      </div>
    </header>

    <section class="manage-toolbar">
      <div class="toolbar-group">
        <el-input
          v-model.trim="filters.keyword"
          clearable
          placeholder="搜索标题或文件名"
          style="width: 230px"
          @keyup.enter="search"
        >
          <template #prefix><Search :size="14" /></template>
        </el-input>
        <el-select v-model="filters.album_id" clearable placeholder="全部相册" style="width: 150px" @change="search">
          <el-option label="未分类" :value="0" />
          <el-option v-for="album in albums" :key="album.id" :label="album.name" :value="album.id" />
        </el-select>
        <el-select v-model="filters.mime_type" clearable placeholder="全部格式" style="width: 135px" @change="search">
          <el-option label="JPEG" value="image/jpeg" />
          <el-option label="PNG" value="image/png" />
          <el-option label="WebP" value="image/webp" />
          <el-option label="GIF" value="image/gif" />
          <el-option label="AVIF" value="image/avif" />
        </el-select>
      </div>
      <div class="toolbar-group">
        <el-button @click="clearFilters">清空</el-button>
        <el-button :loading="loading" @click="loadImages"><RefreshCw :size="14" />刷新</el-button>
      </div>
    </section>

    <el-skeleton :loading="loading" animated>
      <template #template>
        <div class="image-grid">
          <el-skeleton-item v-for="item in 8" :key="item" variant="image" class="image-skeleton" />
        </div>
      </template>
      <template #default>
        <section v-if="list.length" class="image-grid" aria-live="polite">
          <article v-for="image in list" :key="image.id" class="image-card">
            <div class="image-preview">
              <img :src="image.url" :alt="image.title || image.original_name" loading="lazy">
              <div class="image-overlay">
                <button type="button" aria-label="复制图片地址" @click="copyUrl(image.url)"><Copy :size="15" /></button>
                <button type="button" aria-label="编辑图片" @click="openEdit(image)"><Pencil :size="15" /></button>
                <button type="button" aria-label="删除图片" class="danger" @click="removeImage(image)"><Trash2 :size="15" /></button>
              </div>
            </div>
            <div class="image-info">
              <strong :title="image.title || image.original_name">{{ image.title || image.original_name }}</strong>
              <span>{{ image.width }} × {{ image.height }} · {{ formatBytes(image.file_size) }}</span>
              <span>{{ image.extension.toUpperCase() }} · {{ formatDate(image.created_at) }}</span>
            </div>
          </article>
        </section>
        <el-empty v-else :image-size="92" description="没有符合条件的图片">
          <el-button type="primary" @click="openUpload"><UploadCloud :size="15" />上传图片</el-button>
        </el-empty>
      </template>
    </el-skeleton>

    <footer v-if="total > filters.page_size" class="manage-pagination">
      <el-pagination
        v-model:current-page="filters.page"
        :page-size="filters.page_size"
        :total="total"
        layout="prev, pager, next, total"
        @current-change="loadImages"
      />
    </footer>

    <el-dialog v-model="uploadVisible" title="上传图片" width="min(560px, 92vw)" @closed="clearFile">
      <input ref="fileInput" hidden type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" @change="onFileChange">
      <div
        class="upload-dropzone"
        :class="{ 'has-file': selectedFile }"
        role="button"
        tabindex="0"
        @click="chooseFile"
        @keydown.enter="chooseFile"
        @dragover.prevent
        @drop.prevent="onDrop"
      >
        <template v-if="previewUrl">
          <img :src="previewUrl" alt="待上传图片预览">
          <button type="button" aria-label="移除所选图片" @click.stop="clearFile"><X :size="15" /></button>
        </template>
        <template v-else>
          <span class="upload-icon"><UploadCloud :size="24" /></span>
          <strong>拖放图片到这里，或点击选择</strong>
          <small>JPG、PNG、GIF、WebP、AVIF，最大 20 MB</small>
        </template>
      </div>
      <el-progress v-if="uploadLoading" :percentage="uploadProgress" :stroke-width="6" />
      <el-form label-position="top" class="upload-form">
        <el-form-item label="图片标题">
          <el-input v-model.trim="uploadForm.title" maxlength="100" placeholder="选填" />
        </el-form-item>
        <el-form-item label="目标相册">
          <el-select v-model="uploadForm.album_id" clearable placeholder="未分类" style="width: 100%">
            <el-option v-for="album in albums" :key="album.id" :label="album.name" :value="album.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="uploadVisible = false">取消</el-button>
        <el-button type="primary" :loading="uploadLoading" @click="submitUpload">开始上传</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="editVisible" title="编辑图片" width="min(480px, 92vw)">
      <el-form label-position="top">
        <el-form-item label="图片标题">
          <el-input v-model.trim="editForm.title" maxlength="100" />
        </el-form-item>
        <el-form-item label="所属相册">
          <el-select v-model="editForm.album_id" clearable placeholder="未分类" style="width: 100%">
            <el-option v-for="album in albums" :key="album.id" :label="album.name" :value="album.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" @click="saveEdit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 12px;
}
.image-skeleton {
  width: 100%;
  height: 240px;
  border-radius: 12px;
}
.image-card {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--panel-bg);
  transition: border-color 180ms ease, box-shadow 180ms ease;
}
.image-card:hover {
  border-color: var(--border-strong);
  box-shadow: 0 9px 24px rgba(25, 28, 40, 0.07);
}
.image-preview {
  position: relative;
  overflow: hidden;
  aspect-ratio: 4 / 3;
  background:
    linear-gradient(45deg, var(--panel-muted) 25%, transparent 25%),
    linear-gradient(-45deg, var(--panel-muted) 25%, transparent 25%);
  background-size: 18px 18px;
}
.image-preview img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.image-overlay {
  position: absolute;
  right: 8px;
  bottom: 8px;
  display: flex;
  gap: 5px;
  opacity: 0;
  transform: translateY(4px);
  transition: opacity 170ms ease, transform 170ms ease;
}
.image-card:hover .image-overlay,
.image-card:focus-within .image-overlay {
  opacity: 1;
  transform: translateY(0);
}
.image-overlay button,
.upload-dropzone > button {
  display: inline-flex;
  width: 30px;
  height: 30px;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: 8px;
  color: white;
  background: rgba(18, 19, 24, 0.72);
  backdrop-filter: blur(8px);
}
.image-overlay button.danger:hover {
  background: var(--danger);
}
.image-info {
  display: flex;
  min-width: 0;
  flex-direction: column;
  padding: 11px 12px 12px;
}
.image-info strong {
  overflow: hidden;
  font-size: 11px;
  font-weight: 620;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.image-info span {
  margin-top: 4px;
  color: var(--text-tertiary);
  font-size: 9px;
}
.upload-dropzone {
  position: relative;
  display: flex;
  min-height: 220px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  overflow: hidden;
  border: 1px dashed var(--border-strong);
  border-radius: 11px;
  background: var(--panel-muted);
  transition: border-color 170ms ease, background-color 170ms ease;
}
.upload-dropzone:hover,
.upload-dropzone:focus-visible {
  border-color: var(--accent);
  background: var(--accent-soft);
  outline: none;
}
.upload-dropzone.has-file {
  border-style: solid;
}
.upload-dropzone img {
  display: block;
  width: 100%;
  max-height: 320px;
  object-fit: contain;
}
.upload-dropzone > button {
  position: absolute;
  top: 9px;
  right: 9px;
}
.upload-icon {
  display: inline-flex;
  width: 45px;
  height: 45px;
  align-items: center;
  justify-content: center;
  margin-bottom: 13px;
  border-radius: 12px;
  color: var(--accent);
  background: var(--accent-soft);
}
.upload-dropzone strong {
  font-size: 12px;
}
.upload-dropzone small {
  margin-top: 6px;
  color: var(--text-tertiary);
  font-size: 9px;
}
.upload-form {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 12px;
  margin-top: 16px;
}
@media (max-width: 600px) {
  .image-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
  .image-overlay { opacity: 1; transform: none; }
  .upload-form { grid-template-columns: 1fr; gap: 0; }
}
@media (max-width: 420px) {
  .image-grid { grid-template-columns: 1fr; }
}
</style>
