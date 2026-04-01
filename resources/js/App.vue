<template>
    <div class="relative min-h-screen">
        <MatrixBackground v-if="!isAdmin" />
        <div class="relative z-10">
            <router-view />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import MatrixBackground from './components/MatrixBackground.vue';

const route = useRoute();
const isAdmin = computed(() => route.path.startsWith('/admin') || route.path.startsWith('/dashboard'));
</script>
