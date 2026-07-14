<template>
  <header class="navbar">
    <div class="navbar-left">
      <button class="nav-toggle" @click="$emit('toggle-sidebar')" aria-label="Toggle menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>
      <div class="breadcrumb">
        <span class="crumb-root">Roll Lot Viewer</span>
        <svg class="crumb-sep" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
          <polyline points="9 18 15 12 9 6" />
        </svg>
        <span class="crumb-current">{{ title }}</span>
      </div>
    </div>

    <div class="navbar-right">
      <a href="/api/health" target="_blank" class="nav-action" title="System health">
        <i class="pi pi-heart"></i>
      </a>
      <div class="nav-user">
        <span class="user-avatar">RL</span>
      </div>
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'Dashboard' },
});
defineEmits(['toggle-sidebar']);
</script>

<style scoped>
.navbar {
  height: var(--navbar-height);
  background: var(--bg-card);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.5rem;
  position: sticky;
  top: 0;
  z-index: 900;
}
.navbar-left { display: flex; align-items: center; gap: 1rem; }
.nav-toggle {
  background: none;
  border: none;
  color: var(--text-body);
  cursor: pointer;
  padding: 0.4rem;
  border-radius: var(--radius-sm);
  display: flex;
  transition: all var(--transition);
}
.nav-toggle:hover { background: var(--bg-hover); color: var(--primary); }

.breadcrumb { display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; }
.crumb-root { color: var(--text-muted); }
.crumb-sep { color: var(--text-muted); }
.crumb-current { color: var(--text-heading); font-weight: 600; }

.navbar-right { display: flex; align-items: center; gap: 0.75rem; }
.nav-action {
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: var(--text-body);
  transition: all var(--transition);
}
.nav-action:hover { background: var(--bg-hover); color: var(--primary); }
.nav-action i { font-size: 1.15rem; }

.user-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.8rem;
}

@media (max-width: 640px) {
  .crumb-root, .crumb-sep { display: none; }
  .navbar { padding: 0 1rem; }
}
</style>
