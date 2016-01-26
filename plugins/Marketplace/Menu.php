<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace;

use Piwik\Db;
use Piwik\Menu\MenuAdmin;
use Piwik\Menu\MenuUser;
use Piwik\Piwik;

/**
 */
class Menu extends \Piwik\Plugin\Menu
{

    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess() && Marketplace::isMarketplaceEnabled()) {
            $menu->addManageItem('Marketplace_Marketplace',
                $this->urlForAction('overview', array('activated' => '', 'mode' => 'admin')),
                $order = 12);
        }
    }

    public function configureUserMenu(MenuUser $menu)
    {
        if ($this->isAllowedToSeeMarketPlace()) {
            $menu->addPlatformItem('Marketplace_Marketplace',
                                   $this->urlForAction('overview', array('activated' => '', 'mode' => 'user')),
                                   $order = 5);
        }
    }

    private function isAllowedToSeeMarketPlace()
    {
        $isAnonymous          = Piwik::isUserIsAnonymous();
        $isMarketplaceEnabled = Marketplace::isMarketplaceEnabled();

        return $isMarketplaceEnabled && !$isAnonymous;
    }

}
