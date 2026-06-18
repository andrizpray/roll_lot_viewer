import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import PrimeVue from 'primevue/config';

// Import components
import App from './App.vue';
import HomePage from './pages/HomePage.vue';
import UploadPage from './pages/UploadPage.vue';

// Router setup
const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: HomePage,
        },
        {
            path: '/upload',
            name: 'upload',
            component: UploadPage,
        },
    ],
});

// Create app
const app = createApp(App);
app.use(router);
app.use(PrimeVue);

app.mount('#app');
