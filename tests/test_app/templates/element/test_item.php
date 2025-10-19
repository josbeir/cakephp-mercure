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
    <h2><?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
    <p><?= htmlspecialchars($content ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</div>
