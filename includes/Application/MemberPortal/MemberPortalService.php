<?php

declare(strict_types=1);

namespace CoffeePOS\Application\MemberPortal;

use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Integration\WooCommerce\WooCommerceCustomerGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceMembershipProvider;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoney;

final class MemberPortalService
{
    private WooCommerceCustomerGateway $customers;

    private WooCommerceMembershipProvider $membership;

    private WooCommerceMoney $money;

    public function __construct(
        ?WooCommerceCustomerGateway $customers = null,
        ?WooCommerceMembershipProvider $membership = null,
        ?WooCommerceMoney $money = null
    ) {
        $this->customers = $customers ?? new WooCommerceCustomerGateway();
        $this->membership = $membership ?? new WooCommerceMembershipProvider();
        $this->money = $money ?? new WooCommerceMoney();
    }

    public function account(int $customerId): array
    {
        $customer = $this->customers->findById($customerId);
        if ($customer === null) {
            throw new MemberAuthException('member_session_required', __('Your member session has expired. Please sign in again.', 'coffeepos'), 401);
        }
        if (! (bool) Settings::get(Settings::OPTION_MEMBERSHIP_ENABLED)) {
            throw new MemberAuthException('member_portal_unavailable', __('Member access is temporarily unavailable.', 'coffeepos'), 503);
        }

        $spend = $this->membership->netSpend($customerId);
        $membership = $this->membership->membershipForCustomer($customer);
        $tiers = WooCommerceMembershipProvider::tiers();
        usort($tiers, function (array $left, array $right): int {
            return $this->money->toMinor((string) ($left['minimum'] ?? '0')) <=> $this->money->toMinor((string) ($right['minimum'] ?? '0'));
        });
        $nextTier = null;
        $currentThreshold = 0;
        foreach ($tiers as $tier) {
            $threshold = max(0, $this->money->toMinor((string) ($tier['minimum'] ?? '0')));
            if ($threshold <= $spend) {
                $currentThreshold = max($currentThreshold, $threshold);
                continue;
            }
            $nextTier = $tier;
            break;
        }

        $nextThreshold = $nextTier === null ? $spend : max($spend, $this->money->toMinor((string) $nextTier['minimum']));
        $range = max(1, $nextThreshold - $currentThreshold);
        $progress = $nextTier === null ? 100 : (int) floor((max(0, $spend - $currentThreshold) / $range) * 100);
        $remaining = $nextTier === null ? 0 : max(0, $nextThreshold - $spend);
        $currency = $this->money->currentCurrency();

        return [
            'member' => [
                'display_name' => (string) $customer['name'],
                'phone_masked' => \CoffeePOS\Application\Customer\CustomerPhone::mask((string) $customer['phone']),
            ],
            'membership' => [
                'tier_code' => (string) ($membership['tier_code'] ?? ''),
                'tier_label' => (string) ($membership['tier_label'] ?? __('Member', 'coffeepos')),
                'lifetime_spend' => $this->minorString($spend),
                'lifetime_spend_display' => $this->money->formatMinor($spend, $currency),
                'next_tier_code' => (string) ($nextTier['code'] ?? ''),
                'next_tier_label' => (string) ($nextTier['label'] ?? ''),
                'remaining_spend' => $this->minorString($remaining),
                'remaining_spend_display' => $remaining > 0 ? $this->money->formatMinor($remaining, $currency) : '',
                'progress_percent' => max(0, min(100, $progress)),
            ],
        ];
    }

    public function orders(int $customerId, array $criteria): array
    {
        $page = absint($criteria['page'] ?? 1);
        $perPage = absint($criteria['per_page'] ?? 10);
        if ($page < 1 || $perPage < 1 || $perPage > 50) {
            throw new MemberAuthException('invalid_member_order_page', __('The order page is invalid.', 'coffeepos'), 400);
        }

        $all = [];
        $sourcePage = 1;
        do {
            $batch = wc_get_orders([
                'customer_id' => $customerId,
                'type' => 'shop_order',
                'return' => 'objects',
                'limit' => 100,
                'page' => $sourcePage++,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
            foreach ((array) $batch as $order) {
                if ($this->isOwnedPosOrder($order, $customerId)) {
                    $all[] = $this->orderSummary($order);
                }
            }
        } while (is_array($batch) && count($batch) === 100);

        $total = count($all);
        $pages = $total === 0 ? 0 : (int) ceil($total / $perPage);

        return [
            'items' => array_slice($all, ($page - 1) * $perPage, $perPage),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function order(int $customerId, int $orderId): array
    {
        $order = $orderId > 0 && function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        if (! $this->isOwnedPosOrder($order, $customerId)) {
            throw new MemberAuthException('member_order_not_found', __('The order was not found.', 'coffeepos'), 404);
        }

        $currency = (string) $order->get_currency();
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            $quickNotes = json_decode((string) $item->get_meta('_coffeepos_quick_notes', true), true);
            $labels = [];
            foreach ((array) $quickNotes as $note) {
                $label = is_array($note) ? (string) ($note['label'] ?? '') : '';
                if ($label !== '') {
                    $labels[] = wp_strip_all_tags($label);
                }
            }
            $customNote = trim(wp_strip_all_tags((string) $item->get_meta('_coffeepos_note', true)));
            if ($customNote !== '') {
                $labels[] = $customNote;
            }
            $items[] = [
                'name' => wp_strip_all_tags((string) $item->get_name()),
                'quantity' => max(0, (int) $item->get_quantity()),
                'quantity_display' => '×' . max(0, (int) $item->get_quantity()),
                'options' => implode(' · ', array_values(array_unique($labels))),
                'total' => $this->decimal((float) $item->get_total()),
                'total_display' => $this->price((float) $item->get_total(), $currency),
            ];
        }

        $created = $order->get_date_created();
        return [
            'id' => (int) $order->get_id(),
            'number' => sprintf(__('Order #%s', 'coffeepos'), (string) $order->get_order_number()),
            'created_at' => $created ? $created->date('c') : '',
            'created_at_display' => $created ? Settings::formatTimestamp($created->getTimestamp()) : '',
            'status' => (string) $order->get_status(),
            'status_label' => wc_get_order_status_name((string) $order->get_status()),
            'service' => [
                'order_type' => (string) $order->get_meta('_coffeepos_order_type', true),
                'table_label' => wp_strip_all_tags((string) $order->get_meta('_coffeepos_table_label', true)),
            ],
            'payment_method_label' => wp_strip_all_tags((string) $order->get_payment_method_title()),
            'items' => $items,
            'totals' => [
                'subtotal' => $this->moneyValue((float) $order->get_subtotal(), $currency),
                'discount' => $this->moneyValue((float) $order->get_discount_total(), $currency),
                'refunded' => $this->moneyValue((float) $order->get_total_refunded(), $currency),
                'total' => $this->moneyValue((float) $order->get_total(), $currency),
            ],
        ];
    }

    private function orderSummary($order): array
    {
        $currency = (string) $order->get_currency();
        $created = $order->get_date_created();
        $names = [];
        $count = 0;
        foreach ($order->get_items('line_item') as $item) {
            $names[] = wp_strip_all_tags((string) $item->get_name());
            $count += max(0, (int) $item->get_quantity());
        }
        $summary = implode(', ', array_slice($names, 0, 2));
        if (count($names) > 2) {
            $summary .= sprintf(__(' +%d more', 'coffeepos'), count($names) - 2);
        }

        return [
            'id' => (int) $order->get_id(),
            'number' => sprintf(__('Order #%s', 'coffeepos'), (string) $order->get_order_number()),
            'created_at' => $created ? $created->date('c') : '',
            'created_at_display' => $created ? Settings::formatTimestamp($created->getTimestamp()) : '',
            'status' => (string) $order->get_status(),
            'status_label' => wc_get_order_status_name((string) $order->get_status()),
            'item_count' => $count,
            'item_summary' => $summary,
            'total' => $this->decimal((float) $order->get_total()),
            'total_display' => $this->price((float) $order->get_total(), $currency),
            'refunded_display' => $this->price((float) $order->get_total_refunded(), $currency),
        ];
    }

    private function isOwnedPosOrder($order, int $customerId): bool
    {
        return is_object($order)
            && method_exists($order, 'get_customer_id')
            && (int) $order->get_customer_id() === $customerId
            && ((string) $order->get_created_via() === 'coffeepos'
                || (string) $order->get_meta('_coffeepos_pos_session_id', true) !== '');
    }

    private function moneyValue(float $amount, string $currency): array
    {
        return ['amount' => $this->decimal($amount), 'display' => $this->price($amount, $currency)];
    }

    private function price(float $amount, string $currency): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) wc_price($amount, ['currency' => $currency])), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, wc_get_price_decimals(), '.', '');
    }

    private function minorString(int $amount): string
    {
        $decimals = max(0, (int) wc_get_price_decimals());
        if ($decimals === 0) {
            return (string) $amount;
        }
        $digits = str_pad((string) max(0, $amount), $decimals + 1, '0', STR_PAD_LEFT);

        return substr($digits, 0, -$decimals) . '.' . substr($digits, -$decimals);
    }
}
