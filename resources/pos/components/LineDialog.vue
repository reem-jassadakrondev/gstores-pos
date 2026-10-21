<script setup lang="ts">
import { ref } from 'vue'
import { money } from '../lib/calc'
import type { CartLine } from '../lib/sale'
import { reprice } from '../lib/sale'
import Modal from './Modal.vue'
import NumPad from './NumPad.vue'

/** Edit a cart line: quantity, unit (pack size) and line discount. */
const props = defineProps<{ line: CartLine }>()
const emit = defineEmits<{ close: []; remove: [] }>()
const field = ref<'qty' | 'discount'>('qty')
const qty = ref(String(props.line.qty))
const discount = ref(props.line.discount ? String(props.line.discount) : '')
const unitId = ref(props.line.unitId)

function save() {
  const line = props.line
  line.qty = Math.max(0.001, Number(qty.value || 1))
  line.unitId = unitId.value
  reprice(line)
  line.discount = Math.min(Number(discount.value || 0), line.unitPrice * line.qty)
  emit('close')
}
</script>

<template>
  <Modal :title="line.item.product_name" @close="save">
    <div class="space-y-4">
      <div v-if="line.item.units.length" class="flex flex-wrap gap-2">
        <button class="btn-ghost" :class="{ '!border-brand !bg-violet-50': unitId === null }" @click="unitId = null">{{ line.item.base_unit }}</button>
        <button v-for="u in line.item.units" :key="u.id" class="btn-ghost" :class="{ '!border-brand !bg-violet-50': unitId === u.id }"
          @click="unitId = u.id">{{ u.name }} ({{ Number(u.factor) }})</button>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <button class="input text-left" :class="{ 'ring-2 ring-brand': field === 'qty' }" @click="field = 'qty'">
          <small class="block text-slate-500">จำนวน</small>{{ qty || '0' }}
        </button>
        <button class="input text-left" :class="{ 'ring-2 ring-brand': field === 'discount' }" @click="field = 'discount'">
          <small class="block text-slate-500">ส่วนลดรายการ (บาท)</small>{{ discount || '0' }}
        </button>
      </div>
      <NumPad v-if="field === 'qty'" v-model="qty" :decimals="line.item.is_weighable" />
      <NumPad v-else v-model="discount" decimals />
      <p class="text-right text-slate-500">ราคาต่อหน่วย {{ money(line.unitPrice) }}</p>
      <div class="flex gap-2">
        <button class="btn-danger flex-1" @click="$emit('remove')">ลบรายการ</button>
        <button class="btn-primary flex-1" @click="save">ตกลง</button>
      </div>
    </div>
  </Modal>
</template>
