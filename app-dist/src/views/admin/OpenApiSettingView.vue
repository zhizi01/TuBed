<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Gauge, KeyRound, Save, ShieldCheck, UploadCloud } from 'lucide-vue-next'
import { ElMessage } from 'element-plus'
import { adminApi } from '../../api'

const loading = ref(true)
const saving = ref(false)
const form = reactive({
  open_api_enabled: true,
  open_api_upload_enabled: true,
  open_api_default_rate_limit: 60,
  open_api_default_rate_window: 60,
  open_api_default_total_limit: 10000,
  open_api_max_keys_per_user: 5,
})

async function load() {
  loading.value = true
  try {
    const response = await adminApi.apiSettings()
    Object.assign(form, response.data)
  } catch (error) {
    ElMessage.error(error.message || '接口策略加载失败')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const response = await adminApi.updateApiSettings({
      ...form,
      open_api_enabled: form.open_api_enabled ? 1 : 0,
      open_api_upload_enabled: form.open_api_upload_enabled ? 1 : 0,
    })
    Object.assign(form, response.data)
    ElMessage.success('开放 API 策略已保存')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="manage-page">
    <header class="manage-header">
      <div>
        <h1>接口策略</h1>
        <p>控制开放 API 的全局开关，以及新建密钥默认的访问频率和总调用次数。</p>
      </div>
      <div class="manage-actions">
        <el-button type="primary" :loading="saving" @click="save"><Save :size="15" />保存策略</el-button>
      </div>
    </header>

    <el-skeleton :loading="loading" animated :rows="7">
      <template #default>
        <section class="settings-grid">
          <article class="panel-card setting-card">
            <div class="setting-heading">
              <span><ShieldCheck :size="17" /></span>
              <div><h2>服务开关</h2><p>立即控制外部程序是否可以调用接口</p></div>
            </div>
            <div class="switch-list">
              <label>
                <span><strong>启用开放 API</strong><small>关闭后所有 API 密钥均无法访问</small></span>
                <el-switch v-model="form.open_api_enabled" />
              </label>
              <label>
                <span><strong>允许图片上传</strong><small>保留密钥验证，但禁止上传端点</small></span>
                <el-switch v-model="form.open_api_upload_enabled" :disabled="!form.open_api_enabled" />
              </label>
            </div>
          </article>

          <article class="panel-card setting-card">
            <div class="setting-heading">
              <span><Gauge :size="17" /></span>
              <div><h2>默认限流</h2><p>仅作用于保存策略后新创建的密钥</p></div>
            </div>
            <el-form label-position="top" class="number-grid">
              <el-form-item label="窗口最大请求数">
                <el-input-number v-model="form.open_api_default_rate_limit" :min="1" :max="10000" style="width: 100%" />
              </el-form-item>
              <el-form-item label="限流窗口（秒）">
                <el-input-number v-model="form.open_api_default_rate_window" :min="1" :max="86400" style="width: 100%" />
              </el-form-item>
            </el-form>
          </article>

          <article class="panel-card setting-card">
            <div class="setting-heading">
              <span><KeyRound :size="17" /></span>
              <div><h2>密钥额度</h2><p>控制新密钥总调用次数和账号可创建数量</p></div>
            </div>
            <el-form label-position="top" class="number-grid">
              <el-form-item label="默认总调用额度（0 为不限）">
                <el-input-number v-model="form.open_api_default_total_limit" :min="0" :max="1000000000" style="width: 100%" />
              </el-form-item>
              <el-form-item label="每个用户最多密钥数">
                <el-input-number v-model="form.open_api_max_keys_per_user" :min="1" :max="100" style="width: 100%" />
              </el-form-item>
            </el-form>
          </article>

          <article class="policy-preview panel-card">
            <span class="preview-icon"><UploadCloud :size="20" /></span>
            <div>
              <span class="eyebrow">当前默认策略</span>
              <h2>{{ form.open_api_default_rate_limit }} 次 / {{ form.open_api_default_rate_window }} 秒</h2>
              <p>
                新密钥总额度
                <strong>{{ form.open_api_default_total_limit || '不限' }}</strong>，
                每个用户最多 <strong>{{ form.open_api_max_keys_per_user }}</strong> 个密钥。
              </p>
            </div>
            <span class="status-badge" :class="form.open_api_enabled ? 'success' : 'warning'">
              {{ form.open_api_enabled ? '服务运行中' : '服务已关闭' }}
            </span>
          </article>
        </section>
      </template>
    </el-skeleton>
  </div>
</template>

<style scoped>
.settings-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.setting-heading {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 19px;
}
.setting-heading > span, .preview-icon {
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
.setting-heading h2, .policy-preview h2 { margin: 0; font-size: 12px; }
.setting-heading p { margin: 3px 0 0; color: var(--text-tertiary); font-size: 9px; }
.switch-list { display: grid; }
.switch-list label {
  display: flex;
  min-height: 55px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-top: 1px solid var(--border);
}
.switch-list label > span { display: flex; flex-direction: column; }
.switch-list strong { font-size: 10px; }
.switch-list small { margin-top: 3px; color: var(--text-tertiary); font-size: 9px; }
.number-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.policy-preview {
  display: flex;
  grid-column: 1 / -1;
  align-items: center;
  gap: 13px;
}
.policy-preview > div { flex: 1; }
.policy-preview h2 { margin-top: 5px; font-size: 19px; letter-spacing: -0.4px; }
.policy-preview p { margin: 5px 0 0; color: var(--text-secondary); font-size: 9px; }
@media (max-width: 800px) {
  .settings-grid { grid-template-columns: 1fr; }
  .policy-preview { grid-column: auto; align-items: flex-start; flex-wrap: wrap; }
}
@media (max-width: 520px) {
  .number-grid { grid-template-columns: 1fr; gap: 0; }
}
</style>
