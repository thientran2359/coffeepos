<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$item = isset($item) && is_array($item) ? $item : [];
$itemId = (string) ($item['item_id'] ?? '');
$configuration = array_filter([
    (string) ($item['variation_label'] ?? ''),
    (string) ($item['modifier_summary'] ?? ''),
    (string) ($item['quick_note_summary'] ?? ''),
]);
?>
<article class="coffeepos-cart-item" data-component="cart-item" data-item-id="<?php echo esc_attr($itemId); ?>" data-state="normal">
    <div class="coffeepos-cart-item-main">
        <strong><?php echo esc_html((string) ($item['product_name'] ?? '')); ?></strong>
        <p><?php echo esc_html($configuration === [] ? __('Default configuration', 'coffeepos') : implode(' · ', $configuration)); ?></p>
        <p><?php echo esc_html(($item['custom_note'] ?? '') === '' ? __('Note: -', 'coffeepos') : __('Note: ', 'coffeepos') . (string) $item['custom_note']); ?></p>
    </div>
    <div class="coffeepos-cart-item-actions">
        <button type="button" class="coffeepos-btn-icon" data-action="decrease-quantity" data-item-id="<?php echo esc_attr($itemId); ?>" aria-label="<?php esc_attr_e('Decrease quantity', 'coffeepos'); ?>">−</button>
        <span data-component="cart-item-quantity"><?php echo esc_html((string) ($item['quantity'] ?? 0)); ?></span>
        <button type="button" class="coffeepos-btn-icon" data-action="increase-quantity" data-item-id="<?php echo esc_attr($itemId); ?>" aria-label="<?php esc_attr_e('Increase quantity', 'coffeepos'); ?>">+</button>
        <button type="button" class="coffeepos-btn-icon" data-action="edit-cart-item" data-item-id="<?php echo esc_attr($itemId); ?>" aria-label="<?php esc_attr_e('Edit item', 'coffeepos'); ?>">✎</button>
        <button type="button" class="coffeepos-btn-icon" data-action="remove-cart-item" data-item-id="<?php echo esc_attr($itemId); ?>" aria-label="<?php esc_attr_e('Remove item', 'coffeepos'); ?>">×</button>
    </div>
    <div class="coffeepos-cart-item-price"><span><?php echo esc_html((string) ($item['line_total_display'] ?? '')); ?></span></div>
</article>
