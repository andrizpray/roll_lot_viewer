import '../css/app.css';

import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import PrimeVue from 'primevue/config';
import axios from 'axios';

// Import components
import App from './App.vue';
import DashboardPage from './pages/DashboardPage.vue';
import HomePage from './pages/HomePage.vue';
import SheetPage from './pages/SheetPage.vue';
import UploadPage from './pages/UploadPage.vue';

// Router setup
const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'dashboard',
            component: DashboardPage,
            meta: { title: 'Dashboard', icon: 'pi pi-th-large' },
        },
        {
            path: '/rolls',
            name: 'home',
            component: HomePage,
            meta: { title: 'Data Roll', icon: 'pi pi-database' },
        },
        {
            path: '/sheets',
            name: 'sheets',
            component: SheetPage,
            meta: { title: 'Data Sheet', icon: 'pi pi-file' },
        },
        {
            path: '/upload',
            name: 'upload',
            component: UploadPage,
            meta: { title: 'Upload & Import', icon: 'pi pi-cloud-upload' },
        },
    ],
});

// Update document title on navigation
router.afterEach((to) => {
    document.title = to.meta?.title
        ? `${to.meta.title} — Roll Lot Viewer`
        : 'Roll Lot Viewer';
});

// Create app
const app = createApp(App);
app.use(router);
app.use(PrimeVue);

// Setup CSRF token for future auth
axios.defaults.headers.common['X-CSRF-TOKEN'] =
  document.querySelector('meta[name="csrf-token"]')?.content;

app.mount('#app');
