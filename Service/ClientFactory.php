<?php
/**
 * ClusterifyAI ChatBot client factory service
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Service;

use Clusterify\ClusterifyClient;
use Clusterify\Support\RetryPolicy;
use ClusterifyAI\ChatBot\Model\Config;
use InvalidArgumentException;
use Magento\Store\Model\ScopeInterface;
use SensitiveParameter;

/**
 * Class ClientFactory
 *
 * Factory service for creating authenticated instances of the Clusterify PHP SDK client,
 * with full support for store-view and website scoped configurations and SSRF URL validation.
 */
class ClientFactory
{
    /**
     * @param Config $config Module configuration provider
     */
    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Validate API Base URL to prevent SSRF and credential exfiltration.
     *
     * @param string $url Target API base URL
     * @return string Sanitized base URL
     * @throws InvalidArgumentException
     */
    public function validateBaseUrl(string $url): string
    {
        if (preg_match('/[\r\n\0]/', $url) === 1) {
            throw new InvalidArgumentException(
                (string) __('Invalid API Base URL: URL must not contain control characters.')
            );
        }

        $trimmed = rtrim(trim($url), '/');
        if ($trimmed === '') {
            return Config::DEFAULT_API_BASE_URL;
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(
                (string) __('Invalid API Base URL: Must be a well-formed HTTP/HTTPS URL.')
            );
        }

        $parsed = parse_url($trimmed);
        if (!is_array($parsed)) {
            throw new InvalidArgumentException(
                (string) __('Invalid API Base URL: Unable to parse URL components.')
            );
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            throw new InvalidArgumentException(
                (string) __('Invalid API Base URL: Embedded credentials are not permitted in the URL.')
            );
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host'] ?? '');

        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new InvalidArgumentException(
                (string) __('Invalid API Base URL: Only HTTPS and HTTP schemes are supported.')
            );
        }

        $loopbackHosts = ['localhost', '127.0.0.1', '[::1]', '::1'];

        // Enforce HTTPS for remote hosts. HTTP is only permitted for local loopback development.
        if ($scheme === 'http' && !in_array($host, $loopbackHosts, true)) {
            throw new InvalidArgumentException(
                (string) __('Insecure connection rejected: HTTPS is required for external Clusterify API endpoints.')
            );
        }

        // Disallow link-local cloud metadata (169.254.x.x), IPv6 link-local, and private network IP ranges for external hosts
        if (!in_array($host, $loopbackHosts, true)) {
            $cleanHost = trim($host, '[]');
            $isIp = filter_var($cleanHost, FILTER_VALIDATE_IP) !== false;
            $ip = $isIp ? $cleanHost : gethostbyname($host);

            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    throw new InvalidArgumentException(
                        (string) __('API Base URL cannot point to internal or private IP addresses.')
                    );
                }
            }
        }

        return $trimmed;
    }

    /**
     * Create an authenticated ClusterifyClient instance.
     *
     * @param string|null     $publicKey  Optional Public Key override
     * @param string|null     $secretKey  Optional Secret Key override
     * @param string|null     $apiBaseUrl Optional Base URL override
     * @param int|string|null $scopeCode  Optional Store/Website code or ID
     * @param string          $scopeType  Scope type (stores, websites, default)
     * @return ClusterifyClient
     * @throws InvalidArgumentException When credentials or URL are invalid
     */
    public function create(
        ?string $publicKey = null,
        #[SensitiveParameter] ?string $secretKey = null,
        ?string $apiBaseUrl = null,
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): ClusterifyClient {
        $rawPubKey = $publicKey ?? $this->config->getPublicKey($scopeCode, $scopeType);
        $rawSecKey = $secretKey ?? $this->config->getSecretKey($scopeCode, $scopeType);

        if (preg_match('/[\r\n\0]/', $rawPubKey) === 1 || preg_match('/[\r\n\0]/', $rawSecKey) === 1) {
            throw new InvalidArgumentException(
                (string) __('Clusterify API credentials must not contain control characters.')
            );
        }

        $pubKey = trim($rawPubKey);
        $secKey = trim($rawSecKey);
        $baseUrl = trim($apiBaseUrl ?? $this->config->getApiBaseUrl($scopeCode, $scopeType));

        if ($pubKey === '') {
            throw new InvalidArgumentException(
                (string) __('Clusterify API Public Key is required.')
            );
        }

        if ($secKey === '') {
            throw new InvalidArgumentException(
                (string) __('Clusterify API Secret Key is required.')
            );
        }

        $validatedUrl = $this->validateBaseUrl($baseUrl);

        return ClusterifyClient::builder()
            ->withCredentials($pubKey, $secKey)
            ->withBaseUrl($validatedUrl)
            ->withTimeout(15000)
            ->withConnectTimeout(5000)
            ->withUserAgent('ClusterifyAI_ChatBot/' . Config::EXTENSION_VERSION . ' (Magento 2.4)')
            ->withRetryPolicy(new RetryPolicy(maxRetries: 2))
            ->build();
    }
}
