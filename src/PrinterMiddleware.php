<?php declare(strict_types=1);

namespace ReactInspector\Http\Middleware\Printer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\ArrayCache;
use React\Cache\CacheInterface;
use React\Promise\PromiseInterface;
use ReactInspector\Metric;
use ReactInspector\MetricsStreamInterface;
use ReactInspector\Printer\Printer;
use RingCentral\Psr7\Response;

final class PrinterMiddleware
{
    private const TTL = 120;

    /** @var Printer */
    private $printer;

    /** @var CacheInterface */
    private $metrics;

    /** @var array[string] */
    private $metricsList = [];

    public function __construct(Printer $printer, MetricsStreamInterface $metricsStream)
    {
        $this->printer = $printer;
        $this->metrics = new ArrayCache();
        $metricsStream->subscribe(function (Metric $metric): void {
            $this->metrics->set($metric->config()->name(), $metric, self::TTL);
            $this->metricsList[$metric->config()->name()] = $metric->config()->name();
        });
    }

    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        return $this->metrics->getMultiple($this->metricsList)->then(function ($metrics): ResponseInterface {
            $body = '';

            /** @var Metric $metric */
            foreach ($metrics as $metric) {
                $body .= $this->printer->print($metric);
            }

            return new Response(200, [], $body);
        });
    }
}
