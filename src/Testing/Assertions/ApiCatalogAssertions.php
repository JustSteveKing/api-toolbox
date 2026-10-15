<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Assertions;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

final class ApiCatalogAssertions
{
    /**
     * The response is an API catalog (RFC 9727, section 4.2): application/linkset+json, a
     * Linkset document (RFC 9264, section 4.2) and, when given, one that lists the API at $api.
     *
     * @param  TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    public static function assertApiCatalog(TestResponse $response, ?string $api = null): void
    {
        $type = strtolower((string) $response->headers->get('Content-Type'));

        Assert::assertSame('application/linkset+json', trim(explode(';', $type)[0]), 'The catalog is application/linkset+json (RFC 9727, section 4.2).');

        $document = json_decode((string) $response->getContent(), true);

        Assert::assertIsArray($document, 'The catalog is a JSON object.');
        Assert::assertSame(['linkset'], array_keys($document), 'linkset is the sole member of a Linkset document (RFC 9264, section 4.2.1).');
        Assert::assertIsList($document['linkset'], 'linkset is an array of link context objects (RFC 9264, section 4.2.2).');

        $items = [];

        foreach ($document['linkset'] as $context) {
            Assert::assertIsArray($context, 'Each link context is an object (RFC 9264, section 4.2.2).');

            foreach ($context as $member => $targets) {
                if ($member === 'anchor') {
                    continue;
                }

                Assert::assertIsList($targets, "The {$member} targets are an array (RFC 9264, section 4.2.3).");

                foreach ($targets as $target) {
                    Assert::assertIsArray($target, 'Each link target is an object (RFC 9264, section 4.2.3).');
                    Assert::assertIsString($target['href'] ?? null, 'Each link target has an href (RFC 9264, section 4.2.3).');

                    if ($member === 'item') {
                        $items[] = $target['href'];
                    }
                }
            }
        }

        if ($api !== null) {
            Assert::assertContains($api, $items, "The catalog lists {$api} as an item.");
        }
    }
}
