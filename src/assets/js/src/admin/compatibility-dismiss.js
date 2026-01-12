/**
 * Compatibility notice dismiss handler.
 *
 * Handles dismissing compatibility notices via AJAX.
 * Works with all plugins using the shared compatibility system.
 *
 * @package FluxPlugins\Common
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize compatibility notice dismiss functionality.
	 */
	function initCompatibilityDismiss() {
		// Handle dismiss button clicks for all compatibility notices.
		$(document).on('click', '.flux-plugins-compatibility-notice .flux-plugins-dismiss', function(e) {
			e.preventDefault();

			var $notice = $(this).closest('.flux-plugins-compatibility-notice');
			var dismissUrl = $(this).data('dismiss-url');
			var hash = $(this).data('hash');

			if (!dismissUrl || !hash) {
				return;
			}

			// Make AJAX request to dismiss notice.
			$.ajax({
				url: dismissUrl,
				type: 'GET',
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						// Fade out and remove notice.
						$notice.fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						console.error('Failed to dismiss notice:', response.data);
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error dismissing notice:', error);
				}
			});
		});
	}

	// Initialize on document ready.
	$(document).ready(function() {
		initCompatibilityDismiss();
	});

})(jQuery);

