<script setup lang="ts">
/** Touch number pad bound to a string model (digits and one dot). */
const model = defineModel<string>({ required: true })
const props = defineProps<{ decimals?: boolean; maxLength?: number }>()

function press(key: string) {
  if (key === '⌫') model.value = model.value.slice(0, -1)
  else if (key === 'C') model.value = ''
  else if (key === '.' && (!props.decimals || model.value.includes('.'))) return
  else if (model.value.length < (props.maxLength ?? 12)) model.value += key
}
</script>

<template>
  <div class="grid grid-cols-3 gap-2">
    <button v-for="k in ['7', '8', '9', '4', '5', '6', '1', '2', '3', decimals ? '.' : 'C', '0', '⌫']" :key="k"
      type="button" class="btn-ghost py-4 text-2xl" @click="press(k)">{{ k }}</button>
  </div>
</template>
