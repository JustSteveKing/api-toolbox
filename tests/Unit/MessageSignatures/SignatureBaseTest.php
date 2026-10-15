<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\MessageSignatures\Components;
use JustSteveKing\Toolbox\MessageSignatures\Exceptions\SignatureBaseException;
use JustSteveKing\Toolbox\MessageSignatures\Messages\IncomingRequest;
use JustSteveKing\Toolbox\MessageSignatures\Messages\OutgoingResponse;
use JustSteveKing\Toolbox\MessageSignatures\SignatureBase;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

function baseLine(Request $request, string $component): string
{
    $base = SignatureBase::create(new IncomingRequest($request), new InnerList([Components::item($component)]));

    return explode("\n", $base)[0];
}

function withHeaders(array $headers, string $uri = 'https://www.example.com/path?param=value', string $method = 'POST'): Request
{
    $request = Request::create($uri, $method);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

it('canonicalises fields as section 2.1 shows', function (string $component, array $headers, string $line): void {
    expect(baseLine(withHeaders($headers), $component))->toBe($line);
})->with([
    'trailing and leading whitespace' => ['x-ows-header', ['X-OWS-Header' => '   Leading and trailing whitespace.'], '"x-ows-header": Leading and trailing whitespace.'],
    'obsolete line folding' => ['x-obs-fold-header', ['X-Obs-Fold-Header' => "Obsolete\r\n    line folding."], '"x-obs-fold-header": Obsolete line folding.'],
    'several lines combined' => ['cache-control', ['Cache-Control' => ['max-age=60', '   must-revalidate']], '"cache-control": max-age=60, must-revalidate'],
    'dictionary as sent' => ['example-dict', ['Example-Dict' => ' a=1,    b=2;x=1;y=2,   c=(a   b   c)'], '"example-dict": a=1,    b=2;x=1;y=2,   c=(a   b   c)'],
    'empty field' => ['x-empty-header', ['X-Empty-Header' => ''], '"x-empty-header": '],
]);

it('serialises Structured Fields strictly with sf and key (sections 2.1.1 and 2.1.2)', function (string $component, string $line): void {
    $request = withHeaders([
        'Content-Digest' => 'sha-256=:d435Qo+nKZ+gLcUHn7GQtQ72hiBVAgqoLsZnZPiTGPk=:,    sha-512=:YQ==:',
        'Signature-Input' => 'a=1, b=2;x=1;y=2, c=(a   b    c), d',
    ]);

    expect(baseLine($request, $component))->toBe($line);
})->with([
    'sf' => ['"content-digest";sf', '"content-digest";sf: sha-256=:d435Qo+nKZ+gLcUHn7GQtQ72hiBVAgqoLsZnZPiTGPk=:, sha-512=:YQ==:'],
    'key, an Item' => ['"signature-input";key="a"', '"signature-input";key="a": 1'],
    'key, a Boolean' => ['"signature-input";key="d"', '"signature-input";key="d": ?1'],
    'key, with parameters' => ['"signature-input";key="b"', '"signature-input";key="b": 2;x=1;y=2'],
    'key, an Inner List' => ['"signature-input";key="c"', '"signature-input";key="c": (a b c)'],
]);

it('wraps each field line as a Byte Sequence with bs (section 2.1.3)', function (): void {
    expect(baseLine(withHeaders(['Example-Header' => ['value, with, lots', 'of, commas']]), '"example-header";bs'))
        ->toBe('"example-header";bs: :dmFsdWUsIHdpdGgsIGxvdHM=:, :b2YsIGNvbW1hcw==:')
        ->and(baseLine(withHeaders(['Example-Header' => 'value, with, lots, of, commas']), '"example-header";bs'))
        ->toBe('"example-header";bs: :dmFsdWUsIHdpdGgsIGxvdHMsIG9mLCBjb21tYXM=:');
});

it('derives components as section 2.2 shows', function (string $component, string $value): void {
    expect(baseLine(withHeaders([]), $component))->toBe(Components::key($component).': '.$value);
})->with([
    ['@method', 'POST'],
    ['@target-uri', 'https://www.example.com/path?param=value'],
    ['@authority', 'www.example.com'],
    ['@scheme', 'https'],
    ['@request-target', '/path?param=value'],
    ['@path', '/path'],
    ['@query', '?param=value'],
]);

it('lowercases the authority and leaves out a default port', function (): void {
    expect(baseLine(withHeaders([], 'https://WWW.Example.com:443/x'), '@authority'))->toBe('"@authority": www.example.com')
        ->and(baseLine(withHeaders([], 'http://example.com:8080/x'), '@authority'))->toBe('"@authority": example.com:8080');
});

it('gives a missing query as "?" alone (section 2.2.7)', function (): void {
    expect(baseLine(withHeaders([], 'https://example.com/path'), '@query'))->toBe('"@query": ?');
});

it('encodes query parameters as section 2.2.8 shows, spaces as %20', function (string $name, string $line): void {
    $request = withHeaders([], 'https://www.example.com/parameters?var=this%20is%20a%20big%0Amultiline%20value&bar=with+plus+whitespace&fa%C3%A7ade%22%3A%20=something&qux=');

    expect(baseLine($request, '"@query-param";name="'.$name.'"'))->toBe($line);
})->with([
    ['var', '"@query-param";name="var": this%20is%20a%20big%0Amultiline%20value'],
    ['bar', '"@query-param";name="bar": with%20plus%20whitespace'],
    ['fa%C3%A7ade%22%3A%20', '"@query-param";name="fa%C3%A7ade%22%3A%20": something'],
    ['qux', '"@query-param";name="qux": '],
]);

it('takes request components into a response signature with req (section 2.4)', function (): void {
    $request = withHeaders(['Content-Type' => 'application/json'], 'https://example.com/foo?param=Value&Pet=dog');
    $response = new OutgoingResponse(new Response('', 503, ['Content-Type' => 'application/json']), $request);

    $base = SignatureBase::create($response, new InnerList(array_map(Components::item(...), ['@status', '"@authority";req', '"@method";req', '"@path";req', '"content-type";req'])));

    expect($base)->toBe(implode("\n", [
        '"@status": 503',
        '"@authority";req: example.com',
        '"@method";req: POST',
        '"@path";req: /foo',
        '"content-type";req: application/json',
        '"@signature-params": ("@status" "@authority";req "@method";req "@path";req "content-type";req)',
    ]));
});

it('refuses what section 2.5 says must produce an error', function (string $component, ?Closure $message = null): void {
    $message = $message ? $message() : new IncomingRequest(withHeaders(['Example-Dict' => 'a=1, b=2', 'Cache-Control' => 'max-age=60'], 'https://example.com/?a=1&a=2&b=3'));

    SignatureBase::create($message, new InnerList([Components::item($component)]));
})->with([
    'a field that is not there' => ['x-missing'],
    'an unknown derived component' => ['@nonsense'],
    'an unknown parameter' => ['"cache-control";wat'],
    'bs with sf' => ['"cache-control";bs;sf'],
    'sf on a field of unknown type' => ['"cache-control";sf'],
    'a missing dictionary member' => ['"example-dict";key="c"'],
    'a repeated query parameter' => ['"@query-param";name="a"'],
    'a missing query parameter' => ['"@query-param";name="z"'],
    '@query-param without a name' => ['@query-param'],
    '@status on a request' => ['@status'],
    'req on a request' => ['"@method";req'],
    'a trailer' => ['"cache-control";tr'],
    'an uppercase field name' => ['Cache-Control'],
    '@signature-params itself' => ['@signature-params'],
    'a request component on a response without req' => ['@method', fn () => new OutgoingResponse(new Response, Request::create('/'))],
])->throws(SignatureBaseException::class);

it('refuses a component covered twice, whatever the parameter order', function (): void {
    SignatureBase::create(
        new IncomingRequest(withHeaders(['Example-Dict' => 'a=1'])),
        new InnerList([Components::item('"example-dict";key="a";sf'), Components::item('"example-dict";sf;key="a"')]),
    );
})->throws(SignatureBaseException::class, 'covered more than once');

it('refuses a signature base that is not ASCII', function (): void {
    SignatureBase::create(new IncomingRequest(withHeaders(['X-Name' => 'café'])), new InnerList([Components::item('x-name')]));
})->throws(SignatureBaseException::class, 'ASCII');
