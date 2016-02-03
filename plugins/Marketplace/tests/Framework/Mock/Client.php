<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Framework\Mock;

use Piwik\Cache\Backend\NullCache;
use Piwik\Cache\Lazy;

class Client {

    public static function build($service)
    {
        return new \Piwik\Plugins\Marketplace\Api\Client($service, new Lazy(new NullCache()));
    }
}
