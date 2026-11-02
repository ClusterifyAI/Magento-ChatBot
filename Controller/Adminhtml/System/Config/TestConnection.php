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
use Clusterify\Exceptions\ServerException;
use Clusterify\Http\RequestOptions;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use ClusterifyAI\ChatBot\Service\PlanService;
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
     * Clusterify Dashboard URLs
     */
    public const DASHBOARD_API_KEY_URL = 'https://dashboard.clusterify.ai/api-key';
    public const DASHBOARD_URL = 'https://dashboard.clusterify.ai';

    /**
     * @param Context          $context           Backend action context
     * @param JsonFactory      $resultJsonFactory Factory for JSON results
     * @param ClientFactory    $clientFactory     Clusterify SDK client factory
     * @param Config           $config            Module configuration provider
     * @param LoggerInterface  $logger            System logger
     * @param PlanService|null $planService       Optional plan cache manager
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ClientFactory $clientFactory,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly ?PlanService $planService = null
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

            $ping = $client->ping(RequestOptions::noCache());

            if ($ping->success && $ping->authenticated) {
                $this->planService?->clearPlanCache();

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
            $statusCode = $e->getStatusCode() ?? 401;
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Authentication failed (%1): Invalid API keys. Please verify your Public Key and Secret Key on the Clusterify.AI Dashboard.',
                    $statusCode
                ),
                'link_url' => self::DASHBOARD_API_KEY_URL,
                'link_text' => (string) __('Open Clusterify.AI API Keys Page'),
            ]);
        } catch (AuthorizationException $e) {
            $statusCode = $e->getStatusCode() ?? 403;
            $errorCode = $e->getErrorCode() ?? 'API_KEY_DISABLED';

            $message = match ($errorCode) {
                'USER_DISABLED' => (string) __(
                    'Access forbidden (%1): Your Clusterify.AI account is currently disabled or suspended.',
                    $statusCode
                ),
                'USER_NOT_VERIFIED' => (string) __(
                    'Access forbidden (%1): Your Clusterify.AI account email address has not been verified yet.',
                    $statusCode
                ),
                default => (string) __(
                    'Access forbidden (%1): Your API Key is currently disabled. Please enable it on the Clusterify.AI Dashboard.',
                    $statusCode
                ),
            };

            return $result->setData([
                'success' => false,
                'message' => $message,
                'link_url' => $errorCode === 'API_KEY_DISABLED' ? self::DASHBOARD_API_KEY_URL : self::DASHBOARD_URL,
                'link_text' => $errorCode === 'API_KEY_DISABLED'
                    ? (string) __('Open Clusterify.AI API Keys Page')
                    : (string) __('Open Clusterify.AI Dashboard'),
            ]);
        } catch (RateLimitExceededException $e) {
            $statusCode = $e->getStatusCode() ?? 429;
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'API rate limit reached (%1). Retry after %2 seconds.',
                    $statusCode,
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
        } catch (ServerException $e) {
            $statusCode = $e->getStatusCode() ?? 500;
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Clusterify upstream server error (%1): %2',
                    $statusCode,
                    $e->getMessage()
                ),
            ]);
        } catch (ClusterifyException $e) {
            $errorCode = $e->getErrorCode() ?? 'API_ERROR';
            return $result->setData([
                'success' => false,
                'message' => (string) __(
                    'Clusterify API Error [%1]: %2',
                    $errorCode,
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
