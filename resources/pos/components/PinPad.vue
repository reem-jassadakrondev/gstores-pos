<script setup lang="ts">
import { ref, watch } from 'vue'
import NumPad from './NumPad.vue'

/** PIN entry: emits the PIN once 4–6 digits are entered and confirmed. */
const emit = defineEmits<{ submit: [pin: string] }>()
defineProps<{ error?: string | null; busy?: boolean }>()
const pin = ref('')
watch(pin, (v) => {
  if (v.length > 6) pin.value = v.slice(0, 6)
})
function submit() {
  if (pin.value.length >= 4) {
    emit('submit', pin.value)
    pin.value = ''
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-center gap-3">
      <span v-for="i in 6" :key="i" class="h-4 w-4 rounded-full border-2 border-slate-400" :class="{ 'bg-slate-700': i <= pin.length }" />
    </div>
    <p v-if="error" class="text-center text-rose-600">{{ error }}</p>
    <NumPad v-model="pin" :max-length="6" />
    <button class="btn-primary w-full" :disabled="pin.length < 4 || busy" @click="submit">ยืนยัน</button>
  </div>
</template>
