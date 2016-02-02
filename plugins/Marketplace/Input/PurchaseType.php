<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\Marketplace\Input;
use Piwik\Common;
use Piwik\Plugins\Marketplace\Consumer;
use Piwik\Plugins\Marketplace\Plugins;

/**
 */
class PurchaseType
{
    const TYPE_FREE = 'free';
    const TYPE_PAID = 'paid';
    const TYPE_ALL  = '';

    /**
     * @var Consumer
     */
    private $consumer;

    public function __construct(Consumer $consumer)
    {
        $this->consumer = $consumer;
    }

    public function getPurchaseType()
    {
        $defaultType = static::TYPE_FREE;
        if ($this->consumer->hasAccessToPaidPlugins()) {
            $defaultType = static::TYPE_PAID;
        }

        $type = Common::getRequestVar('type', $defaultType, 'string');

        return $type;
    }

}
