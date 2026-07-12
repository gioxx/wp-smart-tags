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

	$( '#wpto-test-api-key' ).on( 'click', function () {
		var $btn = $( this );
		var $result = $( '#wpto-test-api-key-result' );
		var apiKey = $( '#wpto_api_key' ).val();

		if ( ! apiKey ) {
			$result.text( wptoData.i18n.enterApiKey );
			return;
		}

		$btn.prop( 'disabled', true );
		$result.text( wptoData.i18n.testingApiKey );

		ajax( { action: 'wpto_test_api_key', api_key: apiKey } ).done( function ( response ) {
			$result.text( response.success ? response.data.message : ( response.data && response.data.message ? response.data.message : wptoData.i18n.error ) );
		} ).fail( function () {
			$result.text( wptoData.i18n.error );
		} ).always( function () {
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
		if ( ! window.confirm( wptoData.i18n.confirmNewAnalysis ) ) {
			return;
		}

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

	$( document ).on( 'change', '.wpto-select-all-suggestions', function () {
		var group = $( this ).data( 'group' );
		$( '.wpto-suggestion-checkbox[data-group="' + group + '"]' ).prop( 'checked', $( this ).prop( 'checked' ) );
		updateBulkButtons( group );
	} );

	$( document ).on( 'change', '.wpto-suggestion-checkbox', function () {
		updateBulkButtons( $( this ).data( 'group' ) );
	} );

	function updateBulkButtons( group ) {
		var anyChecked = $( '.wpto-suggestion-checkbox[data-group="' + group + '"]:checked' ).length > 0;
		$( '.wpto-bulk-approve[data-group="' + group + '"], .wpto-bulk-reject[data-group="' + group + '"], .wpto-bulk-restore[data-group="' + group + '"]' ).prop( 'disabled', ! anyChecked );
	}

	$( document ).on( 'click', '.wpto-bulk-approve', function () {
		handleBulkSuggestion( $( this ), 'approve', wptoData.i18n.confirmBulkApprove );
	} );

	$( document ).on( 'click', '.wpto-bulk-reject', function () {
		handleBulkSuggestion( $( this ), 'reject', wptoData.i18n.confirmBulkReject );
	} );

	$( document ).on( 'click', '.wpto-bulk-restore', function () {
		handleBulkSuggestion( $( this ), 'restore', wptoData.i18n.confirmBulkRestore );
	} );

	function handleBulkSuggestion( $btn, action, confirmMessage ) {
		var group = $btn.data( 'group' );
		var $checkboxes = $( '.wpto-suggestion-checkbox[data-group="' + group + '"]:checked' );
		var ids = $checkboxes.map( function () {
			return $( this ).val();
		} ).get();

		if ( ! ids.length ) {
			window.alert( wptoData.i18n.noneSelected );
			return;
		}

		if ( ! window.confirm( confirmMessage ) ) {
			return;
		}

		var $groupButtons = $( '.wpto-bulk-approve[data-group="' + group + '"], .wpto-bulk-reject[data-group="' + group + '"], .wpto-bulk-restore[data-group="' + group + '"]' );
		$groupButtons.prop( 'disabled', true );

		ajax( { action: 'wpto_bulk_suggestion_action', ids: ids, do: action } ).done( function ( response ) {
			if ( ! response.success ) {
				window.alert( response.data && response.data.message ? response.data.message : wptoData.i18n.error );
				updateBulkButtons( group );
				return;
			}

			var data = response.data;

			$( data.succeeded ).each( function ( i, id ) {
				$( '.wpto-suggestion-checkbox[value="' + id + '"][data-group="' + group + '"]' ).closest( 'tr' ).fadeOut( 200, function () {
					$( this ).remove();
				} );
			} );

			if ( data.failed && Object.keys( data.failed ).length ) {
				var messages = [ wptoData.i18n.bulkFailed ];
				$.each( data.failed, function ( id, message ) {
					messages.push( '#' + id + ': ' + message );
				} );
				window.alert( messages.join( '\n' ) );
			}

			updateBulkButtons( group );
		} ).fail( function () {
			window.alert( wptoData.i18n.error );
			updateBulkButtons( group );
		} );
	}

	function handleSuggestion( $btn, action ) {
		var id = $btn.data( 'id' );
		var $row = $btn.closest( 'tr' );
		$row.find( 'button' ).prop( 'disabled', true );

		var payload = { action: 'wpto_suggestion_action', id: id, do: action };

		if ( 'approve' === action ) {
			payload.target_id = $row.find( '.wpto-target-select' ).val();
		}

		ajax( payload ).done( function ( response ) {
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

	$( '#wpto-confirm-merge-form' ).on( 'submit', function ( e ) {
		if ( ! window.confirm( wptoData.i18n.confirmMerge ) ) {
			e.preventDefault();
		}
	} );

	$( '#wpto-tags-filter' ).on( 'submit', function ( e ) {
		var action = $( this ).find( 'select[name="action"]' ).val();
		if ( '-1' === action ) {
			action = $( this ).find( 'select[name="action2"]' ).val();
		}
		if ( 'delete' === action && ! window.confirm( wptoData.i18n.confirmDeleteTags ) ) {
			e.preventDefault();
		}
	} );

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
