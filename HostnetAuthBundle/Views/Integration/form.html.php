<div class="well well-sm">
    <h3><?php echo $view['translator']->trans('mautic.integration.auth.qr_label'); ?></h3>
    <div class="row">
        <div class="col-md-4 col-md-offset-4" style="padding: 0 !important">
            <img style="width: 200px;height: 200px;" src="<?php echo $qrUrl; ?>"/>
        </div>
    </div>
</div>
