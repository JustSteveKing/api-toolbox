<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Testing\Parsers\LinkHeader;

it('parses the RFC 8288 section 3.5 examples', function (): void {
    expect(LinkHeader::parse(['<http://example.com/TheBook/chapter2>; rel="previous"; title="previous chapter"']))
        ->toBe([['uri' => 'http://example.com/TheBook/chapter2', 'params' => ['rel' => 'previous', 'title' => 'previous chapter']]]);

    expect(LinkHeader::parse(['</>; rel="http://example.net/foo"']))
        ->toBe([['uri' => '/', 'params' => ['rel' => 'http://example.net/foo']]]);

    expect(LinkHeader::parse(['</TheBook/chapter2>; rel="previous"; title*=UTF-8\'de\'letztes%20Kapitel, </TheBook/chapter4>; rel="next"; title*=UTF-8\'de\'n%c3%a4chstes%20Kapitel']))
        ->toHaveCount(2);
});

it('reads several links from one field and from several fields', function (): void {
    $links = LinkHeader::parse(['<https://a.example>; rel="next", <https://b.example>; rel=prev', '<https://c.example>; rel="first"']);

    expect(array_column($links, 'uri'))->toBe(['https://a.example', 'https://b.example', 'https://c.example'])
        ->and($links[1]['params']['rel'])->toBe('prev');
});

it('keeps commas and semicolons inside quoted strings, and unescapes quoted pairs', function (): void {
    $links = LinkHeader::parse(['<https://a.example>; rel="next"; title="one, two; \"three\""']);

    expect($links)->toHaveCount(1)
        ->and($links[0]['params']['title'])->toBe('one, two; "three"');
});

it('treats parameter names case-insensitively and keeps only the first rel', function (): void {
    $links = LinkHeader::parse(['<https://a.example>; REL="next"; rel="prev"']);

    expect($links[0]['params'])->toBe(['rel' => 'next']);
});

it('matches one relation type among several, case-insensitively (section 3.3)', function (): void {
    $links = LinkHeader::withRel(['<https://a.example>; rel="start Next"'], 'next');

    expect($links)->toHaveCount(1);
});

it('rejects malformed fields', function (string $field): void {
    LinkHeader::parse([$field]);
})->with([
    'no angle brackets' => ['https://a.example; rel="next"'],
    'unclosed bracket' => ['<https://a.example; rel="next"'],
    'space in the URI' => ['<https://a.example/a b>; rel="next"'],
    'missing comma' => ['<https://a.example>; rel="next" <https://b.example>'],
    'unterminated quote' => ['<https://a.example>; rel="next'],
])->throws(InvalidArgumentException::class);
