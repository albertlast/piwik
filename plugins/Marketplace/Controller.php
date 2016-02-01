<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Log;
use Piwik\Nonce;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\CorePluginsAdmin\Controller as PluginsController;
use Piwik\Plugins\CorePluginsAdmin\CorePluginsAdmin;
use Piwik\Plugins\CorePluginsAdmin\PluginInstaller;
use Piwik\ProxyHttp;
use Piwik\SettingsPiwik;
use Piwik\Url;
use Piwik\View;
use Exception;

/**
 * A controller let's you for example create a page that can be added to a menu. For more information read our guide
 * http://developer.piwik.org/guides/mvc-in-piwik or have a look at the our API references for controller and view:
 * http://developer.piwik.org/api-reference/Piwik/Plugin/Controller and
 * http://developer.piwik.org/api-reference/Piwik/View
 */
class Controller extends \Piwik\Plugin\ControllerAdmin
{
    private $validSortMethods = array('popular', 'newest', 'alpha');
    private $defaultSortMethod = 'popular';

    /**
     * @var Plugins
     */
    private $plugins;

    /**
     * @var Api\Client
     */
    private $marketplaceApi;

    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @var PluginInstaller
     */
    private $pluginInstaller;

    /**
     * Controller constructor.
     * @param Plugins $plugins
     */
    public function __construct(Plugins $plugins, Api\Client $marketplaceApi, Consumer $consumer, PluginInstaller $pluginInstaller)
    {
        $this->plugins = $plugins;
        $this->marketplaceApi = $marketplaceApi;
        $this->consumer = $consumer;
        $this->pluginInstaller = $pluginInstaller;

        parent::__construct();
    }

    public function pluginDetails()
    {
        $this->dieIfMarketplaceIsDisabled();

        $pluginName = Common::getRequestVar('pluginName', null, 'string');
        $this->dieIfPluginNameIsInvalid($pluginName);

        $activeTab  = Common::getRequestVar('activeTab', '', 'string');
        if ('changelog' !== $activeTab) {
            $activeTab = '';
        }

        $view = $this->configureView('@Marketplace/plugin-details');

        try {
            $view->plugin = $this->plugins->getPluginInfo($pluginName);
            $view->isSuperUser  = Piwik::hasUserSuperUserAccess();
            $view->installNonce = Nonce::getNonce(PluginsController::INSTALL_NONCE);
            $view->updateNonce  = Nonce::getNonce(PluginsController::UPDATE_NONCE);
            $view->activeTab    = $activeTab;
            $view->isMultiServerEnvironment = SettingsPiwik::isMultiServerEnvironment();
        } catch (\Exception $e) {
            $view->errorMessage = $e->getMessage();
        }

        return $view->render();
    }

    public function download()
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->dieIfMarketplaceIsDisabled();
        $this->dieIfPluginsAdminIsDisabled();

        $pluginName = Common::getRequestVar('pluginName');
        $this->dieIfPluginNameIsInvalid($pluginName);

        Nonce::checkNonce($pluginName);

        // we generate a random unique id as filename to prevent any user could possibly download zip directly by
        // opening $piwikDomain/tmp/latest/plugins/$pluginName.zip in the browser. Instead we make it harder here
        // and try to make sure to delete file in case of any error.
        $target = StaticContainer::get('path.tmp') . '/latest/plugins/' . Common::generateUniqId() . '.zip';
        $filename = $pluginName . '.zip';

        try {
            $this->marketplaceApi->download($pluginName, $target);
            ProxyHttp::serverStaticFile($target, 'application/zip', $expire = 0, $start = false, $end = false, $filename);
        } catch (Exception $e) {
            Common::sendResponseCode(500);
            Log::warning('Could not download file . ' . $e->getMessage());
        }

        Filesystem::deleteFileIfExists($target);
    }

    public function overview()
    {
        $this->dieIfMarketplaceIsDisabled();

        $show  = Common::getRequestVar('show', 'plugins', 'string');
        $query = Common::getRequestVar('query', '', 'string', $_POST);
        $sort  = Common::getRequestVar('sort', $this->defaultSortMethod, 'string');

        if (!in_array($sort, $this->validSortMethods)) {
            $sort = $this->defaultSortMethod;
        }

        $defaultType = 'free';
        if ($this->consumer->hasAccessToPaidPlugins()) {
            $defaultType = 'paid';
        }
        $type = Common::getRequestVar('type', $defaultType, 'string');

        $mode = Common::getRequestVar('mode', 'admin', 'string');
        if (!in_array($mode, array('user', 'admin'))) {
            $mode = 'admin';
        }

        // we're fetching all available plugins to decide which tabs need to be shown in the UI and to know the number
        // of total available plugins
        $freePlugins = $this->plugins->searchPlugins($noQuery = '', $this->defaultSortMethod, $themes = false, 'free');
        $paidPlugins = $this->plugins->searchPlugins($noQuery = '', $this->defaultSortMethod, $themes = false, 'paid');
        $allThemes   = $this->plugins->searchPlugins($noQuery = '', $this->defaultSortMethod, $themes = true);

        $view = $this->configureView('@Marketplace/overview');

        $showThemes  = ($show === 'themes');
        $showPlugins = !$showThemes;
        $showPaid    = ($type === 'paid');
        $showFree    = !$showPaid;

        if ($showPlugins && $showPaid) {
            $type = 'paid';
            $view->numAvailablePlugins = count($paidPlugins);
        } elseif ($showPlugins && $showFree) {
            $type = 'free';
            $view->numAvailablePlugins = count($freePlugins);
        } else {
            $type = ''; // show all themes
            $view->numAvailablePlugins = count($allThemes);
        }

        $pluginsToShow = $this->plugins->searchPlugins($query, $sort, $showThemes, $type);

        $consumer = $this->consumer->getConsumer();

        if (!empty($consumer['expireDate'])) {
            $expireDate = Date::factory($consumer['expireDate']);
            $consumer['expireDateLong'] = $expireDate->getLocalized(Date::DATE_FORMAT_LONG);
        }

        $view->isMultiServerEnvironment = SettingsPiwik::isMultiServerEnvironment();
        $view->distributor = $this->consumer->getDistributor();
        $view->whitelistedGithubOrgs = $this->consumer->getWhitelistedGithubOrgs();
        $view->hasAccessToPaidPlugins = $this->consumer->hasAccessToPaidPlugins();
        $view->pluginsToShow = $pluginsToShow;
        $view->consumer = $consumer;
        $view->paidPlugins = $paidPlugins;
        $view->freePlugins = $freePlugins;
        $view->themes = $allThemes;
        $view->showThemes = $showThemes;
        $view->showPlugins = $showPlugins;
        $view->showFree = $showFree;
        $view->showPaid = $showPaid;
        $view->mode = $mode;
        $view->query = $query;
        $view->sort = $sort;
        $view->installNonce = Nonce::getNonce(PluginsController::INSTALL_NONCE);
        $view->updateNonce = Nonce::getNonce(PluginsController::UPDATE_NONCE);
        $view->isSuperUser = Piwik::hasUserSuperUserAccess();
        $view->isPluginsAdminEnabled = CorePluginsAdmin::isPluginsAdminEnabled();

        return $view->render();
    }

    public function installAllPaidPlugins()
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->dieIfMarketplaceIsDisabled();
        $this->dieIfPluginsAdminIsDisabled();
        Plugin\ControllerAdmin::displayWarningIfConfigFileNotWritable();

        Nonce::checkNonce(PluginsController::INSTALL_NONCE);

        $pluginManager = Plugin\Manager::getInstance();

        $paidPlugins = $this->plugins->searchPlugins($query = '', $this->defaultSortMethod, $themes = false, 'paid');

        $hasErrors = false;
        foreach ($paidPlugins as $paidPlugin) {
            if (empty($paidPlugin['isDownloadable'])) {
                continue;
            }

            $pluginName = $paidPlugin['name'];

            if ($pluginManager->isPluginInstalled($pluginName)
                || $pluginManager->isPluginActivated($pluginName)) {
                continue;
            }

            try {
                $this->pluginInstaller->installOrUpdatePluginFromMarketplace($pluginName);

            } catch (\Exception $e) {

                $notification = new Notification($e->getMessage());
                $notification->context = Notification::CONTEXT_ERROR;
                Notification\Manager::notify('Marketplace_InstallPlugin', $notification);

                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            Url::redirectToReferrer();
            return;
        }

        $dependency = new Plugin\Dependency();

        for ($i = 0; $i <= 5; $i++) {
            foreach ($paidPlugins as $index => $paidPlugin) {
                $pluginName = $paidPlugin['name'];

                if ($pluginManager->isPluginActivated($pluginName)) {
                    unset($paidPlugins[$index]);
                    continue;
                }

                if (empty($paidPlugin['require'])
                    || !$dependency->hasDependencyToDisabledPlugin($paidPlugin['require'])) {

                    unset($paidPlugins[$index]);

                    try {
                        Plugin\Manager::getInstance()->activatePlugin($pluginName);
                    } catch (Exception $e) {

                        $hasErrors = true;
                        $notification = new Notification($e->getMessage());
                        $notification->context = Notification::CONTEXT_ERROR;
                        Notification\Manager::notify('Marketplace_InstallPlugin', $notification);
                    }
                }
            }
        }

        if ($hasErrors) {
            $notification = new Notification('Some paid plugins were not installed successfully');
            $notification->context = Notification::CONTEXT_INFO;
            Notification\Manager::notify('Marketplace_InstallPlugin', $notification);
        } else {
            $notification = new Notification('All paid plugins were successfully installed.');
            $notification->context = Notification::CONTEXT_SUCCESS;
            Notification\Manager::notify('Marketplace_InstallPlugin', $notification);
        }

        Url::redirectToReferrer();
    }

    private function dieIfMarketplaceIsDisabled()
    {
        if (!Marketplace::isMarketplaceEnabled()) {
            throw new \Exception('The Marketplace feature has been disabled.
            You may enable the Marketplace by changing the config entry "[Marketplace]enabled = 0" to 1.
            Please contact your Piwik admins with your request so they can assist.');
        }

        $this->dieIfPluginsAdminIsDisabled();
    }

    private function dieIfPluginsAdminIsDisabled()
    {
        if (!CorePluginsAdmin::isPluginsAdminEnabled()) {
            throw new \Exception('Enabling, disabling and uninstalling plugins has been disabled by Piwik admins.
            Please contact your Piwik admins with your request so they can assist you.');
        }
    }

    private function dieIfPluginNameIsInvalid($pluginName)
    {
        if (!Plugin\Manager::getInstance()->isValidPluginName($pluginName)){
            throw new Exception('Invalid plugin name given');
        }
    }

    protected function configureView($template)
    {
        Piwik::checkUserIsNotAnonymous();

        $view = new View($template);
        $this->setBasicVariablesView($view);
        $this->displayWarningIfConfigFileNotWritable();

        $view->errorMessage = '';

        return $view;
    }
}
