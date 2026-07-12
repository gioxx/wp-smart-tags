( function ( $ ) {
	'use strict';

	function ajax( data ) {
		return $.post( wptoData.ajaxUrl, $.extend( { nonce: wptoData.nonce }, data ) );
	}

	$( '#wpto-recount-tags' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );

		ajax( { action: 'wpto_recount_tags' } ).done( function () {
			window.location.reload();
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '#wpto-select-all-unused' ).on( 'change', function () {
		$( '.wpto-unused-checkbox' ).prop( 'checked', $( this ).prop( 'checked' ) );
	} );

	$( '#wpto-delete-unused' ).on( 'click', function () {
		var ids = $( '.wpto-unused-checkbox:checked' ).map( function () {
			return $( this ).val();
		} ).get();

		if ( ! ids.length ) {
			return;
		}

		if ( ! window.confirm( wptoData.i18n.confirmDelete ) ) {
			return;
		}

		ajax( { action: 'wpto_delete_unused', term_ids: ids } ).done( function () {
			window.location.reload();
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
		} );
	} );

	$( '#wpto-start-analysis' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );

		ajax( { action: 'wpto_start_analysis' } ).done( function ( response ) {
			if ( response.success ) {
				pollProgress();
			}
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			$btn.prop( 'disabled', false );
		} );
	} );

	function pollProgress() {
		ajax( { action: 'wpto_get_progress' } ).done( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			var data = response.data;
			$( '#wpto-progress' ).html(
				'<p>' + data.done + ' / ' + data.total + '</p>'
			);

			if ( data.total > 0 && data.done < data.total ) {
				setTimeout( pollProgress, 5000 );
			} else if ( data.total > 0 && data.done >= data.total ) {
				window.location.reload();
			}
		} );
	}

	$( document ).on( 'click', '.wpto-approve', function () {
		if ( ! window.confirm( wptoData.i18n.confirmMerge ) ) {
			return;
		}
		handleSuggestion( $( this ), 'approve' );
	} );

	$( document ).on( 'click', '.wpto-reject', function () {
		handleSuggestion( $( this ), 'reject' );
	} );

	function handleSuggestion( $btn, action ) {
		var id = $btn.data( 'id' );
		$btn.closest( 'tr' ).find( 'button' ).prop( 'disabled', true );

		ajax( { action: 'wpto_suggestion_action', id: id, do: action } ).done( function ( response ) {
			if ( response.success ) {
				$btn.closest( 'tr' ).fadeOut( 200, function () {
					$( this ).remove();
				} );
			} else {
				window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
				$btn.closest( 'tr' ).find( 'button' ).prop( 'disabled', false );
			}
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			$btn.closest( 'tr' ).find( 'button' ).prop( 'disabled', false );
		} );
	}

	$( document ).on( 'click', '.wpto-retry-batch', function () {
		var batchId = $( this ).data( 'batch-id' );
		ajax( { action: 'wpto_retry_batch', batch_id: batchId } ).done( function () {
			window.location.reload();
		} );
	} );

	if ( $( '#wpto-progress' ).length ) {
		var total = parseInt( $( '#wpto-progress' ).data( 'total' ), 10 ) || 0;
		var done = parseInt( $( '#wpto-progress' ).data( 'done' ), 10 ) || 0;
		if ( total > 0 && done < total ) {
			pollProgress();
		}
	}
} )( jQuery );
