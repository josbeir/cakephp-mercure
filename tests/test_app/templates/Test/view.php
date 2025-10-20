<?php
/**
 * Test template for ViewUpdate tests
 *
 * @var \Cake\View\View $this
 * @var string $message
 * @var int $count
 */
?>
<div class="test-template">
    <div class="message"><?= h($message ?? '') ?></div>
    <div class="count">Count: <?= $count ?></div>
</div>
