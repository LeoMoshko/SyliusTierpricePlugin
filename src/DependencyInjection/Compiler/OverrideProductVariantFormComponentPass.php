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

namespace Brille24\SyliusTierPricePlugin\DependencyInjection\Compiler;

use Brille24\SyliusTierPricePlugin\Form\Components\ProductVariantFormComponent;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Swaps the class of Sylius' admin product-variant form live component for our own,
 * which mixes in the LiveCollectionTrait so the tier price collection can be edited live.
 *
 * Only the class is replaced - arguments, tags and the LiveComponent configuration provided
 * by Sylius are kept untouched, so this keeps working across Sylius 2.x minor versions even
 * when the component's constructor signature changes.
 */
final class OverrideProductVariantFormComponentPass implements CompilerPassInterface
{
    private const SERVICE_ID = 'sylius_admin.twig.component.product_variant.form';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::SERVICE_ID)) {
            return;
        }

        $container->getDefinition(self::SERVICE_ID)->setClass(ProductVariantFormComponent::class);
    }
}
