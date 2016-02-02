<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace\Plugins;

use Piwik\Cache;
use Piwik\Plugin;
use Piwik\Plugins\Marketplace\Consumer;
use Piwik\Plugins\Marketplace\Plugins;
use Exception;

/**
 *
 */
class Expired
{
    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @var Plugins
     */
    private $plugins;

    /**
     * @var Plugin\Manager
     */
    private $pluginManager;

    private $cacheKey = 'Marketplace_ExpiredPlugins';

    public function __construct(Consumer $consumer, Plugins $plugins)
    {
        $this->consumer = $consumer;
        $this->plugins = $plugins;
        $this->pluginManager = Plugin\Manager::getInstance();
    }

    private function getCache()
    {
        return Cache::getEagerCache();
    }

    public function getNamesOfExpiredPaidPlugins()
    {
        $cache = $this->getCache();

        if ($cache->contains($this->cacheKey)) {
            $expiredPlugins = $cache->fetch($this->cacheKey);
        } else {
            $expiredPlugins = $this->getPluginNamesToExpireInCaseLicenseKeyExpired();
            $cache->save($this->cacheKey, $expiredPlugins);
        }

        return $expiredPlugins;
    }

    public function clearCache()
    {
        $this->getCache()->delete($this->cacheKey);
    }

    private function getPluginNamesToExpireInCaseLicenseKeyExpired()
    {
        try {
            if ($this->consumer->hasAccessToPaidPlugins()) {
                // user still has access to paid plugins, no need to do anything
                return array();
            }
        } catch (Exception $e) {
            // in case of any problems, especially with internet connection or marketplace, we should not disable
            // any problems as it might be a false alarm.

            return array();
        }

        $paidPlugins = $this->plugins->searchPlugins($query = '', 'popular', $themes = false, $type = 'paid');

        $pluginNames = array();
        foreach ($paidPlugins as $paidPlugin) {
            if ($this->pluginManager->isPluginActivated($paidPlugin['name'])) {
                $pluginNames[] = $paidPlugin['name'];
            }
        }

        return $pluginNames;
    }
}
