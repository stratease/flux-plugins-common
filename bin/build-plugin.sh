#!/bin/bash

# Build script for Flux Plugins WordPress plugin
# Builds production files directly into wporg/trunk/ (SVN repo root)
# Use deploy-plugin.sh to commit and tag releases in SVN

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
PACKAGE_JSON="$PLUGIN_DIR/package.json"
README_FILE="$PLUGIN_DIR/readme.txt"

# Remove Windows Zone.Identifier files (alternate data streams that can accidentally get copied)
# These files are created by Windows when downloading files from the internet
echo "🧹 Cleaning up Windows Zone.Identifier files..."
ZONE_IDENTIFIER_COUNT=0
# Use find to locate all :Zone.Identifier files in the plugin directory
while IFS= read -r -d '' file; do
    if [ -f "$file" ]; then
        rm -f "$file" 2>/dev/null && ZONE_IDENTIFIER_COUNT=$((ZONE_IDENTIFIER_COUNT + 1))
    fi
done < <(find "$PLUGIN_DIR" -type f -name "*:Zone.Identifier" -print0 2>/dev/null || true)

if [ "$ZONE_IDENTIFIER_COUNT" -gt 0 ]; then
    echo "   ✅ Removed $ZONE_IDENTIFIER_COUNT Zone.Identifier file(s)"
else
    echo "   ℹ️  No Zone.Identifier files found"
fi
echo ""

# Detect the version constant name in the plugin file (e.g., FLUX_MEDIA_OPTIMIZER_VERSION).
VERSION_CONST_NAME=$(grep -E "define\(\s*'[^']+_VERSION'" "$PLUGIN_FILE" | head -1 | sed -n "s/.*'\([^']\+_VERSION\)'.*/\1/p")

# Function to extract version from plugin file header
extract_plugin_header_version() {
    if [ -f "$PLUGIN_FILE" ]; then
        grep "Version:" "$PLUGIN_FILE" | sed 's/.*Version:[[:space:]]*//' | tr -d '\r\n' | tr -d ' ' | head -1
    fi
}

# Function to extract version from PHP constant
extract_php_constant_version() {
    if [ -f "$PLUGIN_FILE" ] && [ -n "$VERSION_CONST_NAME" ]; then
        # Use sed instead of grep -P for better compatibility (macOS doesn't support -P)
        grep "$VERSION_CONST_NAME" "$PLUGIN_FILE" | sed -n "s/.*'$VERSION_CONST_NAME',[[:space:]]*'\([^']*\)'.*/\1/p" | head -1
    fi
}

# Function to extract version from package.json
extract_package_json_version() {
    if [ -f "$PACKAGE_JSON" ]; then
        grep '"version"' "$PACKAGE_JSON" | sed 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/' | head -1
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

# Extract current versions
CURRENT_HEADER_VERSION=$(extract_plugin_header_version)
CURRENT_CONSTANT_VERSION=$(extract_php_constant_version)
CURRENT_PACKAGE_VERSION=$(extract_package_json_version)

# Determine current version (prefer header, fallback to constant, then package.json)
CURRENT_VERSION="$CURRENT_HEADER_VERSION"
if [ -z "$CURRENT_VERSION" ]; then
    CURRENT_VERSION="$CURRENT_CONSTANT_VERSION"
fi
if [ -z "$CURRENT_VERSION" ]; then
    CURRENT_VERSION="$CURRENT_PACKAGE_VERSION"
fi

# If still no version found, use timestamp
if [ -z "$CURRENT_VERSION" ] || [[ "$CURRENT_VERSION" == *"*"* ]]; then
    CURRENT_VERSION="$(date +%Y%m%d-%H%M%S)"
fi

# Display current version information
echo "📋 Current Version Information:"
echo "   Plugin:        $PLUGIN_NAME"
echo "   Plugin File:   $PLUGIN_FILE"
echo "   Plugin Header: ${CURRENT_HEADER_VERSION:-not found}"
echo "   PHP Constant:  ${CURRENT_CONSTANT_VERSION:-not found}"
echo "   package.json:  ${CURRENT_PACKAGE_VERSION:-not found}"
echo ""

# Prompt for version
echo "🔢 Version Selection:"
echo "   Current version: $CURRENT_VERSION"
read -p "   Enter new version (or press Enter to keep current): " NEW_VERSION

# Track if version is being bumped
VERSION_BUMPED=false

# Use current version if empty
if [ -z "$NEW_VERSION" ]; then
    NEW_VERSION="$CURRENT_VERSION"
    echo "   Using current version: $NEW_VERSION"
else
    VERSION_BUMPED=true
    # Validate version format
    if ! validate_version "$NEW_VERSION"; then
        echo "   ⚠️  Warning: Version format may be invalid (expected: x.y.z or x.y.z-suffix)"
        read -p "   Continue anyway? (y/N): " CONFIRM
        if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
            echo "❌ Build cancelled."
            exit 1
        fi
    fi
    
    # Update version in plugin file
    echo "   Updating version in plugin file..."
    
    # Update plugin header version
    if [ -f "$PLUGIN_FILE" ]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS sed
            sed -i '' "s/Version:[[:space:]]*[0-9.]*/Version: $NEW_VERSION/" "$PLUGIN_FILE"
        else
            # Linux sed
            sed -i "s/Version:[[:space:]]*[0-9.]*/Version: $NEW_VERSION/" "$PLUGIN_FILE"
        fi
    fi
    
    # Update PHP constant version
    if [ -f "$PLUGIN_FILE" ] && [ -n "$VERSION_CONST_NAME" ]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS sed - escape single quotes properly
            sed -i '' "s/define( '$VERSION_CONST_NAME', '[^']*' );/define( '$VERSION_CONST_NAME', '$NEW_VERSION' );/" "$PLUGIN_FILE"
        else
            # Linux sed - escape single quotes properly
            sed -i "s/define( '$VERSION_CONST_NAME', '[^']*' );/define( '$VERSION_CONST_NAME', '$NEW_VERSION' );/" "$PLUGIN_FILE"
        fi
        # Verify the update worked
        UPDATED_CONSTANT=$(extract_php_constant_version)
        if [ "$UPDATED_CONSTANT" != "$NEW_VERSION" ]; then
            echo "   ⚠️  Warning: PHP constant may not have updated correctly. Please verify manually."
        fi
    fi
    
    # Update package.json version
    if [ -f "$PACKAGE_JSON" ]; then
        if command -v npm &> /dev/null; then
            npm version "$NEW_VERSION" --no-git-tag-version --allow-same-version > /dev/null 2>&1 || {
                # Fallback to sed if npm version fails
                if [[ "$OSTYPE" == "darwin"* ]]; then
                    sed -i '' "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
                else
                    sed -i "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
                fi
            }
        else
            # Fallback to sed if npm is not available
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
            else
                sed -i "s/\"version\":[[:space:]]*\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" "$PACKAGE_JSON"
            fi
        fi
    fi
    
    # Update Stable tag in readme.txt (WordPress.org requirement)
    if [ -f "$README_FILE" ]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS sed
            sed -i '' "s/Stable tag:[[:space:]]*[0-9.]*/Stable tag: $NEW_VERSION/" "$README_FILE"
        else
            # Linux sed
            sed -i "s/Stable tag:[[:space:]]*[0-9.]*/Stable tag: $NEW_VERSION/" "$README_FILE"
        fi
    fi
    
    # Prompt for changelog message
    echo ""
    echo "📝 Changelog Entry:"
    echo "   Enter changelog message for version $NEW_VERSION"
    echo "   (Use bullet points, one per line. Press Enter twice when done, or just Enter to skip):"
    CHANGELOG_ENTRY=""
    CHANGELOG_LINES=()
    FIRST_LINE=true
    while IFS= read -r line; do
        # If first line is empty, skip changelog entry
        if [ "$FIRST_LINE" = true ] && [ -z "$line" ]; then
            CHANGELOG_ENTRY=""
            break
        fi
        FIRST_LINE=false
        
        # If line is empty and we have entries, we're done
        if [ -z "$line" ] && [ ${#CHANGELOG_LINES[@]} -gt 0 ]; then
            break
        fi
        
        # Check for skip command
        if [ "$line" = "skip" ]; then
            CHANGELOG_ENTRY=""
            break
        fi
        
        # Add non-empty line
        if [ -n "$line" ]; then
            CHANGELOG_LINES+=("$line")
        fi
    done
    
    # Format changelog entry
    if [ ${#CHANGELOG_LINES[@]} -gt 0 ]; then
        CHANGELOG_ENTRY=""
        for line in "${CHANGELOG_LINES[@]}"; do
            # Ensure line starts with * if it doesn't already
            if [[ ! "$line" =~ ^\* ]]; then
                CHANGELOG_ENTRY+="* $line"$'\n'
            else
                CHANGELOG_ENTRY+="$line"$'\n'
            fi
        done
    fi
    
    # Update changelog files if entry provided
    if [ -n "$CHANGELOG_ENTRY" ]; then
        CHANGELOG_FILE="$PLUGIN_DIR/changelog.txt"
        
        # Update changelog.txt (full history) - prepend new entry
        if [ -f "$CHANGELOG_FILE" ]; then
            # Prepend new version entry to changelog.txt
            NEW_CHANGELOG_ENTRY="= $NEW_VERSION ="$'\n'"$CHANGELOG_ENTRY"$'\n'
            if [[ "$OSTYPE" == "darwin"* ]]; then
                echo "$NEW_CHANGELOG_ENTRY$(cat "$CHANGELOG_FILE")" > "$CHANGELOG_FILE"
            else
                echo "$NEW_CHANGELOG_ENTRY$(cat "$CHANGELOG_FILE")" > "$CHANGELOG_FILE"
            fi
        else
            # Create new changelog.txt
            echo "= $NEW_VERSION =" > "$CHANGELOG_FILE"
            echo "$CHANGELOG_ENTRY" >> "$CHANGELOG_FILE"
        fi
        
        # Update readme.txt changelog section (keep only last 3 entries)
        if [ -f "$README_FILE" ]; then
            # Find changelog section boundaries
            # Use head -1 to get only the first match in case of duplicates
            CHANGELOG_HEADER_LINE=$(grep -n "^== Changelog ==$" "$README_FILE" | head -1 | cut -d: -f1)
            UPGRADE_START_LINE=$(grep -n "^== Upgrade Notice ==$" "$README_FILE" | head -1 | cut -d: -f1)
            
            if [ -n "$CHANGELOG_HEADER_LINE" ]; then
                # Extract existing changelog entries (lines after "== Changelog ==")
                # Skip the header line and any empty lines immediately after it
                CHANGELOG_START_LINE=$((CHANGELOG_HEADER_LINE + 1))
                if [ -n "$UPGRADE_START_LINE" ]; then
                    EXISTING_CHANGELOG=$(sed -n "${CHANGELOG_START_LINE},$((UPGRADE_START_LINE - 1))p" "$README_FILE")
                else
                    EXISTING_CHANGELOG=$(sed -n "${CHANGELOG_START_LINE},\$p" "$README_FILE")
                fi
                
                # Extract version entries and keep only the 2 most recent (we're adding 1 new = 3 total)
                KEEP_ENTRIES=""
                VERSION_BLOCKS=()
                CURRENT_BLOCK=""
                
                # Process changelog line by line, handling version entries
                # Use a more robust method to read lines
                while IFS= read -r line || [ -n "$line" ]; do
                    # Trim leading/trailing whitespace for pattern matching
                    TRIMMED_LINE=$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
                    
                    # Check if line is a version header (format: "= x.y.z =" with optional spaces)
                    if [[ "$TRIMMED_LINE" =~ ^=\ [0-9]+\.[0-9]+\.[0-9]+[[:space:]]*=$ ]]; then
                        # Save previous block if exists
                        if [ -n "$CURRENT_BLOCK" ]; then
                            VERSION_BLOCKS+=("$CURRENT_BLOCK")
                        fi
                        # Start new block with version header (preserve original line format)
                        CURRENT_BLOCK="$line"$'\n'
                    elif [ -n "$CURRENT_BLOCK" ]; then
                        # Add line to current block (including empty lines)
                        CURRENT_BLOCK+="$line"$'\n'
                    fi
                done <<< "$EXISTING_CHANGELOG"
                
                # Add last block if exists
                if [ -n "$CURRENT_BLOCK" ]; then
                    VERSION_BLOCKS+=("$CURRENT_BLOCK")
                fi
                
                # Keep only the 2 most recent entries (indices 0 and 1, if they exist)
                BLOCK_COUNT=${#VERSION_BLOCKS[@]}
                MAX_KEEP=2
                if [ $BLOCK_COUNT -lt $MAX_KEEP ]; then
                    MAX_KEEP=$BLOCK_COUNT
                fi
                for ((i=0; i<MAX_KEEP; i++)); do
                    KEEP_ENTRIES+="${VERSION_BLOCKS[$i]}"
                done
                
                # Create new changelog section
                NEW_CHANGELOG_SECTION="== Changelog =="$'\n'$'\n'"= $NEW_VERSION ="$'\n'"$CHANGELOG_ENTRY"
                if [ -n "$KEEP_ENTRIES" ]; then
                    # Add blank line between new entry and old entries
                    NEW_CHANGELOG_SECTION+=$'\n'"$KEEP_ENTRIES"
                fi
                
                # Replace changelog section in readme.txt
                # Use CHANGELOG_HEADER_LINE (already found above) for replacement
                TEMP_README=$(mktemp)
                # Write everything before changelog section (up to and including line before header)
                if [ -n "$CHANGELOG_HEADER_LINE" ] && [ "$CHANGELOG_HEADER_LINE" -gt 1 ]; then
                    head -n $((CHANGELOG_HEADER_LINE - 1)) "$README_FILE" > "$TEMP_README"
                fi
                # Write new changelog section
                echo "$NEW_CHANGELOG_SECTION" >> "$TEMP_README"
                # Write everything after changelog section (upgrade notice and beyond)
                if [ -n "$UPGRADE_START_LINE" ]; then
                    tail -n +$UPGRADE_START_LINE "$README_FILE" >> "$TEMP_README"
                fi
                mv "$TEMP_README" "$README_FILE"
            fi
        fi
        
        echo "   ✅ Changelog updated"
    else
        echo "   ⏭️  Skipped changelog update"
    fi
    
    echo "   ✅ Version updated to: $NEW_VERSION"
    # Store tag info for output at end of build
    TAG_NAME="v${NEW_VERSION}"
fi

# Set version for build
VERSION="$NEW_VERSION"

# Confirm build
echo ""
echo "🚀 Build Configuration:"
echo "   Version: $VERSION"
echo "   Output: ${PLUGIN_NAME}-v${VERSION}.zip"
echo "   SVN Trunk: wporg/trunk/"
read -p "   Proceed with build? (Y/n): " CONFIRM_BUILD
if [[ "$CONFIRM_BUILD" =~ ^[Nn]$ ]]; then
    echo "❌ Build cancelled."
    exit 1
fi
echo ""

# Create zip file name with version
ZIP_FILE="$PLUGIN_DIR/${PLUGIN_NAME}-v${VERSION}.zip"

# Remove existing zip if it exists
if [ -f "$ZIP_FILE" ]; then
    echo "🗑️  Removing existing zip file..."
    rm "$ZIP_FILE"
fi

# Change to plugin directory
cd "$PLUGIN_DIR"

# Setup SVN repo structure (optional - will use local directory if SVN not available)
WPORG_DIR="$PLUGIN_DIR/wporg"
SVN_REPO_URL="https://plugins.svn.wordpress.org/$PLUGIN_NAME"
TRUNK_DIR="$WPORG_DIR/trunk"
SVN_AVAILABLE=false
SVN_SETUP=false

# Check if SVN is available
if command -v svn &> /dev/null; then
    SVN_AVAILABLE=true
else
    echo "ℹ️  SVN is not installed. Building to local directory (wporg/trunk/)."
    echo "   Deploy to WordPress.org SVN will require SVN installation."
    echo ""
fi

# Try to setup SVN repo if available
if [ "$SVN_AVAILABLE" = true ]; then
    # Check if SVN repo is already checked out
    if [ -d "$WPORG_DIR/.svn" ]; then
        SVN_SETUP=true
        echo "📦 Using existing SVN repository."
    else
        # Try to checkout SVN repo
        echo "📦 SVN repository not found. Attempting to check out..."
        echo "   This may take a few moments..."
        mkdir -p "$WPORG_DIR"
        if svn checkout "$SVN_REPO_URL" "$WPORG_DIR" --depth immediates 2>/dev/null; then
            if svn update "$TRUNK_DIR" --set-depth infinity 2>/dev/null; then
                SVN_SETUP=true
                echo "✅ SVN repository checked out successfully."
            else
                echo "⚠️  SVN checkout completed but trunk update failed. Using local directory."
                SVN_SETUP=false
            fi
        else
            echo "⚠️  SVN checkout failed (repository may not exist yet or requires authentication)."
            echo "   Continuing with local build directory..."
            SVN_SETUP=false
        fi
        echo ""
    fi
fi

# Ensure trunk directory exists (create if SVN not available or setup failed)
if [ ! -d "$TRUNK_DIR" ]; then
    echo "📁 Creating local build directory: $TRUNK_DIR"
    mkdir -p "$TRUNK_DIR"
fi

# Install production-only dependencies
echo "🔧 Installing production-only dependencies..."
composer install --ignore-platform-reqs --no-dev --optimize-autoloader --no-interaction

# Build frontend assets
echo "🏗️ Building frontend assets..."
npm run build

# Build directly into wporg/trunk/ (SVN trunk)
echo ""
echo "📦 Building files directly into SVN trunk (single source of truth)..."

# Ensure trunk directory exists
mkdir -p "$TRUNK_DIR"

# Remove existing files in trunk (but preserve .svn directory if it exists)
if [ "$SVN_SETUP" = true ]; then
    find "$TRUNK_DIR" -mindepth 1 ! -path '*/.svn*' -delete 2>/dev/null || true
else
    # For local builds, remove everything (no .svn to preserve)
    find "$TRUNK_DIR" -mindepth 1 -delete 2>/dev/null || true
fi

# Copy files to trunk with exclusions (single set of exclusions)
echo "📋 Copying plugin files to trunk (excluding development files)..."
rsync -av \
    --exclude='bin' \
    --exclude='vendor/stratease/*/bin' \
    --exclude='vendor-prefixed/stratease/*/bin' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='.vscode' \
    --exclude='tests' \
    --exclude='.htaccess' \
    --exclude='.git*' \
    --exclude='.phpunit*' \
    --exclude='*.zip' \
    --exclude='*.log' \
    --exclude='*.xml' \
    --exclude='*.lock' \
    --exclude='.gitignore' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='webpack.config.js' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='*.phar' \
    --exclude='phpunit.xml' \
    --exclude='vendor-prefixed/plugins' \
    --exclude='wporg' \
    "$PLUGIN_DIR/" "$TRUNK_DIR/"

# Create zip file FROM trunk (ensures zip matches trunk exactly)
# Only exclude .svn since all other files were already filtered by rsync
# Zip must contain plugin-name folder at root (WordPress.org requirement)
echo "📦 Creating plugin zip file from trunk..."
# Create temporary directory with plugin name for zip structure
TEMP_ZIP_DIR="/tmp/flux-media-optimizer-zip-$$"
mkdir -p "$TEMP_ZIP_DIR/$PLUGIN_NAME"
# Copy trunk contents to temp directory (excluding .svn)
rsync -av --exclude='.svn' "$TRUNK_DIR/" "$TEMP_ZIP_DIR/$PLUGIN_NAME/"
# Create zip from temp directory
cd "$TEMP_ZIP_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_NAME/"
# Clean up temp directory
rm -rf "$TEMP_ZIP_DIR"

# Return to plugin directory for cleanup
cd "$PLUGIN_DIR"

# Restore full development environment
echo "🔄 Restoring development environment..."
composer install --ignore-platform-reqs --optimize-autoloader --no-interaction

# Calculate sizes
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
TRUNK_SIZE=$(du -sh "$TRUNK_DIR" | cut -f1)

echo ""
echo "✅ Plugin built successfully!"
echo "📦 Zip File: $ZIP_FILE"
echo "📏 Zip Size: $ZIP_SIZE"
echo "📦 Build Directory: $TRUNK_DIR"
echo "📏 Build Size: $TRUNK_SIZE"
echo "🏷️  Version: $VERSION"
if [ "$SVN_SETUP" = true ]; then
    echo "📦 SVN: Connected to WordPress.org repository"
    echo ""
    echo "📝 Next Step:"
    echo "   Run ./bin/deploy-plugin.sh to commit and tag this version in SVN"
else
    echo "📦 SVN: Not connected (local build only)"
    echo ""
    echo "📝 Next Steps:"
    echo "   - Files are ready in: $TRUNK_DIR"
    if [ "$SVN_AVAILABLE" = false ]; then
        echo "   - Install SVN to enable deployment to WordPress.org"
    else
        echo "   - Set up SVN repository to enable deployment"
    fi
fi
echo ""

# Output git tag command if version was bumped (at end so it doesn't get lost)
if [ "$VERSION_BUMPED" = true ]; then
    echo "🏷️  Git Tag Command (run after committing version changes):"
    echo "   git tag -a $TAG_NAME -m \"Release version $NEW_VERSION\""
    echo "   git push origin $TAG_NAME"
    echo ""
fi
