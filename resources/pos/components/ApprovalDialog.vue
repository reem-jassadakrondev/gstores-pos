<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { db, type Staff } from '../lib/db'
import { verifyPin } from '../lib/pin'
import Modal from './Modal.vue'
import PinPad from './PinPad.vue'

/** Manager approval: someone holding `permission` at this branch enters their PIN (works offline). */
const props = defineProps<{ permission: string; title: string }>()
const emit = defineEmits<{ approved: [staff: Staff]; close: [] }>()
const staff = ref<Staff[]>([])
const chosen = ref<Staff | null>(null)
const error = ref<string | null>(null)
const busy = ref(false)

onMounted(async () => {
  staff.value = (await db.staff.toArray()).filter((s) => s.permissions.includes(props.permission) && s.pin)
  if (staff.value.length === 1) chosen.value = staff.value[0]
})

const hint = computed(() => (staff.value.length ? '' : 'ไม่มีผู้มีสิทธิ์อนุมัติที่ตั้ง PIN ไว้'))

async function check(pin: string) {
  busy.value = true
  error.value = null
  if (chosen.value?.pin && (await verifyPin(pin, chosen.value.pin))) emit('approved', chosen.value)
  else error.value = 'PIN ไม่ถูกต้อง'
  busy.value = false
}
</script>

<template>
  <Modal :title="title" @close="$emit('close')">
    <p v-if="hint" class="text-rose-600">{{ hint }}</p>
    <div v-else-if="!chosen" class="grid grid-cols-2 gap-2">
      <button v-for="s in staff" :key="s.id" class="btn-ghost" @click="chosen = s">{{ s.name }}</button>
    </div>
    <div v-else class="space-y-3">
      <p class="text-center">ผู้อนุมัติ: <b>{{ chosen.name }}</b></p>
      <PinPad :error="error" :busy="busy" @submit="check" />
    </div>
  </Modal>
</template>
