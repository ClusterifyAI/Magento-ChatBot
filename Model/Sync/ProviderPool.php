<?php
/**
 * ClusterifyAI ChatBot sync data provider pool
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Sync;

use InvalidArgumentException;

/**
 * Class ProviderPool
 *
 * Registry pool for all registered entity data providers. Enables seamless extension
 * of new entity types or custom extractors via dependency injection.
 */
class ProviderPool
{
    /**
     * @var array<string, DataProviderInterface>
     */
    private array $providers = [];

    /**
     * @param array<string, DataProviderInterface> $providers Injected providers map
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $key => $provider) {
            if (!$provider instanceof DataProviderInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Provider "%s" must implement %s.',
                    $key,
                    DataProviderInterface::class
                ));
            }
            $this->providers[$provider->getEntityType()] = $provider;
        }
    }

    /**
     * Retrieve provider by entity type code.
     *
     * @param string $entityType
     * @return DataProviderInterface
     * @throws InvalidArgumentException
     */
    public function getProvider(string $entityType): DataProviderInterface
    {
        if (!isset($this->providers[$entityType])) {
            throw new InvalidArgumentException(sprintf(
                'No sync data provider registered for entity type "%s". Available types: %s',
                $entityType,
                implode(', ', array_keys($this->providers))
            ));
        }

        return $this->providers[$entityType];
    }

    /**
     * Check if a provider exists for the specified entity type.
     *
     * @param string $entityType
     * @return bool
     */
    public function hasProvider(string $entityType): bool
    {
        return isset($this->providers[$entityType]);
    }
}
