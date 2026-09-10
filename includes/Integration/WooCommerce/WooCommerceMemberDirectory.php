<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Customer\CustomerPhone;

final class WooCommerceMemberDirectory
{
    private const PLACEHOLDER_EMAIL_META = '_coffeepos_placeholder_email';

    public function list(int $page, string $search): array
    {
        $args = [
            'number' => 20,
            'paged' => max(1, $page),
            'count_total' => true,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'meta_key' => 'billing_phone',
            'meta_compare' => 'EXISTS',
        ];
        $normalizedPhone = CustomerPhone::normalize($search);
        if ($search !== '') {
            if ($normalizedPhone !== '' && preg_match('/\d/', $search)) {
                $args['meta_value'] = substr($normalizedPhone, -4);
                $args['meta_compare'] = 'LIKE';
            } else {
                $args['search'] = '*' . $search . '*';
                $args['search_columns'] = ['display_name', 'user_email'];
            }
        }
        $query = new \WP_User_Query($args);
        $items = [];
        foreach ((array) $query->get_results() as $user) {
            $member = $this->summary((int) $user->ID);
            if ($member !== null) { $items[] = $member; }
        }
        $total = (int) $query->get_total();
        return ['items' => $items, 'page' => max(1, $page), 'pages' => max(1, (int) ceil($total / 20)), 'total' => $total, 'search' => $search];
    }

    public function detail(int $customerId): ?array
    {
        $member = $this->summary($customerId);
        if ($member === null) { return null; }
        $orders = wc_get_orders(['customer_id' => $customerId, 'type' => 'shop_order', 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC']);
        $history = [];
        foreach ($orders as $order) {
            if ($order->get_created_via() !== 'coffeepos') { continue; }
            $date = $order->get_date_created();
            $history[] = [
                'id' => (int) $order->get_id(),
                'number' => (string) $order->get_order_number(),
                'date' => $date ? wp_date((string) get_option('date_format', 'Y-m-d') . ' ' . (string) get_option('time_format', 'H:i'), $date->getTimestamp()) : '',
                'status' => wc_get_order_status_name($order->get_status()),
                'total' => $this->plainPrice((float) $order->get_total(), (string) $order->get_currency()),
            ];
        }
        $member['orders'] = $history;
        return $member;
    }

    public function update(array $input): int
    {
        $id = absint($input['customer_id'] ?? 0);
        $customer = new \WC_Customer($id);
        if ($id <= 0 || $customer->get_id() !== $id) { throw new \RuntimeException(__('Member was not found.', 'coffeepos')); }
        $name = sanitize_text_field((string) ($input['member_name'] ?? ''));
        $phone = CustomerPhone::normalize((string) ($input['member_phone'] ?? ''));
        $email = sanitize_email((string) ($input['member_email'] ?? ''));
        if ($name === '' || $phone === '') { throw new \RuntimeException(__('Member name and phone are required.', 'coffeepos')); }
        if ($email !== '' && ! is_email($email)) { throw new \RuntimeException(__('Enter a valid member email.', 'coffeepos')); }
        $match = (new WooCommerceCustomerGateway())->findByPhone($phone);
        if ($match && (! empty($match['ambiguous']) || (int) ($match['id'] ?? 0) !== $id)) { throw new \RuntimeException(__('This phone number belongs to another member.', 'coffeepos')); }
        if ($email !== '' && email_exists($email) && (int) email_exists($email) !== $id) { throw new \RuntimeException(__('This email belongs to another account.', 'coffeepos')); }
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];
        $customer->set_first_name((string) ($parts[0] ?? $name));
        $customer->set_last_name((string) ($parts[1] ?? ''));
        $customer->set_display_name($name);
        $customer->set_billing_first_name((string) ($parts[0] ?? $name));
        $customer->set_billing_last_name((string) ($parts[1] ?? ''));
        $customer->set_billing_phone($phone);
        if ($email !== '') {
            $customer->set_email($email);
            $customer->set_billing_email($email);
            $customer->update_meta_data(self::PLACEHOLDER_EMAIL_META, 'no');
        }
        $newTier = sanitize_key((string) ($input['member_tier'] ?? ''));
        if ($newTier !== '' && ! in_array($newTier, array_column(WooCommerceMembershipProvider::tiers(), 'code'), true)) { throw new \RuntimeException(__('Invalid membership tiers.', 'coffeepos')); }
        $oldOverride = (array) $customer->get_meta(WooCommerceMembershipProvider::OVERRIDE_META, true);
        if ((string) ($oldOverride['code'] ?? '') !== $newTier) {
            $reason = sanitize_text_field((string) ($input['member_reason'] ?? ''));
            if (strlen($reason) < 3 || strlen($reason) > 500) { throw new \RuntimeException(__('Enter a reason for the tier change.', 'coffeepos')); }
            $customer->update_meta_data(WooCommerceMembershipProvider::OVERRIDE_META, ['code' => $newTier, 'reason' => $reason, 'actor' => get_current_user_id(), 'at' => gmdate('c')]);
        }
        $customer->save();
        return $id;
    }

    private function summary(int $id): ?array
    {
        $customer = (new WooCommerceCustomerGateway())->findById($id);
        if ($customer === null || CustomerPhone::normalize((string) ($customer['phone'] ?? '')) === '') { return null; }
        $provider = new WooCommerceMembershipProvider();
        $override = (array) (new \WC_Customer($id))->get_meta(WooCommerceMembershipProvider::OVERRIDE_META, true);
        return $customer + [
            'phone_masked' => CustomerPhone::mask((string) $customer['phone']),
            'membership' => $provider->membershipForCustomer($customer),
            'override_code' => (string) ($override['code'] ?? ''),
            'net_spend' => (new WooCommerceMoney())->formatMinor($provider->netSpend($id), get_woocommerce_currency()),
        ];
    }

    private function plainPrice(float $amount, string $currency): string
    {
        return trim(html_entity_decode(wp_strip_all_tags(wc_price($amount, ['currency' => $currency])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
