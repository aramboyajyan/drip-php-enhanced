<?php

$config = \Drupal::config('fo_drip.settings');
$client = new \Drip\Client(
$config->get('api_key'),
$config->get('account_id')
);

/** @var \Drupal\commerce_order\Entity\OrderInterface $order */
$order = \Drupal\commerce_order\Entity\Order::load(1);

/** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
$date_formatter = \Drupal::service('date.formatter');

$items = [];
/** @var \Drupal\commerce_order\Entity\OrderItemInterface $item */
foreach ($order->getItems() as $item) {
// TODO make sure that the order item is a product.
/** @var \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation */
$product_variation = $item->getPurchasedEntity();
/** @var \Drupal\commerce_product\Entity\ProductInterface $product */
$product = $product_variation->getProduct();

$items[] = (object) [
'product_id' => $product->id(),
'product_variant_id' => $product_variation->id(),
'name' => $item->label(),
'price' => $item->getUnitPrice()->getNumber(),
'quantity' => $item->getQuantity(),
'total' => $item->getAdjustedTotalPrice()->getNumber(),
];
}

$params = [
'provider' => 'drupal8',
'person_id' => $order->getCustomer()->getEmail(),
'action' => 'created',
'cart_id' => $order->id(),
'occurred_at' => $date_formatter->format($order->getCreatedTime(), 'custom', 'c'),
'grand_total' => $order->getTotalPrice()->getNumber(),
'currency' => 'USD',
'cart_url' => 'https://www.footballoutsiders.com/cart',
'items' => $items,
];

$response = $client->cart_activity($params);

if ($response->is_success()) {
dpm('Success!');
}
else {
dpm([
$response->get_http_code() => $response->get_http_message(),
]);
}
