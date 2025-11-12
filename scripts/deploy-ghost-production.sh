#!/bin/bash

# Quick deployment script for Ghost in production
# This automates the basic deployment steps

set -e

echo "=========================================="
echo "Ghost Production Deployment"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "${RED}Error: .env file not found!${NC}"
    echo "Please create .env from .env.example"
    exit 1
fi

echo -e "${BLUE}Checking production configuration...${NC}"
echo ""

# Check if GHOST_URL is set to production
if grep -q "GHOST_URL=http://localhost:2368" .env; then
    echo -e "${YELLOW}⚠ WARNING: GHOST_URL is still set to localhost${NC}"
    echo ""
    read -p "Do you want to update it to https://blog.appliflow.ai? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sed -i 's|GHOST_URL=http://localhost:2368|GHOST_URL=https://blog.appliflow.ai|g' .env
        sed -i 's|GHOST_ADMIN_URL=http://localhost:2368/ghost|GHOST_ADMIN_URL=https://blog.appliflow.ai/ghost|g' .env
        echo -e "${GREEN}✓ URLs updated to production${NC}"
    else
        echo -e "${YELLOW}Please update GHOST_URL manually in .env${NC}"
    fi
fi

# Check if GHOST_CONTENT_API_KEY is set
if ! grep -q "GHOST_CONTENT_API_KEY=.\+" .env; then
    echo -e "${YELLOW}⚠ WARNING: GHOST_CONTENT_API_KEY is not set${NC}"
    echo "You'll need to set this after completing Ghost admin setup"
fi

echo ""
echo -e "${BLUE}Step 1: Pulling latest Ghost image...${NC}"
docker-compose pull ghost

echo ""
echo -e "${BLUE}Step 2: Restarting Ghost with production configuration...${NC}"
docker-compose up -d ghost

echo ""
echo -e "${BLUE}Step 3: Waiting for Ghost to start...${NC}"
sleep 10

# Check if Ghost is running
if docker-compose ps ghost | grep -q "Up"; then
    echo -e "${GREEN}✓ Ghost is running${NC}"
else
    echo -e "${RED}✗ Ghost failed to start${NC}"
    echo "Check logs with: docker-compose logs ghost"
    exit 1
fi

echo ""
echo -e "${BLUE}Step 4: Clearing Laravel cache...${NC}"
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan config:clear
echo -e "${GREEN}✓ Cache cleared${NC}"

echo ""
echo -e "${GREEN}=========================================="
echo "Deployment Complete!"
echo "==========================================${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo ""
echo "1. Ensure DNS is configured:"
echo "   blog.appliflow.ai → YOUR_SERVER_IP"
echo ""
echo "2. Set up SSL certificate:"
echo "   sudo ./scripts/setup-ghost-ssl.sh"
echo ""
echo "3. Access Ghost admin:"
echo "   https://blog.appliflow.ai/ghost"
echo ""
echo "4. Get API keys from Ghost:"
echo "   Settings → Integrations → Add custom integration"
echo ""
echo "5. Update .env with API keys:"
echo "   GHOST_CONTENT_API_KEY=your_key_here"
echo ""
echo "6. Restart app container:"
echo "   docker-compose restart app"
echo ""
echo -e "${BLUE}Check Ghost logs:${NC}"
echo "   docker-compose logs ghost -f"
echo ""
echo -e "${BLUE}Check Ghost status:${NC}"
echo "   docker-compose ps ghost"
echo ""
