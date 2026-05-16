<?php
/**
 * PHPUnit bootstrap for flux-plugins-common (WorDBless).
 *
 * @package FluxPlugins\Common\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

\WorDBless\Load::load();
