import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';
import App from './App.vue';

// Public pages
import Home from './pages/Home.vue';
import CallStatus from './pages/CallStatus.vue';
import CallComplete from './pages/CallComplete.vue';
import Share from './pages/Share.vue';
import Pricing from './pages/Pricing.vue';
import Login from './pages/Login.vue';
import Terms from './pages/Terms.vue';
import Privacy from './pages/Privacy.vue';

// User dashboard
import UserLayout from './user/UserLayout.vue';
import MyCalls from './user/pages/MyCalls.vue';
import NewCall from './user/pages/NewCall.vue';
import Referral from './user/pages/Referral.vue';

// Admin
import AdminLayout from './admin/AdminLayout.vue';
import AdminLogin from './admin/pages/Login.vue';
import AdminDashboard from './admin/pages/Dashboard.vue';
import AdminCalls from './admin/pages/Calls.vue';
import AdminCallView from './admin/pages/CallView.vue';
import AdminLaunchCall from './admin/pages/LaunchCall.vue';
import AdminUsers from './admin/pages/Users.vue';
import AdminPlans from './admin/pages/Plans.vue';
import AdminBilling from './admin/pages/Billing.vue';
import AdminUserDetail from './admin/pages/UserDetail.vue';
import AdminPresets from './admin/pages/Presets.vue';
import AdminReferrals from './admin/pages/Referrals.vue';
import AdminBrand from './admin/pages/Brand.vue';

// Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: Home },
        { path: '/call/:id/status', name: 'call.status', component: CallStatus },
        { path: '/call/:id/complete', name: 'call.complete', component: CallComplete },
        { path: '/share/:sessionId', name: 'share', component: Share },
        { path: '/v/:slug', name: 'share.v', component: Share },
        { path: '/pricing', name: 'pricing', component: Pricing },
        { path: '/login', name: 'login', component: Login },
        { path: '/terms', name: 'terms', component: Terms },
        { path: '/privacy', name: 'privacy', component: Privacy },

        // User dashboard
        {
            path: '/dashboard',
            component: UserLayout,
            children: [
                { path: '', name: 'dashboard', component: MyCalls },
                { path: 'new', name: 'dashboard.new', component: NewCall },
                { path: 'referral', name: 'dashboard.referral', component: Referral },
            ],
        },

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
                { path: 'users/:id', name: 'admin.user', component: AdminUserDetail },
                { path: 'plans', name: 'admin.plans', component: AdminPlans },
                { path: 'billing', name: 'admin.billing', component: AdminBilling },
                { path: 'presets', name: 'admin.presets', component: AdminPresets },
                { path: 'referrals', name: 'admin.referrals', component: AdminReferrals },
                { path: 'brand', name: 'admin.brand', component: AdminBrand },
            ],
        },
    ],
});

const app = createApp(App);
app.use(router);
app.mount('#app');
