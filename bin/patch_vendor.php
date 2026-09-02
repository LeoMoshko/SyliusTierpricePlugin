<?php

/*
 * Post-install/update vendor patches that have no upstream release yet.
 *
 * Each entry is idempotent and tolerant: a missing file or an already-patched
 * file is a no-op (other Sylius / dependency versions in the CI matrix may not
 * ship the code being patched), so this never fails `composer install`.
 */

$root = \dirname(__DIR__);

$patches = [
    /*
     * symfony/type-info 7.4.x cannot resolve the `@phpstan-template T of Loggable|object`
     * bound on Gedmo\Loggable\Entity\MappedSuperclass\AbstractLogEntry (parent of Sylius's
     * ChannelPricingLogEntry / AddressLogEntry API resources): it builds union(object, Loggable),
     * which UnionType rejects, and the exception escapes collectTemplates(), breaking API route
     * loading and `cache:warmup` on Sylius 2.2 + api-platform 4.3.
     *
     * collectTemplates() already swallows UnsupportedException and keeps the `mixed` default;
     * this makes it swallow InvalidArgumentException the same way. Drop once type-info accepts
     * (or collapses) such unions.
     */
    'symfony/type-info' => [
        'file' => 'vendor/symfony/type-info/TypeContext/TypeContextFactory.php',
        'from' => '} catch (UnsupportedException) {',
        'to' => '} catch (UnsupportedException|\Symfony\Component\TypeInfo\Exception\InvalidArgumentException) {',
    ],
];

foreach ($patches as $name => $patch) {
    $path = $root . DIRECTORY_SEPARATOR . $patch['file'];

    if (!is_file($path)) {
        echo "> patch_vendor: {$name} not installed, skipping\n";
        continue;
    }

    $contents = file_get_contents($path);

    if (str_contains($contents, $patch['to'])) {
        echo "> patch_vendor: {$name} already patched\n";
        continue;
    }

    if (!str_contains($contents, $patch['from'])) {
        echo "> patch_vendor: {$name} target code not found (version changed?), skipping\n";
        continue;
    }

    file_put_contents($path, str_replace($patch['from'], $patch['to'], $contents));
    echo "> patch_vendor: {$name} patched\n";
}
