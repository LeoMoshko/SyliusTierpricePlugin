


# Sylius Tier Price Plugin

This plugin adds tier pricing to Sylius: one product variant can have different prices based on the ordered quantity.

> This is a fork of [`brille24/sylius-tierprice-plugin`](https://github.com/Brille24/SyliusTierpricePlugin),
> published as `leomoshko/sylius-tierprice-plugin`. The PHP namespace (`Brille24\SyliusTierPricePlugin\`)
> and the bundle class name are kept unchanged so it stays a drop-in replacement.

## Requirements

| Package | Version         |
|---------|-----------------|
| PHP     | ^8.2            |
| Sylius  | 2.0, 2.1, 2.2   |

## Installation

* Install the plugin via composer:
```bash
composer require leomoshko/sylius-tierprice-plugin
```

* Register the bundle in your `config/bundles.php`:
```php
return [
    // ...
    Brille24\SyliusTierPricePlugin\Brille24SyliusTierPricePlugin::class => ['all' => true],
];
```

* Import the plugin config in your `config/packages/brille24_sylius_tierprice_plugin.yaml`:
```yaml
imports:
    - { resource: '@Brille24SyliusTierPricePlugin/config/config.yaml' }
```

* For API functionality import the plugin routes in your `config/routes.yaml`:
```yaml
brille24_tierprice_bundle:
    resource: '@Brille24SyliusTierPricePlugin/config/routes.yml'
```

* Go into your `ProductVariant` class, implement the interface and add the trait plus one call in the constructor:
```php
use Brille24\SyliusTierPricePlugin\Entity\ProductVariantInterface as TierPriceableProductVariantInterface;
use Brille24\SyliusTierPricePlugin\Traits\TierPriceableTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;

class ProductVariant extends BaseProductVariant implements TierPriceableProductVariantInterface
{
    use TierPriceableTrait;

    public function __construct()
    {
        parent::__construct(); // Your constructor here

        $this->initTierPriceableTrait(); // "Constructor" of the trait
    }
}
```

* Finally update the database schema (generate and run a migration) and install the assets:
```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
bin/console assets:install
```

### Integration

* This plugin decorates the `sylius.calculator.product_variant_price` service. If you wish to change that, you could register a [compiler pass](https://symfony.com/doc/current/service_container/compiler_passes.html).
* This plugin decorates the `sylius.order_processing.order_prices_recalculator` service. If you wish to use your own order processor or change its priority, you could register a [compiler pass](https://symfony.com/doc/current/service_container/compiler_passes.html).
* The admin product-variant form and the shop product page are extended through [Sylius Twig Hooks](https://github.com/Sylius/TwigHooks) (`config/app/twig_hooks/`), not menu listeners.

## Usage

First set up a product with as many variants as you want. Then, for each variant, you can configure tier pricing per channel (and optionally per customer group). The table sorts itself automatically to give a clear overview of the configured tiers.

<img src="images/Backend.png" />

In the shop the customer sees a table next to the "add to cart" button showing the discount for the different tiers:

<img src="images/Front-End.png" />

### Creating data

You can create tier prices with fixtures:
```yaml
sylius_fixtures:
    suites:
        my_suite:
            fixtures:
                tier_prices:
                    options:
                        custom:
                            - product_variant: "20125148-54ca-3f05-875f-5524f95aa85b"
                              channel: US_WEB
                              quantity: 10
                              price: 5
```
The referenced product and product variant have to exist before the fixture runs.

## Upgrading from `brille24/sylius-tierprice-plugin`

Because the namespace is unchanged, switching is a `composer.json` change only:
```bash
composer remove brille24/sylius-tierprice-plugin
composer require leomoshko/sylius-tierprice-plugin
```
No code, config, template or translation-key changes are required.

## Support

Found a bug or need help wiring the plugin into your Sylius project? Please open an
issue on the [issue tracker](https://github.com/LeoMoshko/SyliusTierpricePlugin/issues).

For paid support, Sylius integration or custom development, get in touch through our
partner [santv.fr](https://santv.fr).

## Partners

This fork is maintained with the support of:

<a href="https://santv.fr" target="_blank"><img src="images/partners/santv.png" alt="santv.fr - la tech au service de la santé et du développement des enfants" width="320"></a>

**[santv.fr](https://santv.fr)** — la tech au service de la santé et du développement des enfants.

## License

Released under the [MIT License](LICENSE).
