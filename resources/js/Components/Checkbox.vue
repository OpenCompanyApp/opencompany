<script setup lang="ts">
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps<{
    checked: boolean;
    value?: any;
}>();

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="rounded border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white bg-white dark:bg-neutral-900 shadow-sm focus:ring-neutral-900/30 dark:focus:ring-white/30 focus:ring-offset-0"
    />
</template>
