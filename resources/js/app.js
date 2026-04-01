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

// Admin
import AdminLayout from './admin/AdminLayout.vue';
import AdminLogin from './admin/pages/Login.vue';
import AdminDashboard from './admin/pages/Dashboard.vue';
import AdminCalls from './admin/pages/Calls.vue';
import AdminCallView from './admin/pages/CallView.vue';
import AdminLaunchCall from './admin/pages/LaunchCall.vue';
import AdminUsers from './admin/pages/Users.vue';

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

        // Admin panel
        { path: '/admin/login', name: 'admin.login', component: AdminLogin },
        {
            path: '/admin',
            component: AdminLayout,
            children: [
                { path: '', name: 'admin.dashboard', component: AdminDashboard },
                { path: 'calls', name: 'admin.calls', component: AdminCalls },
                { path: 'calls/:id', name: 'admin.call', component: AdminCallView },
                { path: 'launch', name: 'admin.launch', component: AdminLaunchCall },
                { path: 'users', name: 'admin.users', component: AdminUsers },
            ],
        },
    ],
});

const app = createApp(App);
app.use(router);
app.mount('#app');
