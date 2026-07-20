<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  ArrowUpRight,
  FolderOpen,
  HardDrive,
  Image as ImageIcon,
  Images,
  KeyRound,
  Upload,
} from 'lucide-vue-next'
import { imageApi, statsApi } from '../api'
import { useUserStore } from '../stores/user'

const userStore = useUserStore()
const loading = ref(true)
const stats = ref(null)
const recentImages = ref([])

const storagePercent = computed(() => stats.value?.storage?.percent || 0)

function formatBytes(bytes = 0) {
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`
  if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`
  return `${(bytes / 1024).toFixed(1)} KB`
}

onMounted(async () => {
  try {
    const [statsResponse, imageResponse] = await Promise.all([
      statsApi.overview(),
      imageApi.list({ page: 1, page_size: 4 }),
    ])
    stats.value = statsResponse.data
    recentImages.value = imageResponse.data.list
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="manage-page dashboard-page">
    <header class="manage-header">
      <div>
        <h1>晚上好，{{ userStore.user?.username }}</h1>
        <p>这里汇总了图片空间的最新状态和常用入口。</p>
      </div>
      <div class="manage-actions">
        <el-button v-if="userStore.isAdmin" @click="$router.push('/admin')">
          管理概览<ArrowUpRight :size="15" />
        </el-button>
        <el-button type="primary" @click="$router.push('/images?upload=1')">
          <Upload :size="15" />上传图片
        </el-button>
      </div>
    </header>

    <el-skeleton :loading="loading" animated :rows="5">
      <template #default>
        <section class="metric-grid">
          <article class="metric-card">
            <span class="metric-icon"><Images :size="17" /></span>
            <span class="metric-label">图片总数</span>
            <strong class="metric-value">{{ stats?.image_count || 0 }}</strong>
          </article>
          <article class="metric-card">
            <span class="metric-icon"><FolderOpen :size="17" /></span>
            <span class="metric-label">相册数量</span>
            <strong class="metric-value">{{ stats?.album_count || 0 }}</strong>
          </article>
          <article class="metric-card">
            <span class="metric-icon"><Upload :size="17" /></span>
            <span class="metric-label">近 7 天上传</span>
            <strong class="metric-value">{{ stats?.recent_7_days || 0 }}</strong>
          </article>
          <article class="metric-card">
            <span class="metric-icon"><HardDrive :size="17" /></span>
            <span class="metric-label">已用空间</span>
            <strong class="metric-value">{{ formatBytes(stats?.storage?.used) }}</strong>
          </article>
        </section>

        <section class="dashboard-grid">
          <article class="panel-card storage-card">
            <div class="panel-heading">
              <div>
                <h2>存储配额</h2>
                <p>当前空间使用情况</p>
              </div>
              <HardDrive :size="19" />
            </div>
            <div class="storage-amount">
              <strong>{{ formatBytes(stats?.storage?.used) }}</strong>
              <span>/ {{ formatBytes(stats?.storage?.quota) }}</span>
            </div>
            <el-progress
              :percentage="Math.min(100, storagePercent)"
              :stroke-width="7"
              :show-text="false"
            />
            <div class="storage-meta">
              <span>已使用 {{ storagePercent }}%</span>
              <span>剩余 {{ formatBytes(stats?.storage?.remaining) }}</span>
            </div>
          </article>

          <article class="panel-card quick-card">
            <div class="panel-heading">
              <div>
                <h2>快捷入口</h2>
                <p>继续处理常用任务</p>
              </div>
            </div>
            <div class="quick-links">
              <RouterLink to="/images?upload=1"><Upload :size="17" /><span>上传图片<small>生成公开直链</small></span></RouterLink>
              <RouterLink to="/albums"><FolderOpen :size="17" /><span>整理相册<small>归档图片资产</small></span></RouterLink>
              <RouterLink to="/api-keys"><KeyRound :size="17" /><span>开放 API<small>管理调用密钥</small></span></RouterLink>
            </div>
          </article>
        </section>

        <section class="panel-card recent-panel">
          <div class="panel-heading">
            <div>
              <h2>最近上传</h2>
              <p>最新进入图片库的内容</p>
            </div>
            <el-button text @click="$router.push('/images')">查看全部<ArrowUpRight :size="14" /></el-button>
          </div>
          <div v-if="recentImages.length" class="recent-grid">
            <article v-for="image in recentImages" :key="image.id" class="recent-image">
              <img :src="image.url" :alt="image.title || image.original_name" loading="lazy">
              <div>
                <strong>{{ image.title || image.original_name }}</strong>
                <span>{{ image.width }} × {{ image.height }}</span>
              </div>
            </article>
          </div>
          <el-empty v-else :image-size="72" description="还没有上传图片">
            <el-button type="primary" @click="$router.push('/images?upload=1')">
              <ImageIcon :size="15" />上传第一张图片
            </el-button>
          </el-empty>
        </section>
      </template>
    </el-skeleton>
  </div>
</template>

<style scoped>
.dashboard-grid {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 12px;
}
.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}
.panel-heading h2 {
  margin: 0;
  font-size: 13px;
  font-weight: 650;
}
.panel-heading p {
  margin: 4px 0 0;
  color: var(--text-tertiary);
  font-size: 10px;
}
.panel-heading > svg {
  color: var(--text-tertiary);
}
.storage-amount {
  margin: 26px 0 13px;
}
.storage-amount strong {
  font-size: 23px;
  letter-spacing: -0.5px;
}
.storage-amount span {
  margin-left: 5px;
  color: var(--text-tertiary);
  font-size: 11px;
}
.storage-meta {
  display: flex;
  justify-content: space-between;
  margin-top: 9px;
  color: var(--text-tertiary);
  font-size: 9px;
}
.quick-links {
  display: grid;
  gap: 7px;
  margin-top: 15px;
}
.quick-links a {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 9px;
  border-radius: 9px;
  color: var(--text-secondary);
  text-decoration: none;
  transition: background-color 160ms ease, color 160ms ease;
}
.quick-links a:hover {
  color: var(--text-primary);
  background: var(--panel-muted);
}
.quick-links a > svg {
  color: var(--accent);
}
.quick-links span {
  display: flex;
  flex-direction: column;
  font-size: 11px;
  font-weight: 600;
}
.quick-links small {
  margin-top: 2px;
  color: var(--text-tertiary);
  font-size: 9px;
  font-weight: 400;
}
.recent-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-top: 16px;
}
.recent-image {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--panel-muted);
}
.recent-image img {
  display: block;
  width: 100%;
  height: 116px;
  object-fit: cover;
}
.recent-image > div {
  display: flex;
  min-width: 0;
  flex-direction: column;
  padding: 9px;
}
.recent-image strong {
  overflow: hidden;
  font-size: 10px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.recent-image span {
  margin-top: 3px;
  color: var(--text-tertiary);
  font-size: 9px;
}
@media (max-width: 900px) {
  .dashboard-grid { grid-template-columns: 1fr; }
  .recent-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 520px) {
  .recent-grid { grid-template-columns: 1fr; }
}
</style>
