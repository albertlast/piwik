<?php

use Interop\Container\ContainerInterface;
use Piwik\Plugins\Marketplace\Api\Service;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Consumer;

return array(
    'MarketplaceEndpoint' => function (ContainerInterface $c) {
        $domain = 'http://plugins.piwik.org';
        $updater = $c->get('Piwik\Plugins\CoreUpdater\Updater');

        if ($updater->isUpdatingOverHttps()) {
            $domain = str_replace('http://', 'https://', $this->domain);
        }

        return $domain;
    },
    'Piwik\Plugins\Marketplace\Consumer' => function (ContainerInterface $c) {
        $consumerTest = $c->get('test.vars.consumer');

        if ($consumerTest == 'validLicense') {
            $consumer = Consumer::buildValidLicense();
        } elseif ($consumerTest == 'expiredLicense') {
            $consumer = Consumer::buildExpiredLicense();
        } else {
            $consumer = Consumer::buildInvalidLicense();
        }

        return $consumer;
    }
);