<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Reports\ReportExporter;
use CoffeePOS\Application\Reports\SalesReportService;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoneyFormatter;
use CoffeePOS\Integration\WooCommerce\WooCommerceReportOrderGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ReportController
{
    private SalesReportService $service;

    private ReportExporter $exporter;

    public function __construct(?SalesReportService $service = null, ?ReportExporter $exporter = null)
    {
        $this->service = $service ?? new SalesReportService(new WooCommerceReportOrderGateway(), new WooCommerceMoneyFormatter());
        $this->exporter = $exporter ?? new ReportExporter();
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/reports/sales', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'sales'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/reports/sales/export', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'export'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        add_filter('rest_pre_serve_request', [$this, 'serveBinary'], 10, 4);
    }

    public function permissionCheck()
    {
        return current_user_can(Capabilities::VIEW_REPORTS)
            ? true
            : ErrorFactory::forbidden('coffeepos_reports_forbidden', __('You are not allowed to view CoffeePOS reports.', 'coffeepos'));
    }

    public function sales(WP_REST_Request $request): WP_REST_Response
    {
        try {
            return RestResponder::success(['report' => $this->service->generate($request->get_params())]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function export(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $report = $this->service->generate($request->get_params());
            $export = $this->exporter->export($report, (string) $request->get_param('format'));
            $response = new WP_REST_Response((string) $export['content'], 200);
            $response->header('Content-Type', (string) $export['content_type']);
            $response->header('Content-Disposition', 'attachment; filename="' . (string) $export['filename'] . '"');
            $response->header('Content-Length', (string) strlen((string) $export['content']));
            $response->header('Cache-Control', 'private, no-store, max-age=0');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-CoffeePOS-Binary', '1');
            return $response;
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function serveBinary(bool $served, $result, WP_REST_Request $request, WP_REST_Server $server): bool
    {
        $headers = $result instanceof WP_REST_Response ? $result->get_headers() : [];
        if ($served || ! $result instanceof WP_REST_Response || (string) ($headers['X-CoffeePOS-Binary'] ?? '') !== '1') {
            return $served;
        }

        echo (string) $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated binary export bytes.
        return true;
    }
}
