<template>
    <div class="relative min-h-screen">
        <MatrixBackground />
        <div class="relative z-10">
            <router-view />
        </div>
    </div>
</template>

<script setup>
import MatrixBackground from './components/MatrixBackground.vue';
</script>
