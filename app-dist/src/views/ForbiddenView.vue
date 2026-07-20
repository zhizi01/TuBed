<script setup>
import { ArrowLeft, ShieldX } from 'lucide-vue-next'
</script>

<template>
  <main class="status-page">
    <div class="status-icon"><ShieldX :size="28" /></div>
    <span class="status-code">403</span>
    <h1>没有访问权限</h1>
    <p>当前角色不能进入该页面，请联系管理员调整权限。</p>
    <el-button type="primary" @click="$router.push('/dashboard')">
      <ArrowLeft :size="16" />返回工作台
    </el-button>
  </main>
</template>
