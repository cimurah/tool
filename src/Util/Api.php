<?php

namespace App\Util;

/**
 * @author Thomas Pellissier Tanon
 * @copyright 2011 Thomas Pellissier Tanon
 * @license GPL-2.0-or-later
 */

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * a base class for communications with Wikisource
 */
class Api {
	private const USER_AGENT = 'Wikisource Export/0.1';
	private const CONNECT_TIMEOUT = 10; // in seconds
	private const REQUEST_TIMEOUT = 60; // in seconds

	/** @var string */
	private $lang = '';

	private $domainName = '';

	/**
	 * @var ClientInterface
	 */
	private $client;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param ClientInterface $client
	 */
	public function __construct( ClientInterface $client = null ) {
		if ( $client === null ) {
			$client = static::createClient( $this->logger );
		}
		$this->client = $client;
	}

	/**
	 * Set the Wikisource language code.
	 */
	public function setLang( string $lang ): void {
		$this->lang = $lang;
		if ( $this->lang == 'www' || $this->lang == '' ) {
			$this->domainName = 'wikisource.org';
			$this->lang = '';
		} elseif ( $this->lang == 'wl' || $this->lang == 'wikilivres' ) {
			$this->domainName = 'wikilivres.ca';
			$this->lang = '';
		} elseif ( preg_match( '/^([a-z_]{2,})-?wikibooks$/', $this->lang, $m ) ) {
			$this->domainName = $m[1] . '.wikibooks.org';
			$this->lang = $m[1];
		} else {
			$this->domainName = $this->lang . '.wikisource.org';
		}
	}

	/**
	 * @return string the domain name of the wiki being used
	 */
	public function getDomainName(): string {
		return $this->domainName;
	}

	public function getLang(): string {
		return $this->lang;
	}

	/**
	 * @return ClientInterface
	 */
	public function getClient() {
		return $this->client;
	}

	/**
	 * GET action
	 *
	 * @param string $url the target URL
	 * @param array $options
	 * @return PromiseInterface the body of the result
	 */
	public function getAsync( $url, array $options = [] ) {
		return $this->client->getAsync(
			$url,
			$options
		)->then(
			function ( ResponseInterface $response ) {
				if ( $response->getStatusCode() !== 200 ) {
					throw new HttpException( $response->getStatusCode() );
				}
				return $response->getBody()->getContents();
			}
		);
	}

	/**
	 * @param string $url
	 * @param array $options
	 * @return PromiseInterface
	 */
	public function createAsyncRequest( string $url, array $options = [] ): PromiseInterface {
		return $this->client->getAsync(
			$url,
			$options
		);
	}

	/**
	 * API query
	 *
	 * @deprecated Use Api::queryAsync
	 *
	 * @param array $params parameters sent to the api
	 * @return array result of the api query
	 * @throws HttpException
	 */
	public function query( $params ) {
		return $this->queryAsync( $params )->wait();
	}

	/**
	 * API query
	 *
	 * @param array $params an associative array for params send to the api
	 * @return PromiseInterface a Promise with the result array
	 * @throws HttpException
	 */
	public function queryAsync( $params ) {
		$params += [ 'action' => 'query', 'format' => 'json' ];

		return $this->getAsync(
			'https://' . $this->getDomainName() . '/w/api.php',
			[ 'query' => $params ]
		)->then(
			function ( $result ) {
				$json = json_decode( $result, true );
				if ( isset( $json ) ) {
					return $json;
				} else {
					throw new Exception( 'invalid JSON: "' . $result . '": ' . json_last_error_msg() );
				}
			}
		);
	}

	/**
	 * api query. Give all pages of response
	 * @param array $params an associative array for params send to the api
	 * @return array an array with whe result of the api query
	 * @throws HttpException
	 */
	public function completeQuery( $params ) {
		$data = [];
		$continue = true;
		do {
			$temp = $this->query( $params );
			$data = array_merge_recursive( $data, $temp );

			if ( array_key_exists( 'continue', $temp ) ) {
				foreach ( $temp['continue'] as $keys => $value ) {
					$params[$keys] = $value;
				}
			} else {
				$continue = false;
			}
		} while ( $continue );

		return $data;
	}

	/**
	 * @param string $title the title of the page
	 * @return PromiseInterface promise with the content of a page
	 */
	public function getPageAsync( $title ) {
		return $this->queryAsync( [
			'titles' => $title,
			'prop' => 'revisions',
			'rvprop' => 'content',
			'rvparse' => true
		] )->then( function ( array $result ) {
			return $this->parseGetPageResponse( $result );
		} );
	}

	private function parseGetPageResponse( $response ) {
		$pages = $response['query']['pages'] ?? [];
		foreach ( $pages as $page ) {
			$title = $page['title'];
			if ( isset( $page['revisions'] ) ) {
				foreach ( $page['revisions'] as $revision ) {
					return Util::getXhtmlFromContent( $this->getLang(), $revision['*'], $title );
				}
			}
		}
		if ( !isset( $title ) ) {
			throw new HttpException( 500, 'No page information found in response' );
		}
		throw new NotFoundHttpException( "Page revision not found for: $title" );
	}

	/**
	 * @param string $url the url
	 * @return string the file content
	 */
	public function get( $url ) {
		return $this->client->get( $url )->getBody()->getContents();
	}

	/**
	 * @param string $url
	 * @return string the url encoded like mediawiki does.
	 */
	public static function mediawikiUrlEncode( string $url ): string {
		$search = [ '%21', '%24', '%28', '%29', '%2A', '%2C', '%2D', '%2E', '%2F', '%3A', '%3B', '%40' ];
		$replace = [ '!', '$', '(', ')', '*', ',', '-', '.', '/', ':', ';', '@' ];

		return str_replace( $search, $replace, urlencode( str_replace( ' ', '_', $url ) ) );
	}

	/**
	 * @param LoggerInterface $logger
	 * @return ClientInterface
	 */
	private static function createClient( ?LoggerInterface $logger ): ClientInterface {
		$handler = HandlerStack::create();
		if ( $logger ) {
			$handler->push( LoggingMiddleware::forLogger( $logger ), 'logging' );
		}
		return new Client( [
			'defaults' => [
				'connect_timeout' => self::CONNECT_TIMEOUT,
				'headers' => [ 'User-Agent' => self::USER_AGENT ],
				'timeout' => self::REQUEST_TIMEOUT
			],
			'handler' => $handler
		] );
	}
}
