<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace\Api;

use Piwik\Cache;
use Piwik\Http;

/**
 *
 */
class Service
{
    const CACHE_TIMEOUT_IN_SECONDS = 1200;
    const HTTP_REQUEST_TIMEOUT = 60;

    /**
     * @var string
     */
    private $domain;

    /**
     * @var null|string
     */
    private $accessToken;

    private $version = 2;

    public function __construct($domain)
    {
        $this->domain = $domain;
    }

    public function authenticate($accessToken)
    {
        if (empty($accessToken)) {
            $this->accessToken = null;
        } elseif (ctype_xdigit($accessToken)) {
            $this->accessToken = $accessToken;
        }
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function fetch($action, $params)
    {
        $query = http_build_query($params);

        $endpoint = sprintf('%s/api/%d.0/', $this->domain, $this->version);

        $url = sprintf('%s%s?%s', $endpoint, $action, $query);

        $post = null;
        if ($this->accessToken) {
            $post = array('access_token' => $this->accessToken);
        }

        $method = Http::getTransportMethod();
        $timeout = static::HTTP_REQUEST_TIMEOUT;

        $response = Http::sendHttpRequestBy($method,
                                            $url,
                                            $timeout,
                                            $userAgent = null,
                                            $destinationPath = null,
                                            $file = null,
                                            $followDepth = 0,
                                            $acceptLanguage = false,
                                            $acceptInvalidSslCertificate = false,
                                            $byteRange = false, $getExtendedInfo = false, $httpMethod = 'POST',
                                            $httpUsername = null, $httpPassword = null, $post);

        $result = json_decode($response, true);

        if (is_null($result)) {
            $message = sprintf('There was an error reading the response from the Marketplace: %s. Please try again later.',
                substr($response, 0, 50));
            throw new Service\Exception($message, Service\Exception::HTTP_ERROR);
        }

        if (!empty($result['error'])) {
            throw new Service\Exception($result['error'], Service\Exception::API_ERROR);
        }

        return $result;
    }

    public function getDomain()
    {
        return $this->domain;
    }

}
