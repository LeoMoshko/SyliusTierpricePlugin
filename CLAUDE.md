# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Sylius 2 plugin that adds quantity-based tier pricing to product variants. A variant can have many `TierPrice` rows, each scoped to a channel and (optionally) a customer group. When an order is priced, the unit price drops to the cheapest tier whose `qty` threshold is met.

This repo is a **fork of `brille24/sylius-tierprice-plugin`**, published as `leomoshko/sylius-tierprice-plugin`. The PHP namespace (`Brille24\SyliusTierPricePlugin\`), the bundle class name, the Sylius resource name (`brille24.tierprice`) and all translation keys are deliberately kept unchanged so it stays a drop-in replacement — only `composer.json` metadata and docs carry the new identity.

Requires PHP `^8.2` and `sylius/sylius >=2.0 <2.3` (Sylius 2.0, 2.1, 2.2). Note that Sylius 2.2 pulls Doctrine ORM 3 and Symfony 7.4. The Sylius 2 line was a rewrite — see `UPGRADE-2.md`: config/templates/translations moved out of `src/Resources/`, menu listeners were replaced by Twig hooks, and custom frontend JS was removed (the shop price no longer updates live).

## Commands

Everything runs against the test application in `tests/Application/` (a full Sylius install). `Makefile` targets:

| Task | Command |
| --- | --- |
| Full first-time setup | `make init` (`install` + `backend` + `frontend`) |
| Install PHP deps only | `make install` (`composer install --no-scripts`) |
| Set up test app DB + fixtures | `make backend` |
| Build test app assets | `make frontend` |
| PHPUnit | `make phpunit` / `vendor/bin/phpunit` |
| Single PHPUnit test | `vendor/bin/phpunit --filter testMethodName src/Tests/Services/TierPriceFinderTest.php` |
| PHPSpec | `make phpspec` |
| PHPStan (level max, `src/` + `tests/Behat`) | `make phpstan` |
| Behat (needs running webserver + headless Chrome) | `make behat` |
| Coding standard check | `composer analyse` (ECS + PHPStan) |
| Coding standard autofix | `composer fix` |
| Run one Behat feature | `APP_ENV=test vendor/bin/behat features/cart/order_price.feature` |

`make ci` = `init phpstan phpunit phpspec behat`. Behat needs a Symfony server on `127.0.0.1:8080` and Chrome on `9222` (see `.github/workflows/build.yml` for the exact invocation); it also needs `tests/Application` DB created and fixtures loaded.

PHPUnit tests live in `src/Tests/` (not `tests/`), configured via `phpunit.xml.dist` bootstrapping `tests/Application/config/bootstrap.php`.

## Architecture

### Pricing flow (the core of the plugin)

1. **`Services\ProductVariantPriceCalculator`** decorates Sylius's `sylius.calculator.product_variant_price`. Its `calculate()` falls back to the inner calculator when `quantity` is absent or the variant is not `TierPriceableInterface`; otherwise it delegates to the finder. Customer comes from `CustomerContextInterface` unless a `customer` key is passed in `$context`. Only `calculate()` is overridden — `calculateOriginal` / `calculateLowestPriceBeforeDiscount` pass straight through.
2. **`Services\OrderPricesRecalculator`** decorates `sylius.order_processing.order_prices_recalculator`. It runs the inner processor first, then re-sets each non-immutable order item's unit price via the calculator, passing `channel`, `quantity`, and `customer`. This is what makes the cart reflect tier prices.
3. **`Services\TierPriceFinder`** walks the repository's channel/group-sorted list (ascending `qty`) and returns the last tier whose `qty <= quantity` — i.e. the cheapest applicable tier.
4. **`Repository\TierPriceRepository::getSortedTierPrices()`** encodes the customer-group rule: if the customer's group has *any* tier price for this variant+channel, only that group's prices are used; otherwise only group-less prices (`customerGroup = null`) apply. `Traits\TierPriceableTrait::filterPricesWithCustomerGroup()` applies the same rule in-memory for the templates.

All prices are integers in the currency's minor unit (cents), matching Sylius.

### Entities & host integration

- **`Entity\TierPrice`** — mapped to table `brille24_tierprice`, unique on `(qty, channel_id, product_variant_id, customer_group_id)`. Registered as a Sylius resource `brille24.tierprice` in `config/config.yaml`.
- The host app's `ProductVariant` must implement `Entity\ProductVariantInterface` and use **`Traits\TierPriceableTrait`**, calling `initTierPriceableTrait()` from its constructor (see README). The trait declares the `OneToMany` `tierPrices` mapping (cascade all, orphan removal).
- `Entity\ProductVariant` (in `src/`) is the version used by the test app and PHPUnit tests.
- `config/services.php` (PHP DSL, autowire/autoconfigure on) wires all decorators. The admin form live component `sylius_admin.twig.component.product_variant.form` is re-classed to **`Form\Components\ProductVariantFormComponent`** (base component + `LiveCollectionTrait`, so the admin tier-price table can add/remove rows live) by **`DependencyInjection\Compiler\OverrideProductVariantFormComponentPass`** — registered in `build()` of the bundle class. Only the class is swapped; Sylius keeps ownership of the service's constructor args and tags, which is what makes this survive Sylius minor-version bumps (2.1/2.2 changed that constructor).

### Forms & admin UI

- **`Form\Extension\ProductVariantTypeExtension`** adds a `tierPrices` `LiveCollectionType` (entry = `Form\TierPriceType`) to Sylius's `ProductVariantType`.
- `Form\TierPriceType` requires a `currency` option; the `channel` field is rendered hidden and the actual per-channel grouping happens in the template.
- Admin UI is injected via **Twig hooks**, not menu listeners (`config/app/twig_hooks/admin.yaml`), targeting `sylius_admin.product_variant.update.*`. Templates in `templates/Admin/product_variant/form/...` iterate channels and render one table per channel, matching each row by `tierprice.channel.vars.value == channel.code`.
- Shop side: `config/app/twig_hooks/shop.yaml` injects `templates/Shop/Product/Show/_tier_price_promo.html.twig` into the product page price summary.

### Validation

`config/validation/validation.xml` (loaded via `config/config.yaml`): `qty` NotBlank/integer/Range(min 0), `price` >= 0, and a class-level **`Validator\TierPriceUniqueConstraint`** (`Validator\TierPriceUniqueValidator`, uses Doctrine directly via `ClassMetadata` — not the ORM-2-only `ClassMetadataInfo`) enforcing uniqueness on `qty/channel/productVariant/customerGroup` in the `sylius` validation group. `ProductVariant::tierPrices` carries a `Valid` cascade.

### Fixtures

`Fixtures\TierPriceFixture` (tagged `sylius_fixtures.fixture`, alias `tier_prices`) + `Factory\TierPriceExampleFactory` resolve `product_variant` and `channel` by code. `Factory\TierPriceFactory` decorates `brille24.factory.tierprice`; its `createAtProductVariant(ProductVariant, array $options)` signature is order-sensitive (see `UPGRADE-1.3.md`).

## Notes

- `config/` and `templates/` at the repo root are the plugin's own resources; `tests/Application/config/` is the test Sylius app.
- Every PHP file carries the Brille24 license header — `composer fix` (ECS `HeaderCommentFixer`) enforces it. New files must include it.
- Minimum runtime is **PHP 8.2** (plugin + Sylius 2.0–2.2 all allow `^8.2`). `config.platform.php` is pinned to `8.2.99` and `friends-of-behat/symfony-extension` is capped at `<2.7` because 2.7.0 raised its own requirement to PHP `^8.3` — keep dev deps 8.2-installable.
- Docker: `docker-compose.yml` (the plugin dev container, MySQL) is separate from `compose.override.yaml` (Symfony Flex recipe additions).
- Known upstream issue (patched): on Sylius 2.2.8 + Symfony 7.4, api-platform 4.3.17's `symfony/type-info` usage throws `Cannot create union with both "object" and class type` while loading API routes / warming the cache. Root cause: `symfony/type-info` 7.4.x fails to resolve the `@phpstan-template T of Loggable|object` bound on `Gedmo\Loggable\Entity\MappedSuperclass\AbstractLogEntry` (parent of Sylius's `ChannelPricingLogEntry` / `AddressLogEntry` API resources). Not a plugin bug — it reproduces with the plugin removed. Worked around by `bin/patch_vendor.php` (run from `post-install-cmd` / `post-update-cmd` and from the `install` Makefile target, since that one uses `--no-scripts`): it makes `TypeContextFactory::collectTemplates()` swallow the `InvalidArgumentException` (as it already does for `UnsupportedException`) and keep the `mixed` default. The script is idempotent and a no-op when the target file or line is absent (older Sylius / api-platform matrix jobs). Drop it once `symfony/type-info` accepts or collapses such unions.
