#!/bin/bash
# Deployment config for roll-lot-viewer server setup
# Run this on fresh Ubuntu 22.04 + PHP 8.3 install after Laravel setup

echo "============================================"
echo "Roll Lot Viewer - Server Configuration"
echo "============================================"

# 1. Nginx configuration (client_max_body_size)
echo "[1/4] Setting up Nginx..."
sudo cat > /etc/nginx/sites-enabled/roll-lot.driz.web.id << 'EOF'
server {
    server_name roll-lot.driz.web.id;

    root /home/ubuntu/roll-lot-viewer/public;
    index index.php;

    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/roll-lot.driz.web.id/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/roll-lot.driz.web.id/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot
}
server {
    if ($host = roll-lot.driz.web.id) {
        return 301 https://$host$request_uri;
    } # managed by Certbot

    listen 80;
    server_name roll-lot.driz.web.id;
    return 404; # managed by Certbot
}
EOF

# 2. PHP configuration (upload limits)
echo "[2/4] Setting up PHP upload limits..."
sudo sed -i 's/^upload_max_filesize = 2M$/upload_max_filesize = 20M/' /etc/php/8.3/fpm/php.ini
sudo sed -i 's/^post_max_size = 8M$/post_max_size = 20M/' /etc/php/8.3/fpm/php.ini

# 3. Storage permissions for queue worker
echo "[3/4] Setting up storage permissions..."
cd /home/ubuntu/roll-lot-viewer
mkdir -p storage/app/private/imports

sudo chown ubuntu:www-data storage/app/private/
sudo chmod 750 storage/app/private/
sudo setfacl -m g:www-data:rwx storage/app/private/
sudo setfacl -d -m g:www-data:rwx storage/app/private/

sudo chmod 755 storage/app/private/imports
sudo chown ubuntu:www-data storage/app/private/imports
sudo setfacl -m g:www-data:rwx storage/app/private/imports
sudo setfacl -d -m g:www-data:rwx storage/app/private/imports

# 4. Restart services
echo "[4/4] Restarting services..."
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm

echo "============================================"
echo "Setup complete! Verify with:"
echo "  curl -I https://roll-lot.driz.web.id/"
echo "============================================"
