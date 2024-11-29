<?php

namespace Saksono\Woojurnal;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

class MekariRequest {

    /**
	 * Mekari API url
	 */
    private const BASE_URL  = 'https://api.mekari.com';

    /**
	 * Mekari application Client ID from WordPress options
	 */
    private $clientId;

    /**
	 * Mekari application Client Secret from WordPress options
	 */
    private $clientSecret;

    /**
	 * Guzzle client
	 */
    private $client;

    public function __construct()
    {
        // Retrieve credentials from WordPress settings
		global $wpdb;
        $table_name = $wpdb->prefix . 'wcbc_setting';
        $options = get_option('wji_plugin_general_options');
        $this->clientId = $options['client_id'] ?? null;
        $this->clientSecret = $options['client_secret'] ?? null;

        // Initialize the Guzzle client
        $this->client = new Client(['base_uri' => self::BASE_URL]);
	}

    /**
     * Generate Authentication Headers
     *
     * @param string $method HTTP Method (GET, POST, etc.)
     * @param string $pathWithQueryParam API Path with query parameters
     * @return array Headers for the request
     */
    public function generateHeaders( string $method, string $pathWithQueryParam ): array
    {
        $datetime       = Carbon::now()->toRfc7231String();
        $request_line   = "{$method} {$pathWithQueryParam} HTTP/1.1";
        $payload        = implode("\n", ["date: {$datetime}", $request_line]);
        $digest         = hash_hmac('sha256', $payload, $this->clientSecret, true);
        $signature      = base64_encode($digest);
        
        return [
            'Content-Type'  => 'application/json',
            'Date'          => $datetime,
            'Authorization' => "hmac username=\"{$this->clientId}\", algorithm=\"hmac-sha256\", headers=\"date request-line\", signature=\"{$signature}\""
        ];
    }

    /**
     * Make API Request
     *
     * @param string $method HTTP Method (GET, POST, etc.)
     * @param string $path API Endpoint Path
     * @param string $queryParams Query parameters for the request
     * @param array $body Body payload for POST/PUT requests
     * @param array $extraHeaders Additional headers for the request
     * @return mixed Response body or error details
     */
    public function make( string $method, string $path, string $queryParams = '', array $body = [], array $extraHeaders = [] )
    {
        try {
            $headers = array_merge(
                $this->generateHeaders($method, $path . $queryParams),
                $extraHeaders
            );

            $response = $this->client->request($method, $path, [
                'headers' => $headers,
                'json'    => $body,
                'query'   => $queryParams
            ]);

            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents()
            ];
        } catch (ClientException $e) {
            return [
                'success' => false,
                'status_code' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'body' => $e->getMessage()
            ];
        }
    }

}