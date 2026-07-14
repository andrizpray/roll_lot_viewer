<template>
  <div class="layout" :class="{ 'sidebar-collapsed': collapsed }">
    <AppSidebar
      :collapsed="collapsed"
      :mobile-open="mobileOpen"
      @toggle="toggleCollapse"
      @close-mobile="mobileOpen = false"
      @navigate="onNavigate"
    />
    <div class="layout-main">
      <AppNavbar :title="currentTitle" @toggle-sidebar="toggleSidebar" />
      <main class="layout-content">
        <div class="content-container">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import AppSidebar from '../components/AppSidebar.vue';
import AppNavbar from '../components/AppNavbar.vue';

const route = useRoute();
const collapsed = ref(false);
const mobileOpen = ref(false);
const isMobile = ref(false);

const currentTitle = computed(() => route.meta?.title || 'Dashboard');

function checkViewport() {
  isMobile.value = window.innerWidth <= 1024;
}

function toggleSidebar() {
  if (isMobile.value) {
    mobileOpen.value = !mobileOpen.value;
  } else {
    collapsed.value = !collapsed.value;
  }
}

function toggleCollapse() {
  collapsed.value = !collapsed.value;
}

function onNavigate() {
  if (isMobile.value) mobileOpen.value = false;
}

onMounted(() => {
  checkViewport();
  window.addEventListener('resize', checkViewport);
});
onUnmounted(() => window.removeEventListener('resize', checkViewport));
</script>

<style scoped>
.layout { min-height: 100vh; }
.layout-main {
  margin-left: var(--sidebar-width);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: margin-left var(--transition);
}
.sidebar-collapsed .layout-main { margin-left: var(--sidebar-collapsed); }

.layout-content { flex: 1; padding: 1.75rem; }
.content-container { max-width: var(--content-max); margin: 0 auto; }

@media (max-width: 1024px) {
  .layout-main,
  .sidebar-collapsed .layout-main { margin-left: 0; }
  .layout-content { padding: 1.25rem; }
}
</style>
