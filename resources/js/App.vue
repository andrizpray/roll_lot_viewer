<template>
  <div id="app">
    <nav class="navbar">
      <div class="container">
        <div class="logo-section">
          <span class="logo-icon">📦</span>
          <h1 class="logo">Roll Lot Viewer</h1>
        </div>
        <div class="nav-links">
          <router-link to="/" class="nav-link" active-class="active">Dashboard</router-link>
          <router-link to="/rolls" class="nav-link" active-class="active">Data Roll</router-link>
          <router-link to="/sheets" class="nav-link" active-class="active">Data Sheet</router-link>
          <router-link to="/upload" class="nav-link" active-class="active">Upload</router-link>
        </div>
      </div>
    </nav>
    <main class="main-content">
      <router-view />
    </main>
  </div>
</template>

<script setup>
// Root component
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
  background: #f1f5f9;
  color: #1e293b;
}

#app {
  min-height: 100vh;
}

/* Navbar - Dark theme */
.navbar {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  border-bottom: 2px solid #059669;
  padding: 0.875rem 0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.navbar .container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo-section {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.logo-icon {
  font-size: 2rem;
  filter: drop-shadow(0 2px 4px rgba(5, 150, 105, 0.3));
}

.logo {
  font-size: 1.5rem;
  font-weight: 700;
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.nav-links {
  display: flex;
  gap: 0.5rem;
}

.nav-link {
  text-decoration: none;
  color: #cbd5e1;
  font-weight: 500;
  padding: 0.625rem 1.25rem;
  border-radius: 0.5rem;
  transition: all 0.2s ease;
  position: relative;
}

.nav-link:hover {
  background: rgba(5, 150, 105, 0.1);
  color: #ffffff;
}

.nav-link.active {
  background: rgba(5, 150, 105, 0.2);
  color: #ffffff;
  font-weight: 600;
}

.nav-link.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 60%;
  height: 3px;
  background: linear-gradient(90deg, #059669, #10b981);
  border-radius: 3px 3px 0 0;
  animation: underlineSlide 0.3s ease-out;
}

@keyframes underlineSlide {
  from {
    width: 0;
    opacity: 0;
  }
  to {
    width: 60%;
    opacity: 1;
  }
}

.main-content {
  max-width: 1400px;
  margin: 2rem auto;
  padding: 0 2rem;
  padding-bottom: 4rem;
}

@media (max-width: 768px) {
  .navbar .container {
    flex-direction: column;
    gap: 1rem;
  }
  
  .nav-links {
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.5rem;
  }
  
  .logo {
    font-size: 1.25rem;
  }
  
  .main-content {
    padding: 0 1rem;
  }
}
</style>
