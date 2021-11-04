<?php

declare(strict_types=1);

namespace ReactInspector\Tests\Http\Middleware\Printer;

use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use ReactInspector\Config;
use ReactInspector\Http\Middleware\Printer\PrinterMiddleware;
use ReactInspector\Measurements;
use ReactInspector\Metric;
use ReactInspector\MetricsStreamInterface;
use ReactInspector\Printer\Printer;
use ReactInspector\Tags;
use RingCentral\Psr7\ServerRequest;
use Rx\Disposable\EmptyDisposable;
use Rx\DisposableInterface;
use Rx\ObserverInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function assert;
use function is_callable;

/**
 * @internal
 */
final class PrinterMiddlewareTest extends AsyncTestCase
{
    public function testPrinting(): void
    {
        $metricsString = 'dasdsadsa';

        $printer = $this->prophesize(Printer::class);
        $printer->print(Argument::type(Metric::class))->shouldBeCalled()->willReturn($metricsString);

        $metrics = new class () implements MetricsStreamInterface {
            /**
             * @param callable|ObserverInterface|null $onNextOrObserver
             */
            // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
            public function subscribe($onNextOrObserver = null, ?callable $onError = null, ?callable $onCompleted = null): DisposableInterface
            {
                if (is_callable($onNextOrObserver)) {
                    $onNextOrObserver(Metric::create(new Config('name', '', ''), new Tags(), new Measurements()));
                    $onNextOrObserver(Metric::create(new Config('eman', '', ''), new Tags(), new Measurements()));
                }

                return new EmptyDisposable();
            }
        };

        $printerMiddleware = new PrinterMiddleware($printer->reveal(), $metrics);
        $response          = $this->await($printerMiddleware(new ServerRequest('GET', 'https://example.com/')));
        assert($response instanceof ResponseInterface);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($metricsString . $metricsString, (string) $response->getBody());
    }
}
