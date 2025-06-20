#!/bin/bash
# Script to install the feature branch for testing

echo "Installing performance-review module with extensibility feature..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get the current directory (module source)
MODULE_DIR="$(cd "$(dirname "$0")" && pwd)"

# Check if we're on the feature branch
CURRENT_BRANCH=$(cd "$MODULE_DIR" && git branch --show-current)
if [ "$CURRENT_BRANCH" != "feature/1-extensible-analyzers" ]; then
    echo -e "${YELLOW}Switching to feature branch...${NC}"
    cd "$MODULE_DIR" && git checkout feature/1-extensible-analyzers
fi

# Create modules directory if it doesn't exist
echo -e "${YELLOW}Creating n98-magerun2 modules directory...${NC}"
mkdir -p ~/.n98-magerun2/modules

# Remove old module if exists
if [ -d ~/.n98-magerun2/modules/performance-review ]; then
    echo -e "${YELLOW}Removing old module installation...${NC}"
    rm -rf ~/.n98-magerun2/modules/performance-review
fi

# Copy the module to n98-magerun2 modules directory
echo -e "${YELLOW}Installing module...${NC}"
cp -r "$MODULE_DIR" ~/.n98-magerun2/modules/

echo -e "${GREEN}✓ Module installed successfully${NC}"

# Verify installation
echo -e "\n${YELLOW}Verifying installation...${NC}"

# Test if the command exists
if ~/fch/n98-magerun2/n98-magerun2.phar list performance 2>&1 | grep -q "performance:review"; then
    echo -e "${GREEN}✓ Module is loaded correctly${NC}"
    
    # Check for the new option
    if ~/fch/n98-magerun2/n98-magerun2.phar help performance:review 2>&1 | grep -q "list-analyzers"; then
        echo -e "${GREEN}✓ New extensibility features are available${NC}"
    else
        echo -e "${RED}✗ New features not found. The module might be cached.${NC}"
        echo -e "${YELLOW}Try clearing the cache:${NC}"
        echo "rm -rf ~/.n98-magerun2/cache/*"
    fi
else
    echo -e "${RED}✗ Module not loaded${NC}"
fi

echo -e "\n${GREEN}Next steps:${NC}"
echo "1. Test the new options:"
echo -e "   ${YELLOW}~/fch/n98-magerun2/n98-magerun2.phar --root-dir ~/fch/magento248/ performance:review --list-analyzers${NC}"
echo ""
echo "2. If you get 'option does not exist', try:"
echo -e "   ${YELLOW}rm -rf ~/.n98-magerun2/cache/*${NC}"
echo -e "   ${YELLOW}~/fch/n98-magerun2/n98-magerun2.phar cache:clear${NC}"