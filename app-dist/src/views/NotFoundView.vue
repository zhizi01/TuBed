<script setup>
import { ArrowLeft, FileQuestion } from 'lucide-vue-next'
</script>

<template>
  <main class="status-page standalone">
    <div class="status-icon"><FileQuestion :size="28" /></div>
    <span class="status-code">404</span>
    <h1>页面不存在</h1>
    <p>你访问的地址可能已被移动或删除。</p>
    <el-button type="primary" @click="$router.push('/dashboard')">
      <ArrowLeft :size="16" />返回工作台
    </el-button>
  </main>
</template>
