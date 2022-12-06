<div class="display no-border">
	<div class="container-fluid">
		<div class="well toolbar">
			<span class="toolbar-title">
				<h1><?php echo $brandName; ?></h1>
			</span>
			<span class="toolbar-btn">
                <div class="btn-group">
                    <button type="button" class="btn btn-default btn-collapse-all" title="<?php echo _("Collapse All"); ?>"><i class="fa fa-chevron-up"></i></button>
                    <button type="button" class="btn btn-default btn-expand-all" title="<?php echo _("Expand All"); ?>"><i class="fa fa-chevron-down"></i></button>
                </div>
            </span>
			<span class="toolbar-btn">
				<button type="button" class="btn btn-debug"><i class="fa fa-cogs" aria-hidden="true"></i> <?php echo _("View Debug"); ?></button>
				<!-- If sync_with_userman is enabled hide the view password and email password links -->
				<?php if ($sync_with_userman != "1") : ?>
					<!-- Check if the initial password list needs to be shown -->
					<button type="button" class="btn btn-show-password"><i class="fa fa-key" aria-hidden="true"></i> <?php echo _('View Initial User Passwords'); ?></button>
					<!-- Create the email password link -->
					<button type="button" class="btn btn-sendmail-password" title="<?php echo _('Send initially generated passwords for extensions that have a voicemail email configured. Will not send password if it has been changed from the initially generated one.'); ?>"><i class="fa fa-paper-plane" aria-hidden="true"></i> <?php echo _('Email Initial Passwords'); ?></button>
				<?php endif ?>
			</span>
		</div>
	</div>
</div>