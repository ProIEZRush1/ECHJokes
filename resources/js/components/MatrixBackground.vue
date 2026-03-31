<template>
    <div class="matrix-bg" aria-hidden="true">
        <div
            v-for="col in columns"
            :key="col.id"
            class="matrix-column"
            :style="{
                left: col.left + '%',
                animationDuration: col.speed + 's',
                animationDelay: col.delay + 's',
                fontSize: col.size + 'px',
            }"
        >
            {{ col.chars }}
        </div>
    </div>
</template>

<script setup>
const chars = 'ECHJOKESアイウエオカキクケコ01サシスセソ';

function randomChars(len) {
    let result = '';
    for (let i = 0; i < len; i++) {
        result += chars[Math.floor(Math.random() * chars.length)];
    }
    return result;
}

const columns = Array.from({ length: 20 }, (_, i) => ({
    id: i,
    left: Math.random() * 100,
    speed: 8 + Math.random() * 12,
    delay: Math.random() * -20,
    size: 10 + Math.random() * 8,
    chars: randomChars(30 + Math.floor(Math.random() * 20)),
}));
</script>
