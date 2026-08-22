<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$attributes = isset($attributes) && is_array($attributes) ? $attributes : [];
?>
<?php if ($attributes !== []) : ?>
    <section class="coffeepos-product-options" data-component="variation-options">
        <h4><?php esc_html_e('Variations', 'coffeepos'); ?></h4>
        <?php foreach ($attributes as $attribute) : ?>
            <?php
            $name = (string) ($attribute['name'] ?? '');
            $options = (array) ($attribute['options'] ?? []);
            ?>
            <?php if ($name !== '' && $options !== []) : ?>
                <fieldset class="coffeepos-option-group" data-attribute-name="<?php echo esc_attr($name); ?>">
                    <legend><?php echo esc_html($name); ?></legend>
                    <div class="coffeepos-option-list">
                        <?php foreach ($options as $option) : ?>
                            <button type="button" class="coffeepos-chip" data-action="select-variation-option" data-attribute-name="<?php echo esc_attr($name); ?>" data-attribute-value="<?php echo esc_attr((string) $option); ?>" aria-pressed="false">
                                <?php echo esc_html((string) $option); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
