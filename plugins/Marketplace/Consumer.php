<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace;

use Piwik\Config;
use Piwik\Plugin;

/**
 * A consumer is a user having specified a license key in the Marketplace. This is typically a Piwik PRO client
 * but could be as well any client of any distributor.
 */
class Consumer
{
    /**
     * @var Api\Client
     */
    private $marketplaceClient;
    
    public function __construct(Api\Client $marketplaceClient)
    {
        $this->marketplaceClient = $marketplaceClient;
    }

    public function hasAccessToPaidPlugins()
    {
        $consumer = $this->getConsumer();

        return !empty($consumer['name']);
    }

    public function getDistributor()
    {
        $consumer = $this->getConsumer();

        if (!empty($consumer['distributor']['name'])) {
            return new Distributor($consumer['distributor']);
        }
    }

    public function getConsumer()
    {
        return $this->marketplaceClient->getConsumer();
    }

    /**
     * Gets a list of restricted github organizations. Returns false if all plugins are meant to be shown.
     * Returns a list of github organizations (lower case) if only plugins of specific github organizations are supposed
     * to be shown in the Marketplace UI.
     *
     * @return array|false
     */
    public function getWhitelistedGithubOrgs()
    {
        $whitelist = Config::getInstance()->Marketplace['whitelisted_github_orgs'];

        if ($whitelist == 'all') {
            return false;
        }

        if ((empty($whitelist) || $whitelist == '0') && $this->hasAccessToPaidPlugins()) {
            $githubOrgs = array('piwik');
            $distributor = $this->getDistributor();

            if (!empty($distributor)) {
                $githubOrgs[] = strtolower($distributor->getGithubOrg());
            }

            return $githubOrgs;
        }

        $githubOrgs = explode(',', $whitelist);
        foreach ($githubOrgs as $index => $githubOrg) {
            $githubOrgs[$index] = strtolower($githubOrg);
        }

        return $githubOrgs;
    }

}
