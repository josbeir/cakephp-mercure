<?php
/**
 * Test element for ViewUpdate tests
 *
 * @var \Cake\View\View $this
 * @var string $title
 * @var string $content
 */
?>
<div class="test-item">
    <h2><?= h($title ?? '') ?></h2>
    <p><?= h($content ?? '') ?></p>
</div>
