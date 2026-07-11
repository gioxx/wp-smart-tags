<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Api_Client {

	const API_URL = 'https://api.anthropic.com/v1/messages';
	const API_VERSION = '2023-06-01';

	const VALID_TYPES = array( 'near_duplicate', 'semantic_overlap', 'low_usage_merge' );

	/**
	 * Analyze a batch of tags via the Claude API.
	 *
	 * @param array $tags Array of ['id' => int, 'name' => string, 'count' => int].
	 * @return array|WP_Error List of validated suggestion arrays, or WP_Error on failure.
	 */
	public function analyze_batch( array $tags ) {
		$api_key = WPTO_Settings::get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpto_no_api_key', __( 'Nessuna API key configurata.', 'wp-tags-optimizer' ) );
		}

		$valid_ids = wp_list_pluck( $tags, 'id' );

		$body = array(
			'model'      => WPTO_Settings::get_model(),
			'max_tokens' => 4096,
			'system'     => $this->build_system_prompt(),
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => wp_json_encode( $tags ),
				),
			),
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 120,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'wpto_api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body */
					__( 'Errore API Claude (HTTP %1$d): %2$s', 'wp-tags-optimizer' ),
					$status_code,
					$raw_body
				)
			);
		}

		$decoded = json_decode( $raw_body, true );

		if ( ! is_array( $decoded ) || empty( $decoded['content'][0]['text'] ) ) {
			return new WP_Error( 'wpto_bad_response', __( 'Risposta API in un formato inatteso.', 'wp-tags-optimizer' ) );
		}

		return $this->parse_suggestions( $decoded['content'][0]['text'], $valid_ids );
	}

	private function build_system_prompt() {
		return "Sei un assistente che analizza un elenco di tag di WordPress (formato JSON: id, name, count) per individuare:\n"
			. "1) near_duplicate: quasi-duplicati testuali (refusi, plurali, maiuscole/minuscole, trattini/spazi)\n"
			. "2) semantic_overlap: tag diversi nel testo ma con significato sovrapponibile\n"
			. "3) low_usage_merge: tag con count molto basso che potrebbero confluire in un tag piu' ampio gia' presente nell'elenco\n\n"
			. "Rispondi SOLO con JSON valido, nessun testo aggiuntivo, nessun blocco di codice, nel formato esatto:\n"
			. '{"suggestions":[{"type":"near_duplicate|semantic_overlap|low_usage_merge","source_tag_ids":[123],"target_tag_id":456,"reason":"...","confidence":0.0}]}' . "\n\n"
			. "Regole: usa solo gli id presenti nell'elenco fornito; target_tag_id deve essere diverso da tutti i source_tag_ids; "
			. "se non trovi suggerimenti significativi restituisci {\"suggestions\":[]}.";
	}

	private function parse_suggestions( $text, array $valid_ids ) {
		$text = trim( $text );

		// Strip an accidental markdown code fence if the model added one.
		if ( 0 === strpos( $text, '```' ) ) {
			$text = preg_replace( '/^```[a-zA-Z]*\n?/', '', $text );
			$text = preg_replace( '/```$/', '', $text );
			$text = trim( $text );
		}

		$decoded = json_decode( $text, true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['suggestions'] ) || ! is_array( $decoded['suggestions'] ) ) {
			return new WP_Error( 'wpto_invalid_json', __( 'La risposta del modello non contiene JSON valido.', 'wp-tags-optimizer' ) );
		}

		$valid_ids_flip = array_flip( array_map( 'intval', $valid_ids ) );
		$suggestions    = array();

		foreach ( $decoded['suggestions'] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type = isset( $raw['type'] ) ? sanitize_key( $raw['type'] ) : '';
			if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
				continue;
			}

			$source_ids = array();
			if ( ! empty( $raw['source_tag_ids'] ) && is_array( $raw['source_tag_ids'] ) ) {
				foreach ( $raw['source_tag_ids'] as $sid ) {
					$sid = (int) $sid;
					if ( isset( $valid_ids_flip[ $sid ] ) ) {
						$source_ids[] = $sid;
					}
				}
			}

			$target_id = isset( $raw['target_tag_id'] ) ? (int) $raw['target_tag_id'] : 0;

			if ( empty( $source_ids ) || ! $target_id || ! isset( $valid_ids_flip[ $target_id ] ) ) {
				continue;
			}

			if ( in_array( $target_id, $source_ids, true ) ) {
				continue;
			}

			$confidence = isset( $raw['confidence'] ) ? (float) $raw['confidence'] : 0.5;
			$confidence = max( 0.0, min( 1.0, $confidence ) );

			$suggestions[] = array(
				'type'            => $type,
				'source_term_ids' => $source_ids,
				'target_term_id'  => $target_id,
				'reason'          => isset( $raw['reason'] ) ? sanitize_text_field( $raw['reason'] ) : '',
				'confidence'      => $confidence,
			);
		}

		return $suggestions;
	}
}
