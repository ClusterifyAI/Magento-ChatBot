<?php
/**
 * ClusterifyAI ChatBot admin test connection AJAX action
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Controller\Adminhtml\System\Config;

use Clusterify\Exceptions\AuthenticationException;
use Clusterify\Exceptions\AuthorizationException;
use Clusterify\Exceptions\ClusterifyException;
use Clusterify\Exceptions\NetworkException;
use Clusterify\Exceptions\RateLimitExceededException;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use Exception;
use InvalidArgumentException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Class TestConnection
 *
 * Handles AJAX requests from System Configuration to test API connectivity using the ping endpoint,
 * resolving credentials based on the currently selected website or store scope with SSRF protection.
 */
class TestConnection extends Action implements HttpPostActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'ClusterifyAI_ChatBot::config';

    /**
     * Clusterify Dashboard API keys URL
     */
    public const DASHBOARD_API_KEY_URL = 'https://dashboard.clusterify.ai/api-key';

    /**
     * @param Context          $context           Backend action context
     * @param JsonFactory      $resultJsonFactory Factory for JSON results
     * @param ClientFactory    $clientFactory     Clusterify SDK client factory
     * @param Config           $config            Module configuration provider
     * @param LoggerInterface  $logger            System logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ClientFactory $clientFactory,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Execute test connection action.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        $store = (string) $this->getRequest()->getParam('store');
        $website = (string) $this->getRequest()->getParam('website');
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeCode = null;

        if ($store !== '') {
            $scopeType = ScopeInterface::SCOPE_STORE;
            $scopeCode = $store;
        } elseif ($website !== '') {
            $scopeType = ScopeInterface::SCOPE_WEBSITE;
            $scopeCode = $website;
        }

        $publicKey = trim((string) $this->getRequest()->getPost('public_key'));
        $secretKey = trim((string) $this->getRequest()->getPost('secret_key'));
        $apiBaseUrl = trim((string) $this->getRequest()->getPost('api_base_url'));

        // If secret key is masked (e.g. from saved password field '******') or empty, use saved secret for scope
        if ($secretKey === '' || preg_match('/^\*+$/', $secretKey)) {
            $secretKey = $this->config->getSecretKey($scopeCode, $scopeType);
        }

        // If public key is empty in POST, fall back to saved config for scope
        if ($publicKey === '') {
            $publicKey = $this->config->getPublicKey($scopeCode, $scopeType);
        }

        // Fallback for API base URL
        if ($apiBaseUrl === '') {
            $apiBaseUrl = $this->config->getApiBaseUrl($scopeCode, $scopeType);
        }

        if ($publicKey === '' || $secretKey === '') {
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Both API Public Key and API Secret Key are required to test connection. Please enter both keys.'
                ),
            ]);
        }

        try {
            // Validate URL to prevent SSRF before creating client
            $this->clientFactory->validateBaseUrl($apiBaseUrl);

            $client = $this->clientFactory->create(
                publicKey: $publicKey,
                secretKey: $secretKey,
                apiBaseUrl: $apiBaseUrl,
                scopeCode: $scopeCode,
                scopeType: $scopeType
            );

            $ping = $client->ping();

            if ($ping->success && $ping->authenticated) {
                return $result->setData([
                    'success' => true,
                    'message' => (string) __(
                        'Connection successful! Clusterify API is healthy and credentials are valid. (Status: %1, Timestamp: %2)',
                        $ping->status,
                        $ping->timestamp
                    ),
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Clusterify API reached, but authentication status is unverified. Status: %1',
                    $ping->status
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            return $result->setData([
                'success' => false,
                'message' => (string) __('Validation error: %1', $e->getMessage()),
            ]);
        } catch (AuthenticationException $e) {
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Authentication failed (401): Invalid API keys. Please verify your Public Key and Secret Key on the Clusterify.AI Dashboard.'
                ),
                'link_url' => self::DASHBOARD_API_KEY_URL,
                'link_text' => (string) __('Open Clusterify.AI API Keys Page'),
            ]);
        } catch (AuthorizationException $e) {
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Access forbidden (403): Your API Key is currently disabled. Please enable it on the Clusterify.AI Dashboard.'
                ),
                'link_url' => self::DASHBOARD_API_KEY_URL,
                'link_text' => (string) __('Open Clusterify.AI API Keys Page'),
            ]);
        } catch (RateLimitExceededException $e) {
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'API rate limit reached (429). Retry after %1 seconds.',
                    (int) ($e->getRetryAfter() ?? 60)
                ),
            ]);
        } catch (NetworkException $e) {
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Network error: Unable to connect to %1. Please verify server internet connectivity and URL. (%2)',
                    $apiBaseUrl,
                    $e->getMessage()
                ),
            ]);
        } catch (ClusterifyException $e) {
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Clusterify API Error [%1]: %2',
                    $e->getErrorCode(),
                    $e->getMessage()
                ),
            ]);
        } catch (Exception $e) {
            $this->logger->error(
                'Clusterify ChatBot Test Connection Error: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Connection test failed: %1',
                    $e->getMessage()
                ),
            ]);
        }
    }
}
