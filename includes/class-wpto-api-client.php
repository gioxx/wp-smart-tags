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
			return new WP_Error( 'wpto_no_api_key', __( 'No API key configured.', 'ai-tags-optimizer' ) );
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
					__( 'Claude API error (HTTP %1$d): %2$s', 'ai-tags-optimizer' ),
					$status_code,
					$raw_body
				)
			);
		}

		$decoded = json_decode( $raw_body, true );

		if ( ! is_array( $decoded ) || empty( $decoded['content'][0]['text'] ) ) {
			return new WP_Error( 'wpto_bad_response', __( 'Unexpected API response format.', 'ai-tags-optimizer' ) );
		}

		return $this->parse_suggestions( $decoded['content'][0]['text'], $valid_ids );
	}

	/**
	 * Sends a minimal request to validate an API key without consuming a real batch.
	 *
	 * @param string $api_key Key to test.
	 * @return true|WP_Error True on success, WP_Error with a human-readable message otherwise.
	 */
	public function test_connection( $api_key ) {
		if ( empty( $api_key ) ) {
			return new WP_Error( 'wpto_no_api_key', __( 'No API key configured.', 'ai-tags-optimizer' ) );
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => WPTO_Settings::get_model(),
						'max_tokens' => 1,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Hi',
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : wp_remote_retrieve_body( $response );

			return new WP_Error(
				'wpto_api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__( 'Claude API error (HTTP %1$d): %2$s', 'ai-tags-optimizer' ),
					$status_code,
					$message
				)
			);
		}

		return true;
	}

	private function build_system_prompt() {
		$language = trim( WPTO_Settings::get_ai_language() );

		if ( '' !== $language ) {
			$language_instruction = sprintf( 'Always write the "reason" field in %s, regardless of the language of the tag names.', $language );
		} else {
			$language_instruction = 'Write the "reason" field in the same language as the tag names.';
		}

		return "You are an assistant analyzing a list of WordPress tags (JSON format: id, name, count) to identify:\n"
			. "1) near_duplicate: textual near-duplicates (typos, plurals, casing, hyphens/spaces) referring to the exact same thing, e.g. \"Wii U\" and \"Nintendo Wii U\"\n"
			. "2) semantic_overlap: tags that are true synonyms describing the exact same underlying concept with different wording, NOT tags that are merely related, topically adjacent, or a broader/narrower category of each other\n"
			. "3) low_usage_merge: tags with a very low count (typically 1-2 posts) that are a clear, unambiguous narrower case of a broader tag already present in the list, where merging loses no meaningful distinction\n\n"
			. "Be conservative for types 2 and 3: these are far more prone to false positives than type 1. If you are not highly confident two tags mean the same thing, do not suggest merging them - omitting a suggestion is always better than a wrong one. Only use confidence 0.7 or higher for semantic_overlap and low_usage_merge; near_duplicate can use the full range.\n\n"
			. "Reply with ONLY valid JSON, no extra text, no code block, in this exact format:\n"
			. '{"suggestions":[{"type":"near_duplicate|semantic_overlap|low_usage_merge","source_tag_ids":[123],"target_tag_id":456,"reason":"...","confidence":0.0}]}' . "\n\n"
			. "Rules: only use ids present in the given list; target_tag_id must differ from every source_tag_id; "
			. "if you find no meaningful suggestions, return {\"suggestions\":[]}. {$language_instruction}";
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
			return new WP_Error( 'wpto_invalid_json', __( 'The model response does not contain valid JSON.', 'ai-tags-optimizer' ) );
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
