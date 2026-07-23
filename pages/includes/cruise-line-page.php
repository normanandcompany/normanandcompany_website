<?php

if (!isset($cruiseLinePage) || !is_string($cruiseLinePage)) {
    throw new RuntimeException('A cruise-line database page value is required.');
}
?>
<section class="cruise-line-detail" data-cruise-line-page="<?= htmlspecialchars($cruiseLinePage, ENT_QUOTES, 'UTF-8') ?>">
    <div id="page-title-meta"
        data-title="Norman and Company | Cruise Line"
        style="display: none;">
    </div>

    <div id="cruiseLineDetail">
        <p>Loading cruise-line data...</p>
    </div>
</section>
