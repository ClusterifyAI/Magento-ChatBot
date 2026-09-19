<?php
/**
 * ClusterifyAI ChatBot page visibility config backend model
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * Class PageVisibility
 *
 * Serializes and deserializes the dynamic page types visibility mapping to and from JSON.
 */
class PageVisibility extends Value
{
    /**
     * Encode array value to JSON before saving to core_config_data.
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            $this->setValue(json_encode($value));
        }

        return parent::beforeSave();
    }

    /**
     * Decode JSON value into an associative array after loading from database.
     *
     * @return $this
     */
    public function afterLoad()
    {
        $value = $this->getValue();
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $this->setValue(is_array($decoded) ? $decoded : []);
        } elseif (!is_array($value)) {
            $this->setValue([]);
        }

        return parent::afterLoad();
    }
}
