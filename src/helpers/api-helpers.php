<?php

namespace Team51\Helper;

class API_Helper {

	private const PRESABLE_TOKEN_FILE         = __DIR__ . '/pressable_token.json';
	private const PRESABLE_TOKEN_EXPIRE_AFTER = '-8 hours';

	public function call_pressable_api( $query, $method, $data ) {
		$api_request_url = PRESSABLE_API_ENDPOINT . $query;

		$data = json_encode( $data );

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer '. $this->get_pressable_api_token(),
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'  => $headers,
				'method'  => $method,
				'content' => $data,
				'ignore_errors' => true,
			)
		);

		$context  = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		preg_match( '/\d\d\d/', $http_response_header[0], $response_code );

		$response_code = $response_code[0];

		if ( '401' === $response_code ) {
			echo "Pressable authentication failed! Your credentials are probably out of date. Please update them before running this again or your Pressable account may be locked." . PHP_EOL;
		}

		return json_decode( $result );
	}

	public function get_pressable_api_token() {
		$access_token = $this->get_local_pressable_access_token();
		if( ! empty( $access_token ) ) {
			// Re-use access token
			echo "\nRe-using OAuth token stored locally.\n";
			return $access_token;
		}

		// Otherwise, generate a new token
		$api_request_url = PRESSABLE_API_TOKEN_ENDPOINT;

		$data = array(
			'client_id'     => PRESSABLE_API_APP_CLIENT_ID,
			'client_secret' => PRESSABLE_API_APP_CLIENT_SECRET,
		);

		if ( defined( 'PRESSABLE_ACCOUNT_PASSWORD' ) && defined( 'PRESSABLE_ACCOUNT_EMAIL' ) ) {
			$data['grant_type'] = 'password';
			$data['email']      = PRESSABLE_ACCOUNT_EMAIL;
			$data['password']   = PRESSABLE_ACCOUNT_PASSWORD;
		} elseif ( defined( 'PRESSABLE_API_REFRESH_TOKEN' ) ) {
			$data['grant_type']    = 'refresh_token';
			$data['refresh_token'] = $this->get_local_pressable_refresh_token();
		} else {
			echo "\n❌ Please configure your config.json to have email/password, or refresh and access tokens.</error>";
			exit;
		}

		$data = http_build_query( $data );

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded',
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'  => $headers,
				'method'  => 'POST',
				'content' => $data
			)
		);

		$context  = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		$result = json_decode( $result );

		if( empty( $result->access_token ) ) {
			die( "Pressable API token could not be retrieved. Aborting!\n" );
		}

		// Store pressable token for future use (only when using local constants)
		$this->set_local_pressable_tokens( $result );

		return $result->access_token;
	}

	/**
	 * Given a client_id and client_secret, a pair of access/refresh tokens is
	 * retrieved from Pressable API.
	 * The idea here is to not use the locally defined PRESSABLE_API_APP_CLIENT_ID constants,
	 * but get a set of tokens for someone else.
	 */
	public function get_pressable_api_auth_tokens( $client_id, $client_secret ) {
		$api_request_url = PRESSABLE_API_TOKEN_ENDPOINT;
		$data = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'grant_type'    => 'password',
			'email'         => PRESSABLE_ACCOUNT_EMAIL,
			'password'      => PRESSABLE_ACCOUNT_PASSWORD,
		);

		$data = http_build_query( $data );

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded',
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'  => $headers,
				'method'  => 'POST',
				'content' => $data
			)
		);

		$context  = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		$result = json_decode( $result );

		if( empty( $result->refresh_token ) ) {
			die( "Pressable API Refresh token could not be retrieved. Aborting!\n" );
		}

		return array(
			'refresh_token' => $result->refresh_token,
			'access_token'  => $result->access_token,
		);
	}

	public function call_github_api( $query, $data, $method = 'POST' ) {
		$api_request_url = GITHUB_API_ENDPOINT . $query;

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: token '. GITHUB_API_TOKEN,
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'  => $headers,
				'method'  => $method,
				'ignore_errors' => true,
			)
		);

		if( in_array( $method, array( 'POST', 'PUT' ) ) ) {
			$data = json_encode( $data );
			$options['http']['content'] = $data;
		}

		$context  = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		return json_decode( $result );
	}

	public function call_deploy_hq_api( $query, $method, $data ) {
		$api_request_url = DEPLOY_HQ_API_ENDPOINT . $query;

		$data = json_encode( $data );

		$headers = array(
			'Accept: application/json',
			'Content-type: application/json',
			'Authorization: Basic '. base64_encode( DEPLOY_HQ_USERNAME . ':' . DEPLOY_HQ_API_KEY ),
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'  => $headers,
				'method'  => $method,
				'content' => $data,
				'timeout' => 60,
				'ignore_errors' => true,
			)
		);

		$context  = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		return json_decode( $result );
	}


	public function log_to_slack( $message ) {
		if( ! defined( 'SLACK_WEBHOOK_URL' ) || empty( SLACK_WEBHOOK_URL ) ) {
			echo "Note: log_to_slack() won't work while SLACK_WEBHOOK_URL is undefined in config.json." . PHP_EOL;
		}

		$message = json_encode( array( 'message' => $message ) );

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'  => $headers,
				'method'  => 'POST',
				'content' => $message,
			),
		);

		$context = stream_context_create( $options );
		$result  = @file_get_contents( SLACK_WEBHOOK_URL, false, $context );

		return json_decode( $result );
	}

	public function call_wpcom_api( $query, $data, $method = 'GET' ) {
		$api_request_url = WPCOM_API_ENDPOINT . $query;

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer ' . WPCOM_API_ACCOUNT_TOKEN,
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'        => $headers,
				'ignore_errors' => true,
				'method'        => $method,
			)
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ) ) ) {
			$data = json_encode( $data );
			$options['http']['content'] = $data;
		}

		$context = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		return json_decode( $result );
	}

	public function call_front_api( $query, $data = array(), $method = 'GET' ) {
		$api_request_url = FRONT_API_ENDPOINT . $query;

		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer '. FRONT_API_TOKEN,
			'User-Agent: PHP',
		);

		$options = array(
			'http' => array(
				'header'        => $headers,
				'ignore_errors' => true,
			)
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ) ) ) {
			$data = json_encode( $data );
			$options['http']['content'] = $data;
			$options['http']['method']  = $method;
		}

		$context = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		return json_decode( $result );
	}

	public function call_generic_api( $api_request_url, $data, $method = 'GET', $bearer_token = null ) {
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'User-Agent: PHP',
		);

		if ( ! empty( $bearer_token ) ) {
			$headers[] = 'Authorization: Bearer ' . $bearer_token;
		}

		$options = array(
			'http' => array(
				'header'        => $headers,
				'ignore_errors' => true,
			),
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ) ) ) {
			$data = json_encode( $data );
			$options['http']['content'] = $data;
			$options['http']['method']  = $method;
		}

		$context = stream_context_create( $options );
		$result = @file_get_contents( $api_request_url, false, $context );

		return json_decode( $result );
	}

	/**
	 * Retrieves the last stored Pressable access token. If token is expired, returns false
	 */
	private function get_local_pressable_access_token() {
		if ( ! file_exists( self::PRESABLE_TOKEN_FILE ) ) {
			// No local token stored
			return false;
		}

		$data = json_decode( file_get_contents( self::PRESABLE_TOKEN_FILE ) );

		if ( ! $data ) {
			return false;
		}

		if( intval( $data->pressable_token_timestamp ) < strtotime( self::PRESABLE_TOKEN_EXPIRE_AFTER ) ) {
			// Pressable token expired
			return false;
		}

		return $data->pressable_access_token;
	}

	/**
	 * Retrieves the last stored Pressable refresh token, 
	 * or PRESSABLE_API_REFRESH_TOKEN by default
	 */
	private function get_local_pressable_refresh_token() {
		if ( ! file_exists( self::PRESABLE_TOKEN_FILE ) ) {
			if ( defined( 'PRESSABLE_API_REFRESH_TOKEN' ) ) {
				echo "Using PRESSABLE_API_REFRESH_TOKEN from config.json file\n";
				return PRESSABLE_API_REFRESH_TOKEN;
			} else {
				// No local token stored in JSON nor config file
				echo "No PRESSABLE_API_REFRESH_TOKEN found. Please check your config.json file \n";
				return false;
			}
		}

		$data = json_decode( file_get_contents( self::PRESABLE_TOKEN_FILE ) );
		if ( ! $data ) {
			echo "Could not read pressable_token.json file";
			return false;
		}

		return $data->pressable_refresh_token;
	}

	/**
	 * Stores in local file the last generated pressable token
	 */
	private function set_local_pressable_tokens( $tokens ) {
		$data = array(
			'pressable_access_token'    => $tokens->access_token,
			'pressable_refresh_token'   => $tokens->refresh_token,
			'pressable_token_timestamp' => time(),
		);
		file_put_contents( self::PRESABLE_TOKEN_FILE, json_encode( $data ) );

		return true;
	}
}
