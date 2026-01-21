<script setup lang="ts">
import { onMounted, ref } from 'vue';

const model = defineModel<string>({ required: true });

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="w-full h-10 px-3 text-sm text-gray-900 placeholder:text-gray-400 rounded-lg border border-gray-200 bg-white shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/20 outline-none transition-colors"
        v-model="model"
        ref="input"
    />
</template>
