import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';
import App from './App.vue';
import Home from './pages/Home.vue';
import CallStatus from './pages/CallStatus.vue';
import CallComplete from './pages/CallComplete.vue';
import Share from './pages/Share.vue';
import Pricing from './pages/Pricing.vue';
import Login from './pages/Login.vue';
import Dashboard from './pages/Dashboard.vue';

// Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

// Router
const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: Home },
        { path: '/call/:id/status', name: 'call.status', component: CallStatus },
        { path: '/call/:id/complete', name: 'call.complete', component: CallComplete },
        { path: '/share/:sessionId', name: 'share', component: Share },
        { path: '/pricing', name: 'pricing', component: Pricing },
        { path: '/login', name: 'login', component: Login },
        { path: '/dashboard', name: 'dashboard', component: Dashboard },
    ],
});

const app = createApp(App);
app.use(router);
app.mount('#app');
