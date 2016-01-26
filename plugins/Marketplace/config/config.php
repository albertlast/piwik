<?php

use Interop\Container\ContainerInterface;
use Piwik\Plugins\Marketplace\Api\Service;
use Piwik\Plugins\Marketplace\LicenseKey;

return array(
    'MarketplaceEndpoint' => 'http://plugins.piwik.org',
    'Piwik\Plugins\Marketplace\Api\Service' => function (ContainerInterface $c) {
        /** @var \Piwik\Plugins\Marketplace\Api\Service $previous */

        $domain = $c->get('MarketplaceEndpoint');
        $updater = $c->get('Piwik\Plugins\CoreUpdater\Updater');

        if (0 && $updater->isUpdatingOverHttps()) {
            $domain = str_replace('http://', 'https://', $this->domain);
        }

        $service = new Service($domain);

        $key = new LicenseKey();
        $accessToken = $key->get();

        $service->authenticate($accessToken);

        return $service;
    }
);