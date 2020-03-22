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
use function assert;

final class PrinterMiddleware
{
    private const TTL = 120;

    private Printer $printer;

    private CacheInterface $metrics;

    /** @var array<string, string> */
    private array $metricsList = [];

    public function __construct(Printer $printer, MetricsStreamInterface $metricsStream)
    {
        $this->printer = $printer;
        $this->metrics = new ArrayCache();
        $metricsStream->subscribe(function (Metric $metric): void {
            $this->metrics->set($metric->config()->name(), $metric, self::TTL);
            $this->metricsList[$metric->config()->name()] = $metric->config()->name();
        });
    }

    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        /** @psalm-suppress TooManyTemplateParams */
        return $this->metrics->getMultiple($this->metricsList)->then(function (array $metrics): ResponseInterface {
            $body = '';

            foreach ($metrics as $metric) {
                assert($metric instanceof Metric);
                $body .= $this->printer->print($metric);
            }

            return new Response(200, [], $body);
        });
    }
}
