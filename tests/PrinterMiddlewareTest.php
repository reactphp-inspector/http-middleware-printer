<?php declare(strict_types=1);

namespace ReactInspector\Tests\Printer\Middleware;

use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use ReactInspector\Config;
use ReactInspector\Http\Middleware\Printer\PrinterMiddleware;
use ReactInspector\Metric;
use ReactInspector\MetricsStreamInterface;
use ReactInspector\Printer\Printer;
use RingCentral\Psr7\ServerRequest;
use Rx\Disposable\EmptyDisposable;
use Rx\DisposableInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

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

        $metrics = new class() implements MetricsStreamInterface {
            public function subscribe($onNextOrObserver = null, callable $onError = null, callable $onCompleted = null): DisposableInterface
            {
                $onNextOrObserver(new Metric(new Config('name', '', ''), [], []));

                return new EmptyDisposable();
            }
        };

        $printerMiddleware = new PrinterMiddleware($printer->reveal(), $metrics);
        /** @var ResponseInterface $response */
        $response = $this->await($printerMiddleware(new ServerRequest('GET', 'https://example.com/'), function (): void {
        }));

        self::assertSame($metricsString, (string)$response->getBody());
    }
}
