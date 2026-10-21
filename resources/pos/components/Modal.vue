<script setup lang="ts">
defineProps<{ title: string; wide?: boolean }>()
defineEmits<{ close: [] }>()
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="$emit('close')">
    <div class="card flex max-h-full w-full flex-col overflow-hidden" :class="wide ? 'max-w-3xl' : 'max-w-md'">
      <div class="flex items-center justify-between border-b px-5 py-3">
        <h2 class="text-lg font-bold">{{ title }}</h2>
        <button class="text-2xl leading-none text-slate-400" aria-label="ปิด" @click="$emit('close')">×</button>
      </div>
      <div class="overflow-y-auto p-5"><slot /></div>
    </div>
  </div>
</template>
