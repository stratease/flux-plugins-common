#!/bin/bash

# Deploy script for Flux Plugins WordPress plugin
# Commits trunk to SVN and creates version tags
# Requires: wporg/trunk/ to be built first using build-plugin.sh
# Note: wporg/ is the SVN repo root directory

set -e

# Fix execute permissions if needed (for Strauss-copied files).
# This is a fallback - fix-bin-wrappers.php should handle this, but this ensures it works.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT_FILE="$SCRIPT_DIR/$(basename "${BASH_SOURCE[0]}")"
if [ ! -x "$SCRIPT_FILE" ]; then
    chmod +x "$SCRIPT_FILE" 2>/dev/null || true
fi

# Find the plugin root by locating a PHP file with a Plugin Name header.
find_plugin_file() {
    local search_dir=$1
    local file
    for file in "$search_dir"/*.php; do
        if [ -f "$file" ] && grep -q "Plugin Name:" "$file"; then
            echo "$file"
            return 0
        fi
    done
    return 1
}

find_plugin_root() {
    local dir="$PWD"
    local file
    while [ "$dir" != "/" ]; do
        file=$(find_plugin_file "$dir") && {
            echo "$dir|$file"
            return 0
        }
        dir="$(dirname "$dir")"
    done
    return 1
}

PLUGIN_INFO=$(find_plugin_root)
if [ -z "$PLUGIN_INFO" ]; then
    echo "❌ Error: Could not locate a plugin root. Run this script from a plugin directory."
    exit 1
fi

PLUGIN_DIR="${PLUGIN_INFO%%|*}"
PLUGIN_FILE="${PLUGIN_INFO#*|}"
PLUGIN_NAME="$(basename "$PLUGIN_DIR")"
PLUGIN_FILE_NAME="$(basename "$PLUGIN_FILE")"
WPORG_DIR="$PLUGIN_DIR/wporg"
TRUNK_DIR="$WPORG_DIR/trunk"
SVN_REPO_URL="https://plugins.svn.wordpress.org/$PLUGIN_NAME"

# Detect the version constant name in the plugin file (e.g., FLUX_MEDIA_OPTIMIZER_VERSION).
VERSION_CONST_NAME=$(grep -E "define\(\s*'[^']+_VERSION'" "$PLUGIN_FILE" | head -1 | sed -n "s/.*'\([^']\+_VERSION\)'.*/\1/p")

# Function to extract version from plugin file header
extract_plugin_header_version() {
    local plugin_file=$1
    if [ -f "$plugin_file" ]; then
        grep "Version:" "$plugin_file" | sed 's/.*Version:[[:space:]]*//' | tr -d '\r\n' | tr -d ' ' | head -1
    fi
}

# Function to extract version from PHP constant
extract_php_constant_version() {
    local plugin_file=$1
    if [ -f "$plugin_file" ] && [ -n "$VERSION_CONST_NAME" ]; then
        grep "$VERSION_CONST_NAME" "$plugin_file" | sed -n "s/.*'$VERSION_CONST_NAME',[[:space:]]*'\([^']*\)'.*/\1/p" | head -1
    fi
}

# Function to validate version format (semver: x.y.z)
validate_version() {
    local version=$1
    if [[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.-]+)?(\+[a-zA-Z0-9.-]+)?$ ]]; then
        return 0
    else
        return 1
    fi
}

# Check if trunk directory exists
if [ ! -d "$TRUNK_DIR" ]; then
    echo "❌ Error: Trunk directory not found: $TRUNK_DIR"
    echo "   Please run ./bin/build-plugin.sh first to build the plugin."
    exit 1
fi

# Check if plugin file exists in trunk
PLUGIN_FILE="$TRUNK_DIR/$PLUGIN_FILE_NAME"
if [ ! -f "$PLUGIN_FILE" ]; then
    echo "❌ Error: Plugin file not found in trunk: $PLUGIN_FILE"
    echo "   Please run ./bin/build-plugin.sh first to build the plugin."
    exit 1
fi

# Extract version from trunk plugin file
TRUNK_VERSION=$(extract_plugin_header_version "$PLUGIN_FILE")
if [ -z "$TRUNK_VERSION" ]; then
    TRUNK_VERSION=$(extract_php_constant_version "$PLUGIN_FILE")
fi

if [ -z "$TRUNK_VERSION" ]; then
    echo "❌ Error: Could not determine version from plugin file in trunk."
    exit 1
fi

# Display version information
echo "📋 Version Information:"
echo "   Trunk Version: $TRUNK_VERSION"
echo ""

# Check if SVN is available
if ! command -v svn &> /dev/null; then
    echo "❌ Error: SVN is not installed or not in PATH."
    echo "   Please install Subversion to use this script."
    exit 1
fi

# Check if SVN repo is checked out (wporg/ is the SVN repo root)
if [ ! -d "$WPORG_DIR/.svn" ]; then
    echo "❌ Error: SVN repository not found: $WPORG_DIR"
    echo "   Please run ./bin/build-plugin.sh first to build and checkout the SVN repository."
    exit 1
fi

# Display deployment options
echo ""
echo "🚀 Deployment Options:"
echo "   1) Update trunk only (for development/continuous updates)"
echo "   2) Create/update tag: $TRUNK_VERSION (for versioned release)"
echo "   3) Both: Update trunk and create tag"
read -p "   Select option (1, 2, or 3, default: 3): " DEPLOY_OPTION
DEPLOY_OPTION="${DEPLOY_OPTION:-3}"

if [[ ! "$DEPLOY_OPTION" =~ ^[123]$ ]]; then
    echo "❌ Invalid option. Must be 1, 2, or 3."
    exit 1
fi

# Validate version format
if ! validate_version "$TRUNK_VERSION"; then
    echo "⚠️  Warning: Version format may be invalid: $TRUNK_VERSION"
    echo "   Expected format: x.y.z or x.y.z-suffix"
    read -p "   Continue anyway? (y/N): " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
        echo "❌ Deployment cancelled."
        exit 1
    fi
fi

# Check if tag already exists
TAG_DIR="tags/$TRUNK_VERSION"
TAG_EXISTS=false
cd "$WPORG_DIR"
# Check if tag exists in SVN (must check via SVN, not just local directory)
if svn info "$TAG_DIR" &> /dev/null; then
    TAG_EXISTS=true
fi
cd "$PLUGIN_DIR"

# Only show warning if tag actually exists and we're creating/updating a tag
if [ "$TAG_EXISTS" = true ] && [[ "$DEPLOY_OPTION" == "2" || "$DEPLOY_OPTION" == "3" ]]; then
    echo ""
    echo "⚠️  Warning: Tag $TRUNK_VERSION already exists in SVN."
    read -p "   Overwrite existing tag? (y/N): " OVERWRITE_TAG
    if [[ ! "$OVERWRITE_TAG" =~ ^[Yy]$ ]]; then
        echo "❌ Deployment cancelled."
        exit 1
    fi
fi

# Show what will be deployed
echo ""
echo "📋 Deployment Summary:"
echo "   Version: $TRUNK_VERSION"
if [[ "$DEPLOY_OPTION" == "1" ]]; then
    echo "   Action: Update trunk only"
elif [[ "$DEPLOY_OPTION" == "2" ]]; then
    echo "   Action: Create/update tag: tags/$TRUNK_VERSION"
    if [ "$TAG_EXISTS" = true ]; then
        echo "   Note: Existing tag will be overwritten"
    fi
else
    echo "   Action: Update trunk and create tag: tags/$TRUNK_VERSION"
    if [ "$TAG_EXISTS" = true ]; then
        echo "   Note: Existing tag will be overwritten"
    fi
fi
echo "   SVN Repository: $SVN_REPO_URL"
echo ""

# Final confirmation
read -p "⚠️  Proceed with deployment? (yes/no): " FINAL_CONFIRM
if [[ ! "$FINAL_CONFIRM" == "yes" ]]; then
    echo "❌ Deployment cancelled."
    exit 1
fi

# Deploy to trunk
if [[ "$DEPLOY_OPTION" == "1" || "$DEPLOY_OPTION" == "3" ]]; then
    echo ""
    echo "📦 Committing trunk changes..."
    
    # Files are already in wporg/trunk/ from build script
    # Perform SVN operations from repo root to properly track all files including assets/
    cd "$WPORG_DIR"
    
    # Add new files to SVN (from repo root, specify trunk path)
    svn add --force trunk 2>/dev/null || true
    
    # Remove deleted files from SVN
    svn status trunk | grep '^!' | awk '{print $2}' | xargs svn rm 2>/dev/null || true
    
    # Show status (filter to show only trunk changes)
    echo "   SVN Status:"
    svn status trunk | head -20
    STATUS_COUNT=$(svn status trunk | wc -l)
    if [ "$STATUS_COUNT" -gt 20 ]; then
        echo "   ... (showing first 20 changes)"
    fi
    
    echo ""
    read -p "   Commit trunk changes? (Y/n): " COMMIT_TRUNK
    if [[ ! "$COMMIT_TRUNK" =~ ^[Nn]$ ]]; then
        svn commit -m "Update trunk to version $TRUNK_VERSION" trunk
        echo "   ✅ Trunk updated successfully!"
    else
        echo "   ⚠️  Trunk changes not committed."
    fi
fi

# Deploy to tag
if [[ "$DEPLOY_OPTION" == "2" || "$DEPLOY_OPTION" == "3" ]]; then
    echo ""
    echo "🏷️  Creating/updating tag: $TRUNK_VERSION"
    
    TAG_DIR="tags/$TRUNK_VERSION"
    TRUNK_URL="$SVN_REPO_URL/trunk"
    TAG_URL="$SVN_REPO_URL/$TAG_DIR"
    
    # Use URL-based copy (recommended for WordPress.org SVN)
    # This performs a server-side copy and commit, which is more reliable
    echo "   Copying trunk to tags/$TRUNK_VERSION (server-side copy)..."
    if svn cp "$TRUNK_URL" "$TAG_URL" -m "Tag version $TRUNK_VERSION"; then
        echo "   ✅ Tag created successfully in SVN!"
        echo ""
        echo "   Tag URL: $TAG_URL"
        
        # Update working copy to reflect the new tag (optional, for local visibility)
        cd "$WPORG_DIR"
        if [ -d "tags" ]; then
            echo "   Updating local tags directory..."
            svn update tags 2>/dev/null || true
        fi
        cd "$PLUGIN_DIR"
    else
        echo "   ❌ Error: Failed to create tag in SVN."
        echo "   This may be due to:"
        echo "   - Authentication issues (check SVN credentials)"
        echo "   - Network connectivity problems"
        echo "   - Insufficient permissions"
        echo ""
        echo "   Please verify:"
        echo "   1. SVN credentials are configured (svn auth or ~/.subversion/auth)"
        echo "   2. You have commit access to the repository"
        echo "   3. The tag doesn't already exist (or was properly removed)"
        exit 1
    fi
fi

echo ""
echo "✅ Deployment completed successfully!"
echo ""
echo "📝 Next Steps:"
echo "   - Review the changes in: $WPORG_DIR"
echo "   - The plugin will be available on WordPress.org after SVN sync (usually within minutes)"
if [[ "$DEPLOY_OPTION" == "2" || "$DEPLOY_OPTION" == "3" ]]; then
    echo "   - Tag URL: $SVN_REPO_URL/tags/$TRUNK_VERSION/"
fi
echo ""

