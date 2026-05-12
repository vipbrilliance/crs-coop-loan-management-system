<template>
  <div class="info-row">
    <div class="info-label">{{ label }}</div>
    <div :class="['info-value', mono && 'mono']">{{ value || '—' }}</div>
  </div>
</template>

<script setup>
defineProps({ label: String, value: [String, Number], mono: Boolean })
</script>

<style scoped>
.info-row { display:flex; flex-direction:column; gap:3px; }
.info-label {
  font-size:10px; font-weight:600; letter-spacing:0.5px;
  text-transform:uppercase; color:var(--coop-muted);
}
.info-value { font-size:13.5px; color:var(--coop-cream); }
</style>
