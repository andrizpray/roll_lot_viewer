module.exports = {
  apps: [
    {
      name: 'lot-viewer',
      script: 'php',
      args: 'artisan serve --host=0.0.0.0 --port=3002',
      cwd: '/home/ubuntu/roll_lot_viewer',
      interpreter: 'none',
    },
    {
      name: 'lot-viewer-worker',
      script: 'python/main.py',
      cwd: '/home/ubuntu/roll_lot_viewer',
      interpreter: '/home/ubuntu/roll_lot_viewer/.venv/bin/python',
      env: {
        DB_PASSWORD: 'roll_lot_secure_2026',
        PYTHONPATH: '/home/ubuntu/roll_lot_viewer/python',
      },
    },
  ],
};
