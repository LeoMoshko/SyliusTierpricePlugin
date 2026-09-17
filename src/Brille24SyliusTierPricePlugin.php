<?php

/**
 * This file is part of the Brille24 tierprice plugin.
 *
 * (c) Mamazu
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Brille24\SyliusTierPricePlugin;

use Brille24\SyliusTierPricePlugin\DependencyInjection\Compiler\OverrideProductVariantFormComponentPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class Brille24SyliusTierPricePlugin extends Bundle
{
    use SyliusPluginTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Must run before Symfony's RegisterControllerArgumentLocatorsPass (core FrameworkBundle
        // pass, default priority 0), otherwise that pass reflects the pre-swap class - which lacks
        // LiveCollectionTrait's addCollectionItem()/removeCollectionItem() - and the live component
        // controller can no longer resolve those actions' service arguments (e.g. $propertyAccessor).
        $container->addCompilerPass(new OverrideProductVariantFormComponentPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 200000);
    }
}
