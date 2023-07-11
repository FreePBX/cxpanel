<?php
	Sage::$theme 			 = Sage::THEME_LIGHT;
	Sage::$returnOutput      = true;
	Sage::$displayCalledFrom = false;
	Sage::$expandedByDefault = true;
?>
<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Main Log")); ?><br>
				<?php echo sprintf('<textarea rows="10" cols="90">%s</textarea>', $debug['main_log']['read']); ?><br>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Modify Log")); ?><br>
				<?php echo sprintf('<textarea rows="10" cols="90">%s</textarea>', $debug['modify_log']['read']); ?><br>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Server")); ?><br>
				<?php echo sage($debug['server']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Email")); ?><br>
				<?php echo sage($debug['email']); ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Voicemail Agent")); ?><br>
				<?php echo sage($debug['voicemail_agent']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Recording Agent")); ?><br>
				<?php echo sage($debug['recording_agent']); ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Conference Rooms")); ?><br>
				<?php echo sage($debug['rooms']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Queues")); ?><br>
				<?php echo sage($debug['queues']); ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Users")); ?><br>
				<?php echo sage($debug['users']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Managed Items")); ?><br>
				<?php echo sage($debug['managed_items']); ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<?php echo sprintf('<b>%s</b>', _("Phone Numbers")); ?><br>
		<?php echo sage($debug['phone_numbers']); ?>
	</div>
</div>