<template>
  <div class="flex flex-col">
    <label v-if="label" :for="id" class="form-label">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    <input
      :id="id"
      v-bind="$attrs"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :class="['form-input', error ? 'error' : '']"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <span v-if="error" class="form-error">{{ error }}</span>
    <span v-else-if="hint" class="text-xs text-gray-400 mt-1">{{ hint }}</span>
  </div>
</template>

<script setup>
defineProps({
  modelValue:  { default: '' },
  label:       String,
  id:          String,
  type:        { type: String, default: 'text' },
  placeholder: String,
  error:       String,
  hint:        String,
  disabled:    Boolean,
  required:    Boolean,
})

defineEmits(['update:modelValue'])
</script>
