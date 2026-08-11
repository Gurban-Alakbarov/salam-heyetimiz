<?php

namespace App\Http\Admin\V1\Controllers\Payments;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Payments\Models\PaymentLog;
use App\Domain\Payments\Queries\PaymentStatsQuery;
use App\Http\Concerns\AuthorizesAdmin;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /admin/v1/payment-logs — adminListPaymentLogs (orders.view). Outbound BirPay calls + inbound webhooks,
 * with the (already allowlisted) request/response decrypted server-side. Filter by order / direction /
 * signature validity; cursor-paginated. Powers the Payment Logs + Webhook Logs admin views.
 */
class AdminPaymentLogController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/payments/stats — adminPaymentStats (orders.view). Dashboard back-office overview. */
    public function stats(Request $request, PaymentStatsQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::ORDERS_VIEW);

        return response()->json($query->stats());
    }

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::ORDERS_VIEW);

        $limit = max(1, min(100, (int) $request->query('limit', 30)));
        $cursor = Cursor::decode($request->query('cursor'));

        $query = PaymentLog::query()->orderByDesc('id');

        if (($orderId = $request->query('order_id')) !== null) {
            $query->where('order_id', (int) $orderId);
        }
        if (($direction = $request->query('direction')) !== null) {
            $query->where('direction', $direction); // outbound | inbound
        }
        if (($sig = $request->query('signature')) !== null) {
            $query->where('signature_valid', $sig === 'invalid' ? 0 : 1);
        }
        if (($endpoint = $request->query('endpoint')) !== null) {
            $query->where('endpoint', 'like', '%'.$endpoint.'%');
        }
        if ($request->query('errors') === '1') {
            $query->where('http_status', '>=', 400);
        }
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return response()->json([
            'data' => $data->map(fn (PaymentLog $log): array => [
                'id' => (int) $log->id,
                'order_id' => $log->order_id !== null ? (int) $log->order_id : null,
                'direction' => $log->direction->value,
                'endpoint' => $log->endpoint,
                'http_method' => $log->http_method,
                'http_status' => $log->http_status !== null ? (int) $log->http_status : null,
                'signature_provided' => $log->signature_provided,
                'signature_valid' => $log->signature_valid,
                'ip' => $log->ip,
                'latency_ms' => $log->latency_ms !== null ? (int) $log->latency_ms : null,
                'request' => $this->decode($log->request_redacted_encrypted),
                'response' => $this->decode($log->response_redacted_encrypted),
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])->all(),
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->id) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ]);
    }

    private function decode(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);

        return $decoded ?? $json;
    }
}
