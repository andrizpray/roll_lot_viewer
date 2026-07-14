<template>
  <!-- Mobile overlay -->
  <div
    v-if="mobileOpen"
    class="sidebar-overlay"
    @click="$emit('close-mobile')"
  ></div>

  <aside class="sidebar" :class="{ collapsed: collapsed && !mobileOpen, 'mobile-open': mobileOpen }">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-link">
        <span class="brand-logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <ellipse cx="12" cy="7" rx="8" ry="4" />
            <path d="M20 7v4c0 2.2-3.6 4-8 4s-8-1.8-8-4V7" />
            <path d="M4 11v4c0 2.2 3.6 4 8 4s8-1.8 8-4v-4" />
          </svg>
        </span>
        <span class="brand-text">Roll Lot Viewer</span>
      </div>
      <button class="sidebar-toggle-inline" @click="$emit('toggle')" :title="collapsed ? 'Expand' : 'Collapse'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
          <polyline points="15 18 9 12 15 6" />
        </svg>
      </button>
    </div>

    <!-- Menu -->
    <nav class="sidebar-menu">
      <template v-for="section in menu" :key="section.header">
        <span class="menu-header">{{ section.header }}</span>
        <router-link
          v-for="item in section.items"
          :key="item.to"
          :to="item.to"
          class="menu-item"
          active-class="active"
          :title="item.label"
          @click="$emit('navigate')"
        >
          <i class="menu-icon" :class="item.icon"></i>
          <span class="menu-label">{{ item.label }}</span>
        </router-link>
      </template>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
      <span class="footer-text">v1.0 &middot; 2026</span>
    </div>
  </aside>
</template>

<script setup>
defineProps({
  collapsed: { type: Boolean, default: false },
  mobileOpen: { type: Boolean, default: false },
});
defineEmits(['toggle', 'close-mobile', 'navigate']);

const menu = [
  {
    header: 'Main',
    items: [
      { to: '/', label: 'Dashboard', icon: 'pi pi-th-large' },
    ],
  },
  {
    header: 'Data',
    items: [
      { to: '/rolls', label: 'Data Roll', icon: 'pi pi-database' },
      { to: '/sheets', label: 'Data Sheet', icon: 'pi pi-file' },
    ],
  },
  {
    header: 'Import',
    items: [
      { to: '/upload', label: 'Upload & Import', icon: 'pi pi-cloud-upload' },
    ],
  },
];
</script>

<style scoped>
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: var(--sidebar-width);
  background: var(--bg-card);
  border-right: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  z-index: 1000;
  transition: width var(--transition), transform var(--transition);
}
.sidebar.collapsed { width: var(--sidebar-collapsed); }

/* Brand */
.sidebar-brand {
  height: var(--navbar-height);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.25rem;
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.brand-link { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
.brand-logo {
  width: 2.1rem;
  height: 2.1rem;
  border-radius: var(--radius);
  background: var(--primary-light);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.brand-logo svg { width: 1.3rem; height: 1.3rem; color: var(--primary); }
.brand-text {
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--text-heading);
  white-space: nowrap;
  overflow: hidden;
}
.sidebar-toggle-inline {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0.25rem;
  border-radius: var(--radius-sm);
  display: flex;
  transition: all var(--transition);
  flex-shrink: 0;
}
.sidebar-toggle-inline:hover { background: var(--bg-hover); color: var(--primary); }

.collapsed .brand-text,
.collapsed .sidebar-toggle-inline,
.collapsed .menu-header,
.collapsed .menu-label,
.collapsed .footer-text { display: none; }
.collapsed .sidebar-brand { justify-content: center; padding: 0; }

/* Menu */
.sidebar-menu {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.menu-header {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  padding: 0.75rem 0.85rem 0.35rem;
}
.menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  border-radius: var(--radius);
  color: var(--text-body);
  font-weight: 500;
  font-size: 0.9rem;
  transition: all var(--transition);
  white-space: nowrap;
}
.menu-item:hover { background: var(--bg-hover); color: var(--primary); }
.menu-item.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 0.25rem 0.6rem rgba(var(--primary-rgb), 0.35);
}
.menu-icon { font-size: 1.15rem; flex-shrink: 0; width: 1.15rem; text-align: center; }
.collapsed .menu-item { justify-content: center; padding: 0.65rem; }

/* Footer */
.sidebar-footer {
  padding: 1rem 1.25rem;
  border-top: 1px solid var(--border-light);
  flex-shrink: 0;
}
.footer-text { font-size: 0.75rem; color: var(--text-muted); }

/* Mobile */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(47, 43, 61, 0.5);
  z-index: 999;
}
@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
  .sidebar.mobile-open { transform: translateX(0); }
  .sidebar.collapsed { width: var(--sidebar-width); }
}
</style>
