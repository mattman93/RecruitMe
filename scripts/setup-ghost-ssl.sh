#!/bin/bash

# Setup SSL certificate for blog.appliflow.ai using Let's Encrypt
# This script should be run on your production server

set -e

echo "=========================================="
echo "Ghost Blog SSL Setup for blog.appliflow.ai"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="blog.appliflow.ai"
EMAIL="admin@appliflow.ai"  # Change this to your email
NGINX_CONF="/etc/nginx/sites-available/blog.appliflow.ai.conf"
NGINX_ENABLED="/etc/nginx/sites-enabled/blog.appliflow.ai.conf"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Checking prerequisites...${NC}"

# Check if certbot is installed
if ! command -v certbot &> /dev/null; then
    echo -e "${YELLOW}Certbot not found. Installing...${NC}"
    apt-get update
    apt-get install -y certbot python3-certbot-nginx
else
    echo -e "${GREEN}✓ Certbot is installed${NC}"
fi

# Check if nginx is installed
if ! command -v nginx &> /dev/null; then
    echo -e "${RED}Nginx is not installed. Please install nginx first.${NC}"
    exit 1
else
    echo -e "${GREEN}✓ Nginx is installed${NC}"
fi

echo ""
echo -e "${YELLOW}Step 2: Setting up Nginx configuration...${NC}"

# Copy the nginx configuration
if [ -f "/var/www/appliflow/docker/nginx/blog.appliflow.ai.conf" ]; then
    cp /var/www/appliflow/docker/nginx/blog.appliflow.ai.conf $NGINX_CONF
    echo -e "${GREEN}✓ Nginx configuration copied${NC}"
else
    echo -e "${RED}Nginx configuration file not found!${NC}"
    exit 1
fi

# Create temporary HTTP-only config for certbot verification
cat > /tmp/blog-temp.conf << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name blog.appliflow.ai;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        proxy_pass http://localhost:2368;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

# Create certbot directory
mkdir -p /var/www/certbot

# Use temporary config first
cp /tmp/blog-temp.conf $NGINX_CONF

# Enable the site
if [ ! -L "$NGINX_ENABLED" ]; then
    ln -s $NGINX_CONF $NGINX_ENABLED
    echo -e "${GREEN}✓ Site enabled${NC}"
fi

# Test nginx configuration
echo -e "${YELLOW}Testing Nginx configuration...${NC}"
nginx -t

if [ $? -ne 0 ]; then
    echo -e "${RED}Nginx configuration test failed!${NC}"
    exit 1
fi

# Reload nginx
echo -e "${YELLOW}Reloading Nginx...${NC}"
systemctl reload nginx
echo -e "${GREEN}✓ Nginx reloaded${NC}"

echo ""
echo -e "${YELLOW}Step 3: Obtaining SSL certificate...${NC}"
echo -e "${YELLOW}This may take a minute...${NC}"
echo ""

# Obtain certificate
certbot certonly \
    --nginx \
    --non-interactive \
    --agree-tos \
    --email $EMAIL \
    -d $DOMAIN

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ SSL certificate obtained successfully!${NC}"

    # Now copy the full HTTPS configuration
    cp /var/www/appliflow/docker/nginx/blog.appliflow.ai.conf $NGINX_CONF

    # Test nginx configuration again
    nginx -t

    if [ $? -eq 0 ]; then
        # Reload nginx with HTTPS config
        systemctl reload nginx
        echo -e "${GREEN}✓ Nginx reloaded with HTTPS configuration${NC}"
    else
        echo -e "${RED}Nginx configuration test failed with HTTPS config!${NC}"
        exit 1
    fi
else
    echo -e "${RED}Failed to obtain SSL certificate!${NC}"
    echo -e "${YELLOW}Please check:${NC}"
    echo "1. DNS is properly configured (blog.appliflow.ai points to this server)"
    echo "2. Port 80 is open and accessible from the internet"
    echo "3. No firewall is blocking Let's Encrypt"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 4: Setting up automatic renewal...${NC}"

# Setup automatic renewal (certbot usually does this automatically)
if ! crontab -l | grep -q "certbot renew"; then
    (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -
    echo -e "${GREEN}✓ Automatic renewal configured${NC}"
else
    echo -e "${GREEN}✓ Automatic renewal already configured${NC}"
fi

echo ""
echo -e "${GREEN}=========================================="
echo "SSL Setup Complete!"
echo "==========================================${NC}"
echo ""
echo "Your blog is now available at:"
echo -e "${GREEN}https://blog.appliflow.ai${NC}"
echo ""
echo "Ghost Admin is available at:"
echo -e "${GREEN}https://blog.appliflow.ai/ghost${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Update your .env file with production values:"
echo "   GHOST_URL=https://blog.appliflow.ai"
echo "   GHOST_ADMIN_URL=https://blog.appliflow.ai/ghost"
echo ""
echo "2. Restart Ghost container:"
echo "   cd /var/www/appliflow && docker-compose restart ghost"
echo ""
echo "3. Access Ghost admin and complete setup"
echo ""
echo -e "${YELLOW}Certificate renewal:${NC}"
echo "Certificates will auto-renew 30 days before expiration."
echo "You can manually test renewal with:"
echo "   certbot renew --dry-run"
echo ""
