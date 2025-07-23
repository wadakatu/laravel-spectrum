#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Clear screen
clear

# Opening
echo -e "${PURPLE}✨ Laravel Spectrum Demo${NC}"
echo -e "${BLUE}🎯 Zero-annotation API Documentation Generator${NC}"
echo ""
sleep 3

# Step 1: Installation
echo -e "${YELLOW}📦 Installing Laravel Spectrum...${NC}"
echo "$ composer require wadakatu/laravel-spectrum"
sleep 2
echo -e "${GREEN}✓ Package installed successfully${NC}"
echo ""
sleep 2

# Step 2: Generate documentation
echo -e "${YELLOW}📝 Generating API documentation...${NC}"
echo "$ php artisan prism:generate"
sleep 1
echo -e "${BLUE}🔍 Analyzing routes...${NC}"
sleep 1
echo "Found 12 API routes"
echo -e "${BLUE}📋 Detecting authentication schemes...${NC}"
echo "  ✓ Sanctum Bearer Token"
sleep 1
echo -e "${BLUE}🔍 Analyzing FormRequests...${NC}"
echo "  ✓ StoreUserRequest"
echo "  ✓ UpdateUserRequest"
echo "  ✓ LoginRequest"
sleep 1
echo -e "${BLUE}📦 Analyzing Resources...${NC}"
echo "  ✓ UserResource"
echo "  ✓ PostResource"
sleep 1
echo -e "${GREEN}✅ Documentation generated: storage/app/prism/openapi.json${NC}"
echo -e "⏱️  Generation completed in 1.3 seconds"
echo ""
sleep 3

# Step 3: Show generated features
echo -e "${YELLOW}🎉 Auto-detected features:${NC}"
echo "  • FormRequest validation rules with types"
echo "  • Custom error messages"
echo "  • Resource response structures"
echo "  • Authentication requirements"
echo "  • 422 validation error responses"
echo ""
sleep 3

# Step 4: Start watch mode
echo -e "${YELLOW}🔥 Starting real-time preview...${NC}"
echo "$ php artisan prism:watch"
sleep 1
echo -e "${GREEN}🚀 Starting Laravel Spectrum preview server...${NC}"
echo -e "${BLUE}📡 Preview server running at http://127.0.0.1:8080${NC}"
echo -e "${BLUE}👀 Watching for file changes...${NC}"
echo "Press Ctrl+C to stop"