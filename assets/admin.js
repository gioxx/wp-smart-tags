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

	var stopRequested = false;

	$( '#wpto-start-analysis' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		stopRequested = false;

		ajax( { action: 'wpto_start_analysis' } ).done( function ( response ) {
			if ( response.success ) {
				renderStatus( response.data );
				$( '#wpto-stop-analysis' ).prop( 'disabled', false );
				processTick();
			}
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			$btn.prop( 'disabled', false );
		} );
	} );

	$( '#wpto-stop-analysis' ).on( 'click', function () {
		stopRequested = true;
		$( this ).prop( 'disabled', true );
		$( '#wpto-current-status' ).text( '' );

		ajax( { action: 'wpto_stop_analysis' } ).done( function ( response ) {
			if ( response.success ) {
				renderStatus( response.data );
				$( '#wpto-start-analysis' ).prop( 'disabled', false );
			}
		} );
	} );

	function processTick() {
		if ( stopRequested ) {
			return;
		}

		$( '#wpto-current-status' ).text( wptoData.i18n.processing );

		ajax( { action: 'wpto_process_tick' } ).done( function ( response ) {
			if ( ! response.success ) {
				window.alert( wptoData.i18n.error );
				return;
			}

			renderStatus( response.data );

			var progress = response.data.progress;

			if ( stopRequested || progress.total === 0 || progress.pending === 0 ) {
				$( '#wpto-current-status' ).text( '' );
				$( '#wpto-start-analysis' ).prop( 'disabled', false );
				$( '#wpto-stop-analysis' ).prop( 'disabled', true );
				if ( progress.total > 0 && progress.pending === 0 ) {
					window.location.reload();
				}
				return;
			}

			processTick();
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
		} );
	}

	function renderStatus( data ) {
		var progress = data.progress;

		$( '#wpto-progress' ).html(
			'<p>' + progress.done + ' / ' + progress.total + '</p>'
		);

		if ( data.log ) {
			var $log = $( '#wpto-log' ).empty();
			data.log.forEach( function ( row ) {
				var text = 'Batch #' + row.id + ' - ' + row.status + ' (' + ( row.processed_at || row.created_at ) + ')';
				if ( row.error_message ) {
					text += ' - ' + row.error_message;
				}
				$( '<li>' ).attr( 'data-batch-id', row.id ).text( text ).appendTo( $log );
			} );
		}
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

	$( document ).on( 'click', '.wpto-restore', function () {
		handleSuggestion( $( this ), 'restore' );
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
		var pending = parseInt( $( '#wpto-progress' ).data( 'pending' ), 10 ) || 0;
		if ( pending > 0 ) {
			$( '#wpto-start-analysis' ).prop( 'disabled', true );
			$( '#wpto-stop-analysis' ).prop( 'disabled', false );
			processTick();
		}
	}
} )( jQuery );
