<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2019 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;
use Symfony\Component\HttpClient\Psr18Client;
use Webauthn\MetadataService\MetadataServiceFactory;

return function (ContainerConfigurator $container) {
    $container = $container->services()->defaults()
        ->private()
        ->autoconfigure()
        ->autowire();

    $container->set('webauthn.metadata_services.default_http_client')
        ->class(Psr18Client::class);

    $container->set(MetadataServiceFactory::class)
        ->public()
        ->args([
            ref('webauthn.metadata_services.http_client'),
            ref('webauthn.metadata_services.request_factory'),
        ]);
};
