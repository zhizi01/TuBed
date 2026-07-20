<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import * as echarts from 'echarts/core'
import { BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { Activity, Database, Image as ImageIcon, KeyRound, RefreshCw, Users } from 'lucide-vue-next'
import { ElMessage } from 'element-plus'
import { adminApi } from '../../api'

echarts.use([BarChart, GridComponent, TooltipComponent, CanvasRenderer])

const loading = ref(true)
const data = ref(null)
const chartRef = ref()
let chart

function formatBytes(bytes = 0) {
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`
  if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`
  return `${(bytes / 1024).toFixed(1)} KB`
}

function renderChart() {
  if (!chartRef.value || !data.value) return
  chart ||= echarts.init(chartRef.value)
  const styles = getComputedStyle(document.documentElement)
  const accent = styles.getPropertyValue('--accent').trim()
  const text = styles.getPropertyValue('--text-secondary').trim()
  const border = styles.getPropertyValue('--border').trim()

  chart.setOption({
    animationDuration: 380,
    grid: { top: 16, right: 12, bottom: 28, left: 44 },
    tooltip: { trigger: 'axis' },
    xAxis: {
      type: 'category',
      data: ['用户', '图片', 'API 密钥'],
      axisLine: { lineStyle: { color: border } },
      axisTick: { show: false },
      axisLabel: { color: text, fontSize: 10 },
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      splitLine: { lineStyle: { color: border, type: 'dashed' } },
      axisLabel: { color: text, fontSize: 9 },
    },
    series: [{
      type: 'bar',
      barWidth: 28,
      data: [data.value.users.total, data.value.images.total, data.value.api_keys.total],
      itemStyle: { color: accent, borderRadius: [5, 5, 0, 0] },
    }],
  })
}

async function load() {
  loading.value = true
  try {
    const response = await adminApi.overview()
    data.value = response.data
    await nextTick()
    renderChart()
  } catch (error) {
    ElMessage.error(error.message || '管理概览加载失败')
  } finally {
    loading.value = false
  }
}

function resize() {
  chart?.resize()
}

onMounted(() => {
  load()
  window.addEventListener('resize', resize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resize)
  chart?.dispose()
})
</script>

<template>
  <div class="manage-page">
    <header class="manage-header">
      <div>
        <h1>管理概览</h1>
        <p>查看全站用户、图片、开放 API 和存储资源状态。</p>
      </div>
      <el-button :loading="loading" @click="load"><RefreshCw :size="14" />刷新数据</el-button>
    </header>

    <el-skeleton :loading="loading" animated :rows="6">
      <template #default>
        <section class="metric-grid">
          <article class="metric-card">
            <span class="metric-icon"><Users :size="17" /></span>
            <span class="metric-label">注册用户</span>
            <strong class="metric-value">{{ data?.users.total || 0 }}</strong>
          </article>
          <article class="metric-card">
            <span class="metric-icon"><ImageIcon :size="17" /></span>
            <span class="metric-label">全站图片</span>
            <strong class="metric-value">{{ data?.images.total || 0 }}</strong>
          </article>
          <article class="metric-card">
            <span class="metric-icon"><Database :size="17" /></span>
            <span class="metric-label">图片存储</span>
            <strong class="metric-value">{{ formatBytes(data?.images.size) }}</strong>
          </article>
          <article class="metric-card">
            <span class="metric-icon"><KeyRound :size="17" /></span>
            <span class="metric-label">API 调用次数</span>
            <strong class="metric-value">{{ data?.api_keys.used_count || 0 }}</strong>
          </article>
        </section>

        <section class="admin-overview-grid">
          <article class="panel-card chart-panel">
            <div class="panel-title">
              <div><h2>资源规模</h2><p>核心业务数据总量对比</p></div>
              <Activity :size="18" />
            </div>
            <div ref="chartRef" class="overview-chart" role="img" aria-label="用户、图片和API密钥数量柱状图" />
          </article>

          <article class="panel-card status-panel">
            <div class="panel-title"><div><h2>系统状态</h2><p>关键能力的运行配置</p></div></div>
            <dl>
              <div><dt>活跃用户</dt><dd>{{ data?.users.active }} / {{ data?.users.total }}</dd></div>
              <div><dt>管理员</dt><dd>{{ data?.users.admins }}</dd></div>
              <div><dt>可用 API 密钥</dt><dd>{{ data?.api_keys.active }} / {{ data?.api_keys.total }}</dd></div>
              <div>
                <dt>开放 API</dt>
                <dd><span class="status-badge" :class="data?.open_api.open_api_enabled ? 'success' : 'warning'">{{ data?.open_api.open_api_enabled ? '运行中' : '已关闭' }}</span></dd>
              </div>
              <div>
                <dt>API 上传</dt>
                <dd><span class="status-badge" :class="data?.open_api.open_api_upload_enabled ? 'success' : 'warning'">{{ data?.open_api.open_api_upload_enabled ? '允许' : '禁止' }}</span></dd>
              </div>
            </dl>
          </article>
        </section>

        <section class="panel-card">
          <div class="panel-title">
            <div><h2>最近注册用户</h2><p>最新进入系统的账号</p></div>
            <el-button text @click="$router.push('/admin/users')">查看全部</el-button>
          </div>
          <div class="recent-users">
            <article v-for="user in data?.recent_users" :key="user.id">
              <span class="avatar small">{{ user.username.slice(0, 2).toUpperCase() }}</span>
              <div><strong>{{ user.username }}</strong><span>{{ user.email || '未填写邮箱' }}</span></div>
              <span class="status-badge" :class="user.status === 1 ? 'success' : 'warning'">{{ user.role === 'admin' ? '管理员' : '用户' }}</span>
            </article>
          </div>
        </section>
      </template>
    </el-skeleton>
  </div>
</template>

<style scoped>
.admin-overview-grid {
  display: grid;
  grid-template-columns: 1.35fr 0.65fr;
  gap: 12px;
}
.panel-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.panel-title h2 { margin: 0; font-size: 12px; }
.panel-title p { margin: 4px 0 0; color: var(--text-tertiary); font-size: 9px; }
.panel-title > svg { color: var(--text-tertiary); }
.overview-chart { width: 100%; height: 270px; margin-top: 10px; }
.status-panel dl { margin: 16px 0 0; }
.status-panel dl > div {
  display: flex;
  min-height: 40px;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
}
.status-panel dl > div:last-child { border-bottom: 0; }
.status-panel dt { color: var(--text-tertiary); font-size: 10px; }
.status-panel dd { margin: 0; color: var(--text-primary); font-size: 10px; font-weight: 600; }
.recent-users {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
  margin-top: 15px;
}
.recent-users article {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 8px;
  padding: 9px;
  border: 1px solid var(--border);
  border-radius: 9px;
}
.recent-users article > div { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.recent-users strong, .recent-users span { overflow: hidden; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.recent-users article > div span { margin-top: 3px; color: var(--text-tertiary); }
@media (max-width: 1000px) {
  .admin-overview-grid { grid-template-columns: 1fr; }
  .recent-users { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 520px) {
  .recent-users { grid-template-columns: 1fr; }
}
</style>
