<template>
  <div class="flex flex-col">
    <label v-if="label" :for="id" class="form-label">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    <select
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      :class="['form-select', error ? 'error' : '']"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option value="" disabled>{{ placeholder || 'Pilih...' }}</option>
      <option
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
      >{{ opt.label }}</option>
    </select>
    <span v-if="error" class="form-error">{{ error }}</span>
  </div>
</template>

<script setup>
defineProps({
  modelValue:  { default: '' },
  label:       String,
  id:          String,
  options:     { type: Array, default: () => [] }, // [{ value, label }]
  placeholder: String,
  error:       String,
  disabled:    Boolean,
  required:    Boolean,
})

defineEmits(['update:modelValue'])
</script>
