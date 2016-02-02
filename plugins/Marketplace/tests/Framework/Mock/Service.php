<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Framework\Mock;

class Service extends \Piwik\Plugins\Marketplace\Api\Service {

    public $action;
    public $params;
    public function fetch($action, $params)
    {
        $this->action = $action;
        $this->params = $params;

        return array();
    }
}
