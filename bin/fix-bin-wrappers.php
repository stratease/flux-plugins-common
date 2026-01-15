#!/usr/bin/env php
<?php
/**
 * Fix Composer bin wrapper paths for Strauss-prefixed packages.
 *
 * Composer generates bin wrappers that point to vendor/stratease/flux-plugins-common/bin,
 * but because of Strauss, the actual scripts are in vendor-prefixed/stratease/flux-plugins-common/bin.
 * This script fixes the wrapper paths to point to the correct location.
 *
 * @package FluxPlugins\Common\Bin
 */

// Get the plugin root directory (where composer.json is located).
$plugin_root = getcwd();
if ( ! $plugin_root ) {
    fwrite( STDERR, "❌ Error: Could not determine current working directory.\n" );
    exit( 1 );
}

// Check if we're in a plugin directory (has composer.json).
if ( ! file_exists( $plugin_root . '/composer.json' ) ) {
    fwrite( STDERR, "❌ Error: composer.json not found. Run this script from a plugin root directory.\n" );
    exit( 1 );
}

// Bin scripts to fix.
$bin_scripts = [ 'build-plugin.sh', 'deploy-plugin.sh' ];
$vendor_bin_dir = $plugin_root . '/vendor/bin';
$vendor_prefixed_bin_dir = $plugin_root . '/vendor-prefixed/stratease/flux-plugins-common/bin';

// Check if vendor-prefixed bin directory exists.
if ( ! is_dir( $vendor_prefixed_bin_dir ) ) {
    // If vendor-prefixed doesn't exist, check if vendor exists (non-Strauss installation).
    $vendor_bin_dir_source = $plugin_root . '/vendor/stratease/flux-plugins-common/bin';
    if ( is_dir( $vendor_bin_dir_source ) ) {
        // Non-Strauss installation - wrappers should work as-is.
        exit( 0 );
    }
    fwrite( STDERR, "⚠️  Warning: vendor-prefixed/stratease/flux-plugins-common/bin not found.\n" );
    fwrite( STDERR, "   This is normal if the package hasn't been installed yet.\n" );
    exit( 0 );
}

// Check if vendor/bin exists.
if ( ! is_dir( $vendor_bin_dir ) ) {
    fwrite( STDERR, "⚠️  Warning: vendor/bin directory not found.\n" );
    exit( 0 );
}

$fixed_count = 0;

// Fix each bin script wrapper.
foreach ( $bin_scripts as $script_name ) {
    $wrapper_path = $vendor_bin_dir . '/' . $script_name;
    
    if ( ! file_exists( $wrapper_path ) ) {
        continue;
    }
    
    // Read the wrapper file.
    $wrapper_content = file_get_contents( $wrapper_path );
    if ( $wrapper_content === false ) {
        fwrite( STDERR, "⚠️  Warning: Could not read wrapper: $wrapper_path\n" );
        continue;
    }
    
    // Check if it needs fixing (contains the old path pattern).
    $old_pattern = "../stratease/flux-plugins-common/bin";
    $new_pattern = "../../vendor-prefixed/stratease/flux-plugins-common/bin";
    
    if ( strpos( $wrapper_content, $old_pattern ) !== false ) {
        // Replace the path.
        $fixed_content = str_replace( $old_pattern, $new_pattern, $wrapper_content );
        
        // Write the fixed content.
        if ( file_put_contents( $wrapper_path, $fixed_content ) === false ) {
            fwrite( STDERR, "❌ Error: Could not write fixed wrapper: $wrapper_path\n" );
            continue;
        }
        
        $fixed_count++;
        echo "✅ Fixed wrapper: $script_name\n";
    }
}

if ( $fixed_count > 0 ) {
    echo "✅ Fixed $fixed_count bin wrapper(s).\n";
} else {
    echo "ℹ️  No wrappers needed fixing.\n";
}

exit( 0 );

